<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Models\Institution;
use App\Models\Connection;
use App\Models\GlobalProvider;
use App\Models\Report;
use App\Models\HarvestLog;
use App\Models\InstitutionGroup;
use App\Models\ConnectionField;
use App\Models\Consortium;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class CredentialController extends Controller
{
    private $masterReports;

    /**
     * Return a listing of the resource.
     *
     * @return \Illuminate\Http\Response JSON
     */
    public function index(Request $request)
    {
        global $masterReports;

        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);

        // Get insitution and group limits for this user's role(s)
        $limit_to_insts = $thisUser->adminInsts();
        if ($limit_to_insts === [1]) $limit_to_insts = [];
        $limit_to_groups = $thisUser->adminGroups();
        if ($limit_to_groups === [1]) $limit_to_groups = [];

        // Get credentials
        $data = Credential::with('institution:id,name,is_active,local_id','provider','lastHarvest')
                          ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                              return $qry->whereIn('inst_id',$limit_to_insts);
                          })->get();

        // Pull all connections $thisUser can admin to get a set of GlobalProvider ids
        $connections = Connection::where('inst_id',1)
                                 ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                         return $qry->orWhereIn('inst_id',$limit_to_insts);
                                 })
                                 ->when(count($limit_to_groups) > 0, function ($qry) use ($limit_to_groups) {
                                         return $qry->orWhereIn('group_id',$limit_to_groups);
                                 })->get();
        $globalIds = $connections->unique('global_id')->pluck('global_id')->toArray();

        // Get all global providers
        $globals = GlobalProvider::with('registries','connections','connections.reports')
                                 ->orderBy('name','ASC')->get();

        // Setup filtering options for the datatable
        $global_connectors = ConnectionField::get();
        $filter_options = array();
        $filter_options['results'] = array();
        $filter_options['statuses'] = $data->unique('status')->pluck('status')->toArray();
        $filter_options['platforms'] = $globals->map(function ($plat) use ($global_connectors) {
            $_connectors = array();
            $required = $plat->connectors();
            foreach ($global_connectors as $gc) {
                $_connectors[$gc->name] = in_array($gc->id, $required);
            }
            return [ 'id' => $plat->id, 'name' => $plat->name, 'is_active' => $plat->is_active,
                     'connectors' => $_connectors ];
        });

        // Set institution and group filter options, depending on role(s)
        if ($thisUser->isConsoAdmin()) {
            // Conso admin allowed to replace a conso-connection with one to insts or groups
            $filter_options['groups'] = InstitutionGroup::whereNull('user_id')->orderBy('name','ASC')->get(['id','name']);
            $filter_options['institutions'] = Institution::where('is_active',1)->orderBy('name','ASC')
                                                         ->get(['id','name','is_active']);
        } else {
            $inst_ids = $thisUser->adminInsts();
            $filter_options['groups'] = array();
            $filter_options['institutions'] = Institution::where('is_active',1)->whereIn('id',$inst_ids)
                                                         ->orderBy('name','ASC')->get(['id','name','is_active']);
        }

        // Keep track of the last error values for the filter options
        $nh_count = 0;
        $seen_codes = array(0); // preset success code

        // Get master report definitions
        $this->getMasterReports();

        // Add provider global connectors and can_edit flag to the credentials
        $credentials = array();
        foreach ($data as $cred) {
            if (!$cred->provider) continue;
            // can_edit and can_delete true b/c $data limited above by adminInsts()
            $rec = array('id' => $cred->id, 'status' => $cred->status, 'inst_id' => $cred->inst_id,
                         'prov_id' => $cred->prov_id, 'customer_id' => $cred->customer_id,
                         'requestor_id' => $cred->requestor_id, 'api_key' => $cred->api_key,
                         'platform' => $cred->provider->name, 'can_edit' => true, 'can_delete' => true,
                         'inst_active' => $cred->institution->is_active, 'plat_active' => $cred->provider->is_active
                        );
            $rec['service_url'] = $cred->provider->service_url();
            $rec['connectors'] = array();
            $rec['institution'] = ($cred->institution) ? $cred->institution->name : '';
            $rec['local_id'] = ($cred->institution) ? $cred->institution->local_id : '';
            $required = $cred->provider->connectors();
            foreach ($global_connectors as $gc) {
                $cnx = $gc->toArray();
                $cnx['required'] = in_array($gc->id, $required);
                $rec['connectors'][] = $cnx;
            }
            $rec['connected'] = ($cred->status == 'Enabled') ? true : false;
            $lastHarvest = $cred->lastHarvest;
            if ($lastHarvest) {
                if ($lastHarvest->error_id>0 && !in_array($lastHarvest->error_id,$seen_codes)) {
                    $seen_codes[] = $lastHarvest->error_id;
                }
                $rec['result'] = ($lastHarvest->status == 'Success' || $lastHarvest->error_id==0)
                                 ? 'Success' : $lastHarvest->error_id;
            } else {
                $nh_count++;
                $rec['result'] = 'No Harvests';
            }

            // Set report flags for the related global
            $global = $globals->where('id',$cred->prov_id)->first();
            if ($global) {
                $enabledReports = $global->enabledReports($cred->inst_id);
                foreach ($enabledReports as $name => $rpt) {
                    $rec[$name] = $rpt;
                    if (!$rec[$name]['available']) {
                        $rec[$name]['sortval'] = 4;
                    } else if ($rec[$name]['conso']) {
                        $rec[$name]['sortval'] = 1;
                    } else {
                        $rec[$name]['sortval'] = ($rpt['requested']) ? 3 : 2;
                    }
                }
            } else {
                foreach ($masterReports as $rpt) {
                    $flags[$rpt->name] = array('available'=>false,'conso'=>false,'requested'=>false,
                                               'insts'=>[],'groups'=>[]);
                }
            }
            $credentials[] = $rec;
        }

        // Setup results filter options based on what is in records
        sort($seen_codes);
        foreach ($seen_codes as $code) {
            $_val = ($code>0) ? $code : 'Success';
            $filter_options['results'][] = array('result' => $_val);
        }
        if ($nh_count > 0) {
            $filter_options['results'][] = array('result' => 'No Harvests');
        }
        $filter_options['all_connectors'] = $global_connectors;

        // Return the data array
        return response()->json(['records' => $credentials, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response JSON
     */
    public function store(Request $request)
    {
        global $masterReports;

        $thisUser = auth()->user();
        $input = $request->all();

        // Check role against requested institution
        if (!$thisUser->canManage($input['inst_id'])) {
            return response()->json(['result' => false, 'msg' => 'You can only assign credentials for your institution']);
        }

        // Confirm valid provider ID (pointing at a global)
        if (!isset($input['prov_id'])) {
            return response()->json(['result' => false, 'msg' => 'Platform assignment is required']);
        }
        $gp = GlobalProvider::where('id',$input['prov_id'])->first();
        if (!$gp) {
            return response()->json(['result' => false, 'msg' => 'Platform not unknown or undefined']);
        }

        // If a matching credential already exists, return an error
        $exists = Credential::where('inst_id',$input['inst_id'])->where('prov_id',$input['prov_id'])->first();
        if ($exists) {
            return response()->json(['result' => false,
                                     'msg' => 'A credential already exists for this provider and institution']);
        }

        // If there is no existing connection defined for the global provider, create it now
        $connected_inst_ids = $gp->connectedInstitutions();
        if (!in_array(1,$connected_inst_ids) && !in_array($input['inst_id'],$connected_inst_ids)) {
            $conn_data = array('global_id' => $gp->id, 'is_active' => $gp->is_active, 'inst_id' => $input['inst_id']);
            $new_conn = Connection::create($conn_data);

            // Attach report definitions to new provider
            $global_report_ids = $gp->master_reports();
            $this->getMasterReports();
            $masters = $masterReports->whereIn('id',$global_report_ids);
            foreach ($masters as $rpt) {
                $new_conn->reports()->attach($rpt->id);
            }
        }

        // Create the new credential record and load institution and provider relationships
        $fields = Arr::except($input,array('institutions','platforms'));
        $cred = Credential::firstOrCreate($fields);
        $cred->load('institution', 'provider');

        // Setup return data record to conform to index() records/keys
        // (migration sets status='Enabled' by default, $thisUser created it, they can edit/delete)
        $global_connectors = ConnectionField::get();
        $rec = array('id' => $cred->id, 'status' => 'Enabled', 'connected' => true,  'result' => 'No Harvests',
                     'inst_id' => $cred->inst_id, 'prov_id' => $cred->prov_id, 'customer_id' => $cred->customer_id,
                     'requestor_id' => $cred->requestor_id, 'api_key' => $cred->api_key,
                     'platform' => $cred->provider->name, 'can_edit' => true, 'can_delete' => true
                    );
        $rec['service_url'] = $cred->provider->service_url();
        $rec['connectors'] = array();
        $rec['institution'] = ($cred->institution) ? $cred->institution->name : '';
        $required = $cred->provider->connectors();
        foreach ($global_connectors as $gc) {
            $cnx = $gc->toArray();
            $cnx['required'] = in_array($gc->id, $required);
            $rec['connectors'][] = $cnx;
        }

        // Set report flags for the related global
        $enabledReports = $gp->enabledReports($cred->inst_id);
        foreach ($enabledReports as $name => $rpt) {
            $rec[$name] = $rpt;
            if (!$rec[$name]['available']) {
                $rec[$name]['sortval'] = 4;
            } else if ($rec[$name]['conso']) {
                $rec[$name]['sortval'] = 1;
            } else {
                $rec[$name]['sortval'] = ($rpt['requested']) ? 3 : 2;
            }
        }

        return response()->json(['result' => true, 'msg' => 'Credentials successfully created', 'record' => $rec]);
    }

    /**
     * Set/update report access/availability from credential report toggles)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response JSON
     */
    public function update(Request $request)
    {
        global $masterReports;

        // Validate form inputs
        $thisUser = auth()->user();
        $this->validate($request, ['id' => 'required']);
        $input = $request->all();

        // Get the credential
        $cred = Credential::with('institution','provider')->findOrFail($input['id']);

        // Setup fields for updating/creating
        $fields = array('status' => 'Enabled');    // default for new records
        $fields['customer_id'] = isset($input['customer_id']) ? $input['customer_id'] : null;
        $fields['requestor_id'] = isset($input['requestor_id']) ? $input['requestor_id'] : null;
        $fields['api_key'] = isset($input['api_key']) ? $input['api_key'] : null;
        $fields['extra_args'] = isset($input['extra_args']) ? $input['extra_args'] : null;

        // Credential exists, set fields with input values
        if ($cred) {
            $fields['status'] = ($input['status'] == 'Enabled' || $input['status'] == 'Disabled')
                                ? $input['status'] : $cred->status;
            // Confirm global provider exists
            $global = GlobalProvider::findOrFail($cred->prov_id);
            // Ensure user is allowed to change the credential
            $institution = Institution::findOrFail($cred->inst_id);
            if (!$institution->canManage()) {
                return response()->json(['result' => false, 'msg' => 'Not Authorized to update credential']);
            }
            // Update $cred with user inputs
            foreach ($fields as $fld => $val) {
                $cred->$fld = $val;
            }

        // if not found, try to create one
        } else {
            if (!isset($input["inst_id"]) || !isset($input["prov_id"])) {
                return response()->json(['result' => false, 'msg' => 'Missing credentials arguments from request']);
            }
            if (!$thisUser->isConsoAdmin()) {
                $limit_insts = $thisUser->adminInsts();
                if (!in_array($input['inst_id'], $limit_insts)) {
                    return response()->json(['result' => false, 'msg' => 'Not authorized for requested institution']);
                }
            }
            $fields['status'] = (isset($input['status'])) ? $input['status'] : 'Enabled';
            $fields['inst_id'] = $input['inst_id'];
            $fields['inst_id'] = $input['prov_id'];
            $cred = Credential::create($fields);
            $cred->load('institution','provider');
        }

        // If inst/prov not properly referenced, bail out
        if (!$cred->institution || !$cred->provider) {
            return response()->json(['result' => false, 'msg' => 'Credential reference error for platform or institution']);
        }

        // Check/update connection fields; any null/blank required connectors get updated
        $registry = $cred->provider->default_registry();
        $all_connectors = ConnectionField::get();
        foreach ($all_connectors as $cnx) {
            if (in_array($cnx->id, $registry->connectors) &&
                is_null($cred->{$cnx->name}) || $cred->{$cnx->name} == '') {
                $cred->{$cnx->name} = '-required-';
            }
        }

        // Updating a credential record means the validation values get reset
        $cred->plat_valid = null;
        $cred->inst_valid = null;

        // If user wants to disable, just save the record
        if ($cred->status == 'Disabled') {
            $cred->save();
        // Otherwise, call resetStatus to update/verify status based on connectors and prov/inst is_active values
        } else {
            $cred->resetStatus(true);   // this also handles ->save()
        }

        // Update report settings
        $this->getMasterReports();
        foreach ($masterReports as $rpt) {
            if (!isset($input[$rpt->name])) continue;   // report name not in inputs?
            if ($input[$rpt->name]['conso']) continue;  // skip report if it's set to conso

            // Update the report setting
            $flags = (isset($input[$rpt->name])) ? $input[$rpt->name] : array();

            $result = $this->updateInstReport($cred->inst_id, $cred->provider, $rpt->id, $flags);
            if (!$result['success']) {
                return response()->json(['result' => false, 'msg' => $result['msg']]);
            }
        }

        // Load connections and connection.reports to provider 
        $global->load('connections','connections.reports');
        $enabledReports = $global->enabledReports($cred->inst_id);

        // Setup return record based on inputs, to include any other changes applied here
        $record = $input;
        $record['status'] = $cred->status;
        $record['platform'] = $cred->provider->name;
        $record['institution'] = $cred->institution->name;
        foreach ($all_connectors as $gc) {
            $cnx = $gc->toArray();
            $cnx['required'] = in_array($gc->id, $registry->connectors);
            $record['connectors'][] = $cnx;
        }
        $rec['connected'] = ($cred->status == 'Enabled') ? true : false;
        foreach ($enabledReports as $name => $rpt) {
            $record[$name] = $rpt;
            if (!$record[$name]['available']) {
                $record[$name]['sortval'] = 4;
            } else if ($record[$name]['conso']) {
                $record[$name]['sortval'] = 1;
            } else {
                $record[$name]['sortval'] = ($rpt['requested']) ? 3 : 2;
            }
        }

        return response()->json(['result' => true, 'msg' => 'Credential updated successfully', 'record' => $record]);
    }

    /**
     * Update report definition for a connection
     * @param  Integer  $inst_id
     * @param  GlobalProvider  $global
     * @param  Integer  $report_id
     * @param  Array    $flags
     * @return Array    $result
     */
    private function updateInstReport($inst_id, $global, $report_id, $flags)
    {
        // Get global provider; bail if not found
        if ($global) {
            $result = array('success' => true, 'msg' => '');
        } else {
            return array('success' => false, 'msg' => 'Global platform not found or report unavailable');
        }
        $requested = (isset($flags['requested'])) ? $flags['requested'] : false;
        $attached = false;

        // Get connection record
        $cnx = Connection::where('inst_id',$inst_id)->where('global_id',$global->id)->with('reports')->first();
        if ($cnx) {
            $attached = (!is_null($cnx->reports->where('id',$report_id)->first()));
        } else if ($requested) {
            // Enabling a report for a platform not-yet connected?
            $_data = array('name' => $global->name, 'global_id' => $global->id, 'is_active' => $global->is_active,
                            'inst_id' => $inst_id);
            $cnx = Connection::create($_data);
        }

        // Update report
        try {
            // Attach/add
            if ($requested && !$attached) {
                $cnx->reports()->attach($report_id);
            }
            // Detach/remove
            if ($attached && !$requested) {
                $cnx->reports()->detach($report_id);
                // If the provider has no remaining reports attached, delete it
                if ($cnx->reports()->count() == 0) {
                    $cnx->delete();
                }
            }
        } catch (\Exception $e) {
            return array('success' => false, 'msg' => $e->getMessage());
        }
        return $result;
    }

    /**
     * Test the credentials for a given provider-institution.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function test(Request $request)
    {

//NOTE:: U/I was testing to only allow 5.1 release targets to run service status tests.. 

      // Validate form inputs
      // Get and verify input or bail with error in json response
        try {
            $input = json_decode($request->getContent(), true);
        } catch (\Exception $e) {
            return response()->json(['result' => false, 'msg' => 'Error decoding input!']);
        }
        if (!isset($input['type'])) {
            return response()->json(['result' => false, 'msg' => 'Type is a required argument!']);
        }
        $provider = GlobalProvider::findOrFail($input['prov_id']);

        // ASME (there may be others) checks the Agent and returns 403 if it doesn't like what it sees
        $options = [
            'headers' => ['User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0"]
        ];

       // Begin setting up the URI by cleaning/standardizing the service_url string in the credential
        $_url = rtrim($provider->service_url());    // remove trailing whitespace
        $_url = preg_replace('/\/reports\/?$/i', '', $_url);  // take off any methods with any leading slashes
        $_url = preg_replace('/\/status\/?$/i', '', $_url);  //   "   "   "     "      "   "     "        "
        $_url = preg_replace('/\/members\/?$/i', '', $_url); //   "   "   "     "      "   "     "        "
        $_uri = rtrim($_url, '/');                           // remove any remaining trailing slashes

       // If we got extra_args, try to clean it up and strip any leading "&" or "?"
        if (isset($input['extra_args'])) {
          $input['extra_args'] = trim($input['extra_args']);
          $input['extra_args'] = ltrim($input['extra_args'], "&?");
        }

       // For credentials test, build and execute a request for a PR report of last month
        if ($input['type'] == 'test') {
            $_uri .= '/reports/pr?';
            $uri_auth = "";

            // If a platform value is set, start with it
            if (!is_null($provider->platform_parm)) {
                $uri_auth = "platform=" . $provider->platform_parm;
            }

            $fields = array('customer_id', 'requestor_id', 'api_key', 'extra_args');
            foreach ($fields as $fld) {
            if (isset($input[$fld])) {
                $uri_auth .= ($uri_auth == '') ? "" : "&";
                if ($fld == 'extra_args') {
                    $uri_auth .= urlencode($input['extra_args']);
                } else {
                    $uri_auth .= $fld . '=' . urlencode($input[$fld]);
                }
            }
            }
            $dates = '&begin_date=' . date('Y-m-d', strtotime('first day of previous month')) .
                    '&end_date=' . date('Y-m-d', strtotime('last day of previous month'));
            $request_uri = $_uri . $uri_auth . $dates;
        }
       // Make the request and convert result into JSON
        $rows = array();
        $client = new Client();   //GuzzleHttp\Client
        $result = '';
        try {
            if ($input['type'] == 'status') {
                $response = $client->request('GET', $_uri . "/status");
                $json = $response->getBody();
                $rows[] = "Service Status: ";
            } else {
                $response = $client->request('GET', $request_uri, $options);
                $json = $response->getBody();
                $rows[] = "Request URL: ";
                $rows[] = $request_uri;
                $rows[] = "JSON Response:";
            }
            $begin_txt = substr(trim($json),0,80);
            if (stripos($begin_txt,"doctype html") || stripos($begin_txt,"<html>" )) {
                $result = 'Invalid response - JSON expected but request returned HTML';
                $rows[0] = '';
            } else if (!is_object($json)) { // Badly formed JSON?
                $result = 'Invalid response - JSON expected but not received - check URL';
                $rows[0] = '';
            }
            if ($result == '') {
                $result = 'Request response successfully received';
                $rows[] = json_decode($json, JSON_PRETTY_PRINT);
            }
        } catch (\Exception $e) {
            $rows[] = $e->getMessage();
            return response()->json(['result' => false, 'msg' => 'Request for service status failed!',
                                     'rows' => $rows]);
        }

       // return result
        return response()->json(['result' => true, 'rows' => $rows, 'msg' => $result]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Credential  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $credential = Credential::with('provider','institution')->findOrFail($id);
        if (!$credential->institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        $result = $this->updateInstReport($credential->inst_id, $credential->provider, $mr->id, $flags);
        if (!$result['success']) {
            return response()->json(['result' => false, 'msg' => $result['msg']]);
        }

        $credential->delete();
        return response()->json(['result' => true, 'msg' => 'Credentials successfully deleted']);
    }

    /**
     * Bulk operations from the U/I.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function bulk(Request $request)
    {
        global $thisUser;
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $consoAdmin = $thisUser->isConsoAdmin();

        // Validate form inputs
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();

        // Get insitution and group limits for this user's role(s)
        $limit_to_insts = $thisUser->adminInsts();
        if ($limit_to_insts === [1]) $limit_to_insts = [];
        $limit_to_groups = $thisUser->adminGroups();
        if ($limit_to_groups === [1]) $limit_to_groups = [];

        // Get credentials
        $credentials = Credential::with('institution:id,name,is_active','provider','lastHarvest')
                                 ->whereIn('id',$input['ids'])
                                 ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                     return $qry->whereIn('inst_id',$limit_to_insts);
                                 })->get();

        // For Enable, confirm inst/prov is_active and required connection values set using class resetStatus()
        if ($input['action'] == 'Enable') {
            // Loop across all requested+allowed credentials
            $affectedItems = array();
            foreach ($credentials as $cred) {
                $status_before = $cred->status;
                $cred->status = 'Enabled';
                // This may update/hange status, and will save the credential
                $cred->resetStatus();
                if ($cred->status != $status_before) {
                    $affectedItems[] = array('id' => $cred->id, 'status' => $cred->status);
                }
            }
            return response()->json(['result' => true, 'msg' => '', 'affectedItems' => $affectedItems], 200);

        // For Disable, just update the status for credentials not already disabled
        } else if ($input['action'] == 'Disable') {
            $credIds = $credentials->where('status','<>','Disabled')->pluck('id')->toArray();
            Credential::whereIn('id',$credIds)->update(['status' => 'Disabled']);
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds], 200);

        // Handle delete
        } else if ($input['action'] == 'Delete') {
            $credIds = $credentials->pluck('id')->toArray();
            Credential::whereIn('id',$credIds)->delete();
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds], 200);

        // Handle audit actions
        } else if (str_contains($input['action'], " Validat")) {
            $credIds = $credentials->pluck('id')->toArray();
            $_ts = date('Y-m-d H:i:s');
            $args = array();
            if ($input['action'] == "Set Institution Validated") {
                $args = array('inst_valid' => $_ts);
            } else if ($input['action'] == "Set Platform Validated") {
                $args = array('plat_valid' => $_ts);
            } else if ($input['action'] == "Clear Validation") {
                $args = array('inst_valid' => null, 'plat_valid' => null);
            }
            if ( count($args) > 0) {
                Credential::whereIn('id',$credIds)->update($args);
                return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $credIds, 'data' => $args], 200);
            } else {
                return response()->json(['result' => false, 'msg' => 'Unrecognized bulk action requested'], 200);
            }

        // Unrecognized action
        } else {
            return response()->json(['result' => false, 'msg' => 'Unrecognized bulk action requested'], 200);
        }
    }

    /**
     * Return an array of IDs for not-set credentials
     *   $input['inst_id'] returns platform IDs without a credential for the institution
     *   $input['plat_id'] returns institution IDs without a credential for the platform
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON $unset_ids
     */
    public function unset(Request $request)
    {
        global $thisUser;
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $input = $request->all();

        // Get insitution limits for this user's role(s) if not consoAdmin
        $consoAdmin = $thisUser->isConsoAdmin();
        $limit_insts = ($consoAdmin) ? [] : $thisUser->adminInsts();

        $unset_ids = array();
        // Return an array of unset platforms for the institution
        if ( isset($input['inst_id']) ) {
            if (!$consoAdmin && !in_array($input['inst_id'],$limit_insts)) {
                return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
            }
            $allPlatIds = GlobalProvider::where('is_active',1)->pluck('id')->toArray();
            $setPlatIds = Credential::where('inst_id',$input['inst_id'])->pluck('prov_id')->toArray();
            $unset_ids = array_diff($allPlatIds, $setPlatIds);

        // Return an array of unset institutions for the platform
        } else if ( isset($input['plat_id']) ) {
            $allInstIds = Institution::where('is_active',1)->pluck('id')->toArray();
            $setInstIds = Credential::where('prov_id',$input['plat_id'])
                                    ->when(!$consoAdmin, function ($qry) use ($limit_insts) {
                                        return $qry->whereIn('inst_id',$limit_insts);
                                    })->pluck('inst_id')->toArray();
            $unset_ids = array_diff($allInstIds, $setInstIds);

        // Return an error
        } else {
            return response()->json(['result' => false, 'msg' => 'Request failed - Missing argument(s)']);
        }
        return response()->json(['result' => true, 'msg' => '', 'unset_ids' => array_values($unset_ids)]);
    }

    /**
     * Import credentials from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        $thisUser = auth()->user();

        // Only Admins and Managers can import institution data
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Set role-based limits
        $consoAdmin = $thisUser->isConsoAdmin();
        $adminInsts = $thisUser->adminInsts();
        $adminGroups = $thisUser->adminGroups();

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get institution records that $thisUser can Admin
        $institutions = Institution::when(!$consoAdmin , function ($qry) use ($adminInsts) {
                                         return $qry->whereIn('id', $adminInsts);
                                     })->orderBy('name', 'ASC')->get();

        // Get all global platforms with their connections
        $all_platforms = GlobalProvider::with('connections')->get(['id','name']);

        // Process the input rows
        $updated = 0;
        $deleted = 0;
        $skipped = 0;
        $incomplete = 0;
        foreach ($rows as $rowNum => $row) {
            // Ignore header row and rows with bad/missing/invalid IDs
            if ($rowNum == 0 || !isset($row[0]) && !isset($row[1])) continue;

            // Look for a matching existing institution based on ID or local-ID
            $input_inst_id = (isset($row[0])) ? strval(trim($row[0])) : null;
            $localID = (strlen(trim($row[1])) > 0) ? trim($row[1]) : null;
            // empty/missing/invalid ID and no localID?  skip the row
            if (!$localID && ($input_inst_id == "" || !is_numeric($input_inst_id))) {
                $skipped++;
                continue;
            }

            // Get the current institution
            $institution = null;
            if ($localID) {
                $institution = $institutions->where("local_id", $localID)->first();
            } else if (!is_null($input_inst_id)) {
                $institution = $institutions->where("id", $input_inst_id)->first();
            }
            // If no ID and $localID not found, skip the row
            if (!$institution) {
                $skipped++;
                continue;
            }

            // Get the credentials' platform
            $platform = $all_platforms->where('id',$row[3])->first();
            if (!$platform) {
                $skipped++;
                continue;
            }

            // Create a connection if one does not exist for this inst->platform
//NOTE:: This only creates a new connection - there will be NO attached
//       reports unless/until someone assigns some elsewhere (U/I or import on connections)
            $connectedInstIds = $platform->connectedInstitutions();
            if (!in_array($institution->id,$connectedInstIds) && !in_array(1,$connectedInstIds)) {
                $newCnx = array('global_id'=>$platform->id, 'is_active'=>1, 'inst_id'=>$institution->id);
                $cnx = Connection::create($newCnx);
            }

            // Put credentials into an array (assumes status should be Enabled) for the update call.
            $_args = array('status' => 'Enabled');
            $_args['customer_id'] = (isset($row[5])) ? trim($row[5]) : null;
            $_args['requestor_id'] = (isset($row[6])) ? trim($row[6]) : null;
            $_args['api_key'] = (isset($row[7])) ? trim($row[7]) : null;
            $_args['extra_args'] = (isset($row[8])) ? trim($row[8]) : null;

            // Mark any missing connectors
            $missing_count = 0;
            $connectors = $platform->connectionFields();
            foreach ($connectors as $c) {
                if ( !$c['required']) {
                    continue;
                } else {
                    if ( is_null($_args[$c['name']]) || $_args[$c['name']] == '' ) {
                        $_args[$c['name']] = "-required-";
                        $missing_count++;
                    }
                }
            }

            // Override default status if credentials missing or inst/prov are inactive
            if ($institution->is_active && $platform->is_active) {
                if ( $missing_count==0 ) {
                    $_args['status'] = 'Enabled';
                } else {
                    $_args['status'] = 'Incomplete';
                    $incomplete++;
                }
            } else {
              $_args['status'] = 'Suspended';
            }

            // Clear validation if connection field values are being changed for an existing credential
            $exists = Credential::where('inst_id',$institution->id)->where('prov_id',$platform->id)->first();
            if ($exists) {
                if ($exists->customer_id != $_args['customer_id']   || $exists->api_key != $_args['api_key'] || 
                    $exists->requestor_id != $_args['requestor_id'] || $exists->extra_args != $_args['extra_args']) {
                    $_args['plat_valid'] = null;
                    $_args['inst_valid'] = null;
                }
            }

            // Update or create the credentials
            $current_credential = Credential::updateOrCreate(['inst_id'=>$institution->id, 'prov_id'=>$platform->id], $_args);
            $updated++;
        }

        // Setup return info message
        $msg = "";
        $msg .= ($updated > 0) ? $updated . " added or updated" : "";
        if ($incomplete > 0) {
            $msg .= " (" . $incomplete . " were incomplete)";
        }
        if ($skipped > 0) {
            $msg .= ($msg != "") ? ", " . $skipped . " skipped" : $skipped . " skipped";
        }
        $msg  = 'COUNTER credentials import completed : ' . $msg;

        return response()->json(['result' => true, 'msg' => $msg]);
    }

   /**
    * Returns filter_options for the U/I. Item records returned with auditItems() below
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
    public function audit(Request $request)
    {
        // Only Admins can audit settings
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        // Set arrays for limiting institutions and platforms
        $limit_insts = ($consoAdmin) ? [] : $thisUser->adminInsts();
        $connections = Connection::whereNotNull('global_id')->get();
        $limit_plats = $connections->pluck('global_id')->toArray();

        // Get all institutions and their group-membership to support filter-by-group in the UI
        $all_institutions = Institution::with('institutionGroups:id,name')
                                        ->when(!$consoAdmin, function ($query) use ($limit_insts) {
                                            return $query->whereIn('id', $limit_insts);
                                        })->orderBy('name','ASC')->get();

        // Setup filtering options for the datatable
        $filter_options = array();
        $filter_options['valid_types'] = array(
            'Fully Validated','Not Fully Validated','Institution Not Validated','Platform Not Validated'
        );
        $globals = GlobalProvider::with('connections')->whereIn('id',$limit_plats)->where('is_active',1)
                                 ->orderBy('name','ASC')->get(['id','name']);
        $filter_options['platforms'] = $globals->map(function ($plat) {
            $is_conso = ($plat->connections()->where('inst_id',1)->count() > 0) ? true : false;
            return [ 'id' => $plat->id, 'name' => $plat->name, 'is_conso' => $is_conso ];
        });
        // Set institutions and groups filter options
        $adminGroups = ($consoAdmin) ? [] : $thisUser->adminGroups();
        $filter_options['groups'] = InstitutionGroup::when(!$consoAdmin, function ($query) use ($adminGroups) {
                                                        return $query->whereIn('id', $adminGroups);
                                                    })->orderBy('name','ASC')->get(['id','name']);
        $filter_options['institutions'] = $all_institutions->map(function ($rec) {
            return [ 'id' => $rec['id'], 'name' => $rec['name'] ];
        })->toArray();

        return response()->json(['records' => [], 'options' => $filter_options], 200);
    }

   /**
    * Returns (possibly filtered) items for the U/I
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response or JSON
    */
    public function auditItems(Request $request)
    {
        // Only Admins can audit settings
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);
        $consoAdmin = $thisUser->isConsoAdmin();

        $input = $request->all();
        $type = (isset($input['type'])) ? $input['type'] : 'harvests';
        if ($type!='audit') {
            return response()->json(['result' => false, 'msg' => 'Invalid request type for auditItems']);
        }

        // Setup for known filters
        $filters = array('valid_types' => [], 'platforms' => [], 'institutions' => [], 'groups' => []);
        $validations = array(
            'Fully Validated','Not Fully Validated','Institution Not Validated','Platform Not Validated'
        );

        // Get current consortium and JSON report data file path
        $report_path = null;
        $key = $request->header('X-Tenant');
        $conso = Consortium::where('ccp_key',$key)->first();
        if (!$conso) {
            return response()->json(['result'=>false, 'msg'=>'Error getting current instance data']);
        }
        if (!is_null(config('ccplus.reports_path'))) {
            $report_path = config('ccplus.reports_path') . $conso->id . '/';
        } else {
            return response()->json(['result'=>false, 'msg'=>'Global Setting for reports_path is undefined - Stopping!']);
        }

        // Validate/handle input filters
        if ($input['filters']) {
            foreach ($input['filters'] as $key => $val) {
                $filters[$key] = (is_null($val)) ? [] : $val;
            }
        }

        // Enforce limits on institutions and groups
        $limit_insts = array();
        $group_insts = array();
        // Turn groups into a set of institutions (harvests happen to insts, not groups)
        if (count($filters['groups']) > 0 || !$consoAdmin) {
            $limit_groups = (!$consoAdmin) ? array_intersect($thisUser->adminGroups(), $filters['groups'])
                                           : $filters['groups'];
            $data = InstitutionGroup::whereIn('id',$limit_groups)->with('institutions:id,name')->get();
            foreach ($data as $group) {
                $_inst_ids = $group->institutions->pluck('id')->toArray();
                $group_insts = array_unique(array_merge($group_insts, $_inst_ids));
            }
            $limit_insts = $group_insts;
        }

        if (count($filters['institutions']) > 0 || !$consoAdmin) {
            $limit_insts = (!$consoAdmin) ? array_intersect($thisUser->adminInsts(), $filters['institutions'])
                                          : $filters['institutions'];
        }
        if (count($filters['institutions']) > 0 && count($filters['groups']) > 0) {
            $limit_insts = array_intersect($limit_insts,$group_insts);
        }

        // Limit platforms to intersection of all-connected and filter value (if set)
        $connections = Connection::whereNotNull('global_id')->get();
        $conso_plats = $connections->where('inst_id',1)->pluck('global_id')->toArray();
        $limit_plats = $connections->pluck('global_id')->toArray();
        if (count($filters['platforms']) > 0) {
            $limit_plats = array_intersect($limit_plats, $filters['platforms']);
        }

        // Get all credentials with successful harvestlogs that have a rawfile set
        $credentials = Credential::with(['provider','institution', 'harvestLogs' => function ($qry) {
                                       $qry->where('status','Success')->whereNotNull('rawfile')->orderBy('yearmon','DESC');
                                 }])
                                 ->when(count($limit_insts)>0, function ($query) use ($limit_insts) {
                                     return $query->whereIn('inst_id', $limit_insts);
                                 })
                                 ->when(count($limit_plats)>0, function ($query) use ($limit_plats) {
                                     return $query->whereIn('prov_id', $limit_plats);
                                 })
                                 ->get();

        if (!$credentials) {
            return response()->json(['result'=>false, 'msg'=>'No matching connected credentials to audit.']);
        }

        // Get all institutions and their group-membership to support filter-by-group in the UI
        $all_institutions = Institution::with('institutionGroups:id,name')
                                        ->when(!$consoAdmin, function ($query) use ($limit_insts) {
                                            return $query->whereIn('id', $limit_insts);
                                        })->orderBy('name','ASC')->get();

        // Loop over the credentials - we'll create either a spreadsheet or an array of
        // records to return via JSON to the U/I
        $records = array();
        foreach ($credentials as $credential) {
            if (is_null($credential->provider) || is_null($credential->institution)) continue;

            // Apply validations filter if provided
            if ( in_array($filters['valid_types'],$validations) ) {
                if ( ($filters['valid_types'] == "Fully Validated" &&
                      (!$credential->inst_valid || !$credential->plat_valid)) ||
                     ($filters['valid_types'] == "Not Fully Validated" &&
                      (!is_null($credential->inst_valid) && !is_null($credential->plat_valid))) ||
                     ($filters['valid_types'] == "Institution Not Validated" &&
                      (!is_null($credential->inst_valid))) ||
                     ($filters['valid_types'] == "Platform Not Validated" &&
                      (!is_null($credential->plat_valid)))
                   ) {
                    continue;
                }
            }
            $record = array('id' => $credential->id,
                            'plat_id' => $credential->prov_id, 'plat_name'=>$credential->provider->name,
                            'inst_id' => $credential->inst_id, 'inst_name'=>$credential->institution->name);

            // Set valid_state and status based on inst/plat validated
            $record['inst_valid'] = (is_null($credential->inst_valid)) ? 'Inactive' : 'Active';
            $record['plat_valid'] = (is_null($credential->plat_valid)) ? 'Inactive' : 'Active';
            $record['is_conso'] = (in_array($credential->prov_id,$conso_plats));
            // Return valid_state as an array of values to match filter options
            $record['valid_state'] = array();
            if (!is_null($credential->inst_valid) && !is_null($credential->plat_valid)) {
                $record['valid_state'][] = 'Fully Validated';
            } else {
                if (is_null($credential->inst_valid)) $record['valid_state'][] = 'Institution Not Validated';
                if (is_null($credential->plat_valid)) $record['valid_state'][] = 'Platform Not Validated';
                if (is_null($credential->inst_valid) || is_null($credential->plat_valid)) {
                    $record['valid_state'][] = 'Not Fully Validated';
                }
            }

            // Pull JSON data from the most-recent rawfile in the harvestlogs for this credential
            $json_host = 'No data host'; // default to no-data-found
            $json_inst = 'No institution'; // default to no-data-found
            $json_item = 'No platform'; // default to no-data-found
            if ($credential->harvestLogs) {
                foreach ($credential->harvestLogs as $harv) {
                    $jsonFile = $report_path . '/' . $credential->inst_id . '/' . $credential->prov_id . '/' . $harv->rawfile;
                    if (file_exists($jsonFile)) {
                        // decrypt and decompress the file
                        $json = json_decode(bzdecompress(Crypt::decrypt(File::get($jsonFile), false)));
                        // get JSON fields
                        if (isset($json->Report_Header)) {
                           $header = $json->Report_Header;
                           $json_host = (isset($header->Created_By)) ? $header->Created_By : "no-Created_By";
                           // tack global_provider:platform_parm to datahost, if defined
                           if (!is_null($credential->provider->platform_parm)) {
                               $json_host .= "(" . $credential->provider->platform_parm . ")";
                           }
                           $json_inst = (isset($header->Institution_Name)) ? $header->Institution_Name : "no-Institution_Name";
                           if (isset($json->Report_Items) && is_array($json->Report_Items)) {
                                if (isset($json->Report_Items[0]->Platform)) {
                                    $json_item = $json->Report_Items[0]->Platform;
                                }
                           }
                        } else {
                            $json_host = 'no-Report_Header';
                            $json_inst = 'no-Report_Header';
                        }
                    }

                    // if we got values, go on to the next credential (otherwise, try another harvest)
                    if (substr($json_host,0,3)!="no-" && substr($json_inst,0,3)!="no-") {
                        break;
                    }
                }
            }
            $record['json_host'] = $json_host;
            $record['json_item'] = $json_item;
            $record['json_inst'] = $json_inst;
            $record['inst_set'] = ($json_inst == 'No institution') ? false : true;
            $record['plat_set'] = ($json_item == 'No platform') ? false : true;
            $record['host_set'] = ($json_host == 'No data host') ? false : true;
            $_inst = $all_institutions->where('id',$credential->inst_id)->first();
            $record['group_ids'] = ($_inst) ? $_inst->institutionGroups()->pluck('institution_group_id')->all() : [];
            $records[] = $record;
        }

        // Return the data array
        return response()->json(['result' => true, 'records' => $records, 'truncated' => false], 200);
    }

    /**
     * Update credential as audit-validated
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Credential  $credential
     * @return JSON
     */
    public function setvalidated(Request $request, Credential $credential)
    {
        // Validate form inputs
        $thisUser = auth()->user();
        $input = $request->all();
        // Set column to update
        if (!isset($input['inst_valid']) && !isset($input['plat_valid'])) {
            return response()->json(['msg' => 'Invalid request - no such column', 'result' => false], 200);
        }
        $key = (isset($input['inst_valid'])) ? 'inst_valid' : 'plat_valid';

        // Confirm user's access to change the credential
        $limit_to_insts = $thisUser->adminInsts();
        if (!$thisUser->isConsoAdmin() && !in_array($credential->id, $limit_to_insts)) {
            return response()->json(['result' => false], 200);
        }

        // Set field & save record
        $credential->{$key} = ($input[$key] == 'Active') ? date('Y-m-d H:i:s') : null;
        $credential->save();

        return response()->json(['result' => true], 200);
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getMasterReports() {
        global $masterReports;
        $masterReports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
    }

}
