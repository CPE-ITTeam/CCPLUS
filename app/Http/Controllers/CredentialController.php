<?php

namespace App\Http\Controllers;

use App\Models\Credential;
use App\Models\Institution;
use App\Models\Provider;
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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class CredentialController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasRole('Admin'), 403);

        // Get Providers for this user's role(s)
        $limit_to_insts = $thisUser->adminInsts();
        if ($limit_to_insts === [1]) $limit_to_insts = [];
        $providers = Provider::when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                        return $qry->whereIn('inst_id',[1,$limit_to_insts]);                                                  
                                   })
                             ->get(['id','inst_id','global_id']);

        // Get credentials
        $providerIds = $providers->unique('global_id')->pluck('global_id')->toArray();
        $data = Credential::with('institution:id,name,is_active','provider')
                          ->whereIn('prov_id', $providerIds)->get();

        // Get and map global providers
        $gdata = GlobalProvider::with('registries')->whereIn('id',$providerIds)->get();
        $globals = $gdata->map(function ($rec) use ($providers) {
            $consoCnx = $providers->where('global_id',$rec->id)->where('inst_id',1)->first();
            if ($consoCnx) {
                $rec->conso_reports = $consoCnx->reports->pluck('id')->toArray();
            } else {
                $rec->conso_reports = [];
            }
            return $rec;
        });

        // Setup filtering options for the datatable
        $filter_options = array('statuses' => array('ALL','Active','Inactive'));
        // $filter_options['platforms'] = $gdata->pluck('id','name')->toArray();
        $filter_options['platforms'] = $gdata->map(function ($plat) {
            return [ 'id' => $plat->id, 'name' => $plat->name ];
        });
        $instIds = $providers->pluck('inst_id')->toArray();
        $filter_options['institutions'] = Institution::whereIn('id',$instIds)->get(['id','name'])->toArray();

        // Get master report definitions
        $master_reports = Report::where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);

        // Add provider global connectors and can_edit flag to the credentials
        $credentials = array();
        $global_connectors = ConnectionField::get();
        foreach ($data as $cred) {
            if (!$cred->provider) continue;
            $rec = array('value' => $cred->id, 'customerId' => $cred->customer_id,
                         'requestorId' => $cred->requestor_id, 'apiKey' => $cred->api_key
                        );
// commented -for now- may need more return differently depending on edits/updates/etc,
            // $rec['platform'] = $cred->provider->toArray();
            // $rec['platform']['service_url'] = $cred->provider->service_url();
            // $rec['platform']['connectors'] = array();
            $rec['platform'] = $cred->provider->name;
            $rec['service_url'] = $cred->provider->service_url();
            $rec['connectors'] = array();
            $rec['institution'] = ($cred->institution) ? $cred->institution->name : '';
            $required = $cred->provider->connectors();
            foreach ($global_connectors as $gc) {
                $cnx = $gc->toArray();
                $cnx['required'] = in_array($gc->id, $required);
// commented -for now- may need more return differently depending on edits/updates/etc,
                // $rec['platform']['connectors'][] = $cnx;
                $rec['connectors'][] = $cnx;
            }
            $rec['connected'] = ($cred->status == 'Enabled') ? true : false;

            $combined_ids = array();
            $global = $globals->where('id',$cred->prov_id)->first();
            $reports = array();
            if ($global) {
                $inst_reports = array();
                $instCnx = $providers->where('global_id',$global->id)
                                     ->where('inst_id',$cred->inst_id)->first();
                if ($instCnx) {
                    $inst_reports = $instCnx->reports->pluck('id')->toArray();
                }
                $combined_ids = array_unique(array_merge($global->conso_reports, $inst_reports));
                $master_ids = $global->master_reports;
                $reports = $this->reportFlags($master_reports, $master_ids, $global->conso_reports, $combined_ids);
            } else {
                $reports = $this->reportFlags($master_reports,[],[],[]);
            }
            foreach ($reports as $key => $value) {
                $rec[$key] = $value;
            }
            $credentials[] = $rec;
        }

        // Return the data array
        return response()->json(['records' => $credentials, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Get and show the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // User must be able to manage the credentials
        $credential = Credential::with(['institution', 'provider'])->findOrFail($id);
        abort_unless($credential->institution->canManage(), 403);

        // Map in the connector details
        $registry = $credential->provider->default_registry();
        $credential->provider->connectors = ConnectionField::whereIn('id',$registry->connectors)->get();

        // Set next_harvest date
        if (!$credential->provider->is_active || !$credential->institution->is_active || $credential->status != 'Enabled') {
            $credential['next_harvest'] = null;
        } else {
            $mon = (date("j") < $credential->provider->day_of_month) ? date("n") : date("n")+1;
            $credential['next_harvest'] = date("d-M-Y", mktime(0,0,0,$mon,$credential->provider->day_of_month,date("Y")));
        }

        // Get 10 most recent harvests
        $harvests = HarvestLog::with(
                                  'report:id,name',
                                  'credential',
                                  'credential.institution:id,name',
                                  'credential.provider:id,name'
                              )
                              ->where('credentials_id', $id)
                              ->orderBy('updated_at', 'DESC')->limit(10)
                              ->get()->toArray();

        return view('credentials.edit', compact('credential', 'harvests'));
    }

    /**
     * Pull credentials and return JSON for the requested resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Json
     */
    public function refresh(Request $request)
    {
        $thisUser = auth()->user();

       // Validate form inputs
        $this->validate($request, ['inst_id' => 'required', 'prov_id' => 'required']);

        // User must be an admin or member-of inst to get the credentials
        if (!$thisUser->hasRole("Admin")) {
            if (!auth()->user()->hasRole("Manager") || auth()->user()->inst_id != $request->inst_id) {
                return response()->json(array('error' => 'Invalid request'));
            }
        }

       // Get (conso) provider record
        $provider = GlobalProvider::where('id', $request->prov_id)->get();

       // Get the credentials
        $data = Credential::where(['inst_id' => $request->inst_id, 'prov_id' => $request->prov_id])->first();
        $credentials = ($data) ? $data->toArray() : array('count' => 0);

       // Return credentials and url as json
        $return = array('credentials' => $credentials, 'url' => $provider->service_url());
        return response()->json($return);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['Admin','Manager']), 403);
        $input = $request->all();

        // Manager can only create credentials for their own institution
        if (!auth()->user()->hasAnyRole(['Admin']) && $input['inst_id'] != auth()->user()->inst_id) {
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

        // If there is no existing (conso) Provider definition for the global provider, create it now
        $consoProvider = Provider::where('global_id',$gp->id)->whereIn('inst_id', [1,$input['inst_id']])->first();
        if (!$consoProvider && isset($input['report_state'])) {
            $provider_data = array('name' => $gp->name, 'global_id' => $gp->id, 'is_active' => $gp->is_active,
                                   'inst_id' => $input['inst_id'], 'allow_inst_specific' => 0);
            $new_provider = Provider::create($provider_data);

            // Attach report definitions to new provider
            $global_report_ids = $gp->master_reports;
            $master_reports = Report::where('revision',5)->where('parent_id',0)->whereIn('id',$global_report_ids)
                                    ->orderBy('dorder','ASC')->get(['id','name']);
            foreach ($master_reports as $rpt) {
                if ($input['report_state'][$rpt->name]['prov_enabled']) {
                    $new_provider->reports()->attach($rpt->id);
                }
            }
        }

        // Create the new credential record and relate to the GLOBAL ID (get existing if already defined)
        $fields = array_except($input,array('report_state'));
        $credential = Credential::firstOrCreate($fields);
        $credential->load('institution', 'provider');
        $registry = $credential->provider->default_registry();
        $credential->provider->connectors = ConnectionField::whereIn('id',$registry->connectors)->get();
        // Set string for next_harvest
        if (!$credential->provider->is_active || !$credential->institution->is_active || $credential->status != 'Enabled') {
            $credential['next_harvest'] = null;
        } else {
            $mon = (date("j") < $credential->provider->day_of_month) ? date("n") : date("n")+1;
            $credential['next_harvest'] = date("d-M-Y", mktime(0,0,0,$mon,$credential->provider->day_of_month,date("Y")));
        }
        $credential['can_edit'] = true;
        return response()->json(['result' => true, 'msg' => 'Credentials successfully created', 'credential' => $credential]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function update(Request $request, $id)
    {
        // Validate form inputs
        $thisUser = auth()->user();
        $input = $request->all();

        // Get the credentials record
        $credential = Credential::with('institution','provider')->where('id',$id)->first();

        // If credential exists, confirm authorization for inst and provider
        $fields = array_except($input,array('global_id','report_state'));
        if ($credential) {
            // Confirm global provider exists
            $provider = GlobalProvider::findOrFail($credential->prov_id);
            // Ensure user is allowed to change the credentials
            $institution = Institution::findOrFail($credential->inst_id);
            if (!$institution->canManage()) {
                return response()->json(['result' => false, 'msg' => 'Not Authorized to update credential']);
            }

            // Update $credential with user inputs
            foreach ($fields as $fld => $val) {
                $credential->$fld = $val;
            }

        // if not found, try to create one
        } else {
            if (!isset($input["inst_id"]) || !isset($input["prov_id"])) {
                return response()->json(['result' => false, 'msg' => 'Missing arguments for update credentials request']);
            }
            $credential = Credential::create($fields);
            $credential->load('institution','provider');
        }

        // Get required connectors
        $registry = $credential->provider->default_registry();
        $connectors = ConnectionField::whereIn('id',$registry->connectors)->get();

        // Check/update connection fields; any null/blank required connectors get updated
        foreach ($connectors as $cnx) {
            if (is_null($credential->{$cnx->name}) || $credential->{$cnx->name} == '') {
                $credential->{$cnx->name} = '-required-';
            }
        }

        // If user requested Disabled status, save as-is
        if ($input['status'] == 'Disabled') {
            $credential->save();
        // Otherwise, update status (based on connectors and prov/inst is_active)( and save)
        } else {
            $credential->resetStatus(true);  // tell resetStatus to allow changes to disabled credentials
        }

        // Finish setting up the return object
        $credential->provider->connectors = $connectors;

        return response()->json(['result' => true, 'msg' => 'Credentials updated successfully', 'credential' => $credential]);
    }

    /**
     * Test the credentials for a given provider-institution.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function test(Request $request)
    {

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
        return response()->json(['result' => true, 'rows' => $rows, 'result' => $result]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Credential  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $credential = Credential::findOrFail($id);
        if (!$credential->institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $credential->delete();
        return response()->json(['result' => true, 'msg' => 'Credentials successfully deleted']);
    }
    /**
     * Export credentials records from the database.
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Only Admins and Managers can export institution data
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('inst' => [], 'prov' => [], 'harv_stat' => [], 'group' => 0);
        }
        $only_missing = ($request->only_missing) ? json_decode($request->only_missing, true) : true;
        // Admins have export using group filter, manager can only export their own inst
        $group = null;
        if ($thisUser->hasRole("Admin")) {
            // If group-filter is set, pull the instIDs for the group and set as the "inst" filter
            if ($filters['group'] > 0) {
                $group = InstitutionGroup::with('institutions')->where('id',$filters['group'])->first();
                if ($group) {
                    if ($group->institutions) {
                        $filters['inst'] = $group->institutions->pluck('id')->toArray();
                    }
                }
            }
            $provider_insts = array(1);   //default to consortium providers
        } else {
            $filters['inst'] = array($thisUser->inst_id);
            $provider_insts = array(1,$thisUser->inst_id);
        }

        // Get institution record(s)
        $inst_filters = null;
        if (sizeof($filters['inst']) == 0) {
            $institutions = Institution::get(['id', 'name', 'local_id', 'is_active']);
        } else {
            $institutions = Institution::whereIn('id', $filters['inst'])->get(['id', 'name', 'local_id', 'is_active']);
            $inst_filters = $filters['inst'];
        }
        if (!$institutions) {
            $msg = "Export failed : could not find requested institution(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }
        // Set name if only one inst being exported
        $inst_name = ($institutions->count() == 1) ? $institutions[0]->name : "";

        // Get provider record(s)
        $prov_filters = null;
        $global_ids = Provider::whereIn('inst_id', $provider_insts)->pluck('global_id')->toArray();
        if (sizeof($filters['prov']) == 0) {
            $providers = GlobalProvider::whereIn('id',$global_ids)->get();
        } else if (sizeof($filters['prov']) > 0) {
            $providers = GlobalProvider::whereIn('id', $filters['prov'])->whereIn('id', $global_ids)->get();
            $prov_filters = $providers->pluck('id')->toArray();
        }
        if (!$providers) {
            $msg = "Export failed : could not find requested platform(s).";
            return response()->json(['result' => false, 'msg' => $msg]);
        }

        // Set status filter
        $status_filters = (count($filters['harv_stat'])>0) ? $filters['harv_stat'] : [];
        $status_name = (count($filters['harv_stat']) == 1) ? $filters['harv_stat'][0] : "";

        // Set name if only one provider being exported
        $prov_name = ($providers->count() == 1) ? $providers[0]->name : "";

        // Get credentials
        $credentials = Credential::with('institution:id,name,local_id','provider:id,name')
                      ->when($inst_filters, function ($query, $inst_filters) {
                        return $query->whereIn('inst_id', $inst_filters);
                      })
                      ->when($prov_filters, function ($query, $prov_filters) {
                        return $query->whereIn('prov_id', $prov_filters);
                      })
                      ->when(count($status_filters)>0, function ($qry) use ($status_filters) {
                          return $qry->whereIn('status', $status_filters);
                      })
                      ->get();

        // Setup some styles arrays
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $info_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];
        $centered_style = [
          'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E8');
        $info_sheet->getStyle('A1:E8')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E8')->getAlignment()->setWrapText(true);
        $top_txt  = "The Credentials tab represents a starting place for updating or importing credentials.\n";
        $top_txt .= "The table below describes the datatype and order that the import process requires.\n\n";
        $top_txt .= "Any Import rows without an existing (institution) CC+ System ID in column-A or Local ID";
        $top_txt .= " in column-B AND a valid (platform) ID in column-C will be ignored. If values for the other";
        $top_txt .= " columns are optional and are missing, null, or invalid, they will be set to the 'Default'.\n";
        $top_txt .= "The data rows on the 'Credentials' tab provide reference values for the Platform-ID and";
        $top_txt .= " Institution-ID columns.\n\n";
        $top_txt .= "Once the data sheet is ready to import, save the sheet as a CSV and import it into CC-Plus.\n";
        $top_txt .= "Any header row or columns beyond 'F' will be ignored. Columns I-J are informational only.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->setCellValue('A10', "NOTES: ");
        $info_sheet->mergeCells('B10:E12');
        $info_sheet->getStyle('A10:B12')->applyFromArray($head_style);
        $info_sheet->getStyle('A10:B12')->getAlignment()->setWrapText(true);
        $precedence_note  = "CC+ System ID values (A) take precedence over Local ID values (B) when processing import";
        $precedence_note .= " records. If a match is found for column-A, column-B is ignored. If no match is found for";
        $precedence_note .= " (A) or (B), the row is ignored. CC+ System ID=1 is reserved for system use.";
        $info_sheet->setCellValue('B10', $precedence_note);
        $info_sheet->mergeCells('B13:E14');
        $info_sheet->getStyle('B13:E14')->applyFromArray($info_style);
        $info_sheet->getStyle('B13:E14')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing imports, be mindful about changing or overwriting existing (system) ID value(s).";
        $note_txt .= "The best approach is to add to, or modify, a full export avoid accidentally overwriting or";
        $note_txt .= " deleting existing credentials.";
        $info_sheet->setCellValue('B13', $note_txt);
        $info_sheet->getStyle('A16:E16')->applyFromArray($head_style);
        $info_sheet->setCellValue('A16', 'Column Name');
        $info_sheet->setCellValue('B16', 'Data Type');
        $info_sheet->setCellValue('C16', 'Description');
        $info_sheet->setCellValue('D16', 'Required');
        $info_sheet->setCellValue('E16', 'Default');
        $info_sheet->setCellValue('A17', 'CC+ System ID');
        $info_sheet->setCellValue('B17', 'Integer > 1');
        $info_sheet->setCellValue('C17', 'Institution ID (CC+ System ID)');
        $info_sheet->setCellValue('D17', 'Yes - If LocalID not given');
        $info_sheet->setCellValue('A18', 'LocalID');
        $info_sheet->setCellValue('B18', 'String');
        $info_sheet->setCellValue('C18', 'Local Institution identifier');
        $info_sheet->setCellValue('D18', 'Yes - If CC+ System ID not given');
        $info_sheet->setCellValue('A19', 'Platform ID');
        $info_sheet->setCellValue('B19', 'Integer > 1');
        $info_sheet->setCellValue('C19', 'Unique CC-Plus Platform ID - required');
        $info_sheet->setCellValue('D19', 'Yes');
        // $info_sheet->setCellValue('A20', 'Status');
        // $info_sheet->setCellValue('B20', 'String');
        // $info_sheet->setCellValue('C20', 'Enabled , Disabled, Suspended, or Incomplete');
        // $info_sheet->setCellValue('D20', 'No');
        // $info_sheet->setCellValue('E20', 'Enabled');
        $info_sheet->setCellValue('A20', 'Customer ID');
        $info_sheet->setCellValue('B20', 'String');
        $info_sheet->setCellValue('C20', 'COUNTER API customer ID , platform-specific');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'NULL');
        $info_sheet->setCellValue('A21', 'Requestor ID');
        $info_sheet->setCellValue('B21', 'String');
        $info_sheet->setCellValue('C21', 'COUNTER API requestor ID , platform-specific');
        $info_sheet->setCellValue('D21', 'No');
        $info_sheet->setCellValue('E21', 'NULL');
        $info_sheet->setCellValue('A22', 'API Key');
        $info_sheet->setCellValue('B22', 'String');
        $info_sheet->setCellValue('C22', 'COUNTER API API Key , platform-specific');
        $info_sheet->setCellValue('D22', 'No');
        $info_sheet->setCellValue('E22', 'NULL');
        $info_sheet->setCellValue('A23', 'LEAVE BLANK');
        $info_sheet->setCellValue('B23', 'String');
        $info_sheet->setCellValue('C23', 'Reserved for CC-Plus use');
        $info_sheet->setCellValue('D23', 'No');
        $info_sheet->setCellValue('E23', 'NULL');
        $info_sheet->mergeCells('A26:E29');
        $info_sheet->getStyle('A26:E29')->applyFromArray($head_style);
        $info_sheet->getStyle('A26:E29')->getAlignment()->setWrapText(true);
        $bot_txt = "On import, Status will default to 'Enabled'.\n";
        $bot_txt .= "Status will be set to 'Suspended' for credentials where the Institution or Platform is not active.\n";
        $bot_txt .= "Status will be set to 'Incomplete', and the field values marked as missing, if values are not";
        $bot_txt .= " supplied for fields required to connect to the platform (e.g. for customer_id, requestor_id, etc.)";
        $info_sheet->setCellValue('A26', $bot_txt);

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 23; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Put (defined) credentials into output rows array
        $data_rows = array();
        if (!$only_missing) {
            foreach ($credentials as $credential) {
                $data_rows[] = array( 'A' => $credential->inst_id, 'B' => $credential->institution->local_id,
                                      'C' => $credential->prov_id, 'D' => $credential->customer_id,
                                      'E' => $credential->requestor_id, 'F' => $credential->api_key,
                                      'G' => $credential->extra_args, 'H' => $credential->institution->name,
                                      'I' => $credential->provider->name );
            }
        }

        // Add Missing credentials to the export, get and add output data rows
        // Get ALL known Credentials inst_id <> prov_id pairs
        $existing_pairs = Credential::select('inst_id','prov_id')->get()->map(function ($credential) {
            return array($credential->inst_id, $credential->prov_id);
        })->toArray();
        foreach ($institutions as $inst) {
            // If inst is inactive, skip it
            if (!$inst->is_active) continue;
            foreach ($providers as $prov) {
                // If prov is inactive, skip it
                if (!$prov->is_active) continue;
                // If credential exists, skip it
                if (in_array(array($inst->id, $prov->id), $existing_pairs)) continue;
                // Okay, adding the data; get/set connector values
                $cnx = array();
                $connectors = $prov->connectionFields();
                foreach ($connectors as $c) {
                    $cnx[$c['name']] = ($c['required']) ? '-required-' : '';
                }
                $data_rows[] = array( 'A' => $inst->id, 'B' => $inst->local_id, 'C' => $prov->id, 'D' => $cnx['customer_id'],
                                      'E' => $cnx['requestor_id'], 'F' => $cnx['api_key'], 'G' => $cnx['extra_args'],
                                      'H' => $inst->name, 'I' => $prov->name );
            }
        }

        // Sort data rows by inst_id, then by prov_id
        $colA  = array_column($data_rows, 'A');
        $colC = array_column($data_rows, 'C');
        array_multisort($colA, SORT_ASC, $colC, SORT_ASC, $data_rows);

        // Setup a new sheet for the data rows
        $inst_sheet = $spreadsheet->createSheet();
        $active_column_cells = "D2:D" . strval(count($data_rows)+1);  // align column-D for the data sheet on center
        $inst_sheet->getStyle($active_column_cells)->applyFromArray($centered_style);
        $inst_sheet->setTitle('Credentials');
        $inst_sheet->setCellValue('A1', 'Institution ID (CC+ System ID)');
        $inst_sheet->setCellValue('B1', 'Local Institution Identifier');
        $inst_sheet->setCellValue('C1', 'Platform ID (CC+ System ID)');
        $inst_sheet->setCellValue('D1', 'Customer ID');
        $inst_sheet->setCellValue('E1', 'Requestor ID');
        $inst_sheet->setCellValue('F1', 'API Key');
        $inst_sheet->setCellValue('G1', 'LEAVE BLANK');
        $inst_sheet->setCellValue('H1', 'Institution-Name');
        $inst_sheet->setCellValue('I1', 'Platform-Name');

        // Put data rows into the sheet
        $row = 2;
        foreach ($data_rows as $data) {
            foreach ($data as $col => $val) {
                $inst_sheet->setCellValue($col.$row, $val);
            }
            $row++;
        }

        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I');
        foreach ($columns as $col) {
            $inst_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        $fileName = "CCplus";
        if (!$inst_filters && !$prov_filters && count($status_filters)==0 && is_null($group)) {
            $fileName .= "_" . session('ccp_con_key', '') . "_All";
        } else {
            if (!$inst_filters) {
                $fileName .= "_AllInstitutions";
            } else {
                if ($group) {
                    $fileName .= "_" . preg_replace('/ /', '', $group->name);
                } else {
                    $fileName .= ($inst_name == "") ? "_SomeInstitutions": "_" . preg_replace('/ /', '', $inst_name);
                }
            }
            if (!$prov_filters) {
                $fileName .= "_AllPlatforms";
            } else {
                $fileName .= ($prov_name == "") ? "_SomePlatforms": "_" . preg_replace('/ /', '', $prov_name);
            }
            if ( count($status_filters) > 0) {
                $fileName .= ($status_name == "") ? "_SomeStauses" : "_".$status_name;
            }
        }
        $fileName .= "_COUNTERCredentials.xlsx";

        // redirect output to client
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
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
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);
        $is_admin = $thisUser->hasRole('Admin');
        $usersInst = $thisUser->inst_id;

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

        // Setup arrays of "allowable" institution and provider IDs
        $institutions = Institution::get();
        if ($is_admin) {
            $inst_ids = $institutions->pluck('id')->toArray();
            $global_providers = GlobalProvider::get();
        } else {
            $inst_ids = array($usersInst);
            $_ids = Provider::whereIn('inst_id',[1,$usersInst])->pluck('global_id')->toArray();
            $global_providers = GlobalProvider::whereIn('id',$_ids)->get();
        }
        $prov_ids = $global_providers->pluck('id')->toArray();

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

            // Get the credentials' provider
            $current_prov = $global_providers->where('id',$row[2])->first();
            if (!$current_prov) {
                $skipped++;
                continue;
            }

            // Get the current institution
            $current_inst = null;
            if ($localID) {
                $current_inst = $institutions->where("local_id", $localID)->first();
            } else if (!is_null($input_inst_id)) {
                $current_inst = $institutions->where("id", $input_inst_id)->first();
            }

            // If no ID and $localID not found, skip the row
            if (!$current_inst) {
                $skipped++;
                continue;
            }

            // Process only Inst_ids and prov_ids found in the "allowed" arrays created above
            if ( !in_array($current_inst->id, $inst_ids) || !in_array($row[2], $prov_ids) ) {
                $skipped++;
                continue;
            }

            // Put credentials into an array (assumes status should be Enabled) for the update call.
            $_args = array('status' => 'Enabled', 'customer_id' => $row[3], 'requestor_id' => $row[4], 'api_key' => $row[5],
                           'extra_args' => $row[6]);

            // Mark any missing connectors
            $missing_count = 0;
            $connectors = $current_prov->connectionFields();
            foreach ($connectors as $c) {
                if ( !$c['required']) {
                    continue;
                } else {
                    if ( is_null($_args[$c['name']]) || trim($_args[$c['name']]) == '' ) {
                        $_args[$c['name']] = "-required-";
                        $missing_count++;
                    }
                }
            }

            // Override default status if credentials missing or inst/prov are inactive
            if ($current_inst->is_active && $current_prov->is_active) {
                if ( $missing_count==0 ) {
                    $_args['status'] = 'Enabled';
                } else {
                    $_args['status'] = 'Incomplete';
                    $incomplete++;
                }
            } else {
              $_args['status'] = 'Suspended';
            }

            // Update or create the credentials
            $current_credential = Credential::updateOrCreate(['inst_id' => $current_inst->id, 'prov_id' => $row[2]], $_args);
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

        // Create a return object with the credentials to be returned.
        if (!$is_admin) {
            $credentials = Credential::with('institution:id,name','provider:id,name')
                                    ->whereIn('inst_id', [ 1, $usersInst ])->get();
        } else {
            $credentials = Credential::with('institution:id,name','provider:id,name')->get();
        }
        return response()->json(['result' => true, 'msg' => $msg, 'credentials' => $credentials]);
    }

    /**
     * Export credentials records from the database.
     *
     * @param  array $filters // (Optional) - limit to an inst or provider
     */
    public function audit(Request $request)
    {
        // Only Admins and Managers can audit settings
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin','Manager']), 403);

        // Set JSON report data file path
        $report_path = null;
        $conso = Consortium::where('ccp_key',session('ccp_con_key'))->first();
        if (!$conso) {
            return response()->json(['result'=>false, 'msg'=>'Error getting current instance data']);
        }
        if (!is_null(config('ccplus.reports_path'))) {
            $report_path = config('ccplus.reports_path') . $conso->id . '/';
        } else {
            return response()->json(['result'=>false, 'msg'=>'Global Setting for reports_path is undefined - Stopping!']);
        }

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('inst' => [], 'prov' => [], 'harv_stat' => [], 'group' => 0);
        }

        // Admins have export using group filter, manager can only export their own inst
        $group = null;
        if ($thisUser->hasRole("Admin")) {
            // If group-filter is set, pull the instIDs for the group and set as the "inst" filter
            if ($filters['group'] > 0) {
                $group = InstitutionGroup::with('institutions')->where('id',$filters['group'])->first();
                if ($group) {
                    if ($group->institutions) {
                        $filters['inst'] = $group->institutions->pluck('id')->toArray();
                    }
                }
            }
            $provider_insts = array(1);   //default to consortium providers
        } else {
            $filters['inst'] = array($thisUser->inst_id);
            $provider_insts = array(1,$thisUser->inst_id);
        }

        // Finalize filters
        $inst_filters = [];
        if (count($filters['inst']) > 0) {
            $inst_filters = $filters['inst'];
        }
        $prov_filters = [];
        $global_ids = Provider::whereIn('inst_id', $provider_insts)->pluck('global_id')->toArray();
        if (count($filters['prov'])  > 0) {
            $prov_filters = GlobalProvider::whereIn('id', $filters['prov'])->whereIn('id', $global_ids)
                                          ->pluck('id')->toArray();
        }
        $status_filters = (count($filters['harv_stat'])>0) ? $filters['harv_stat'] : [];
        $status_name = (count($filters['harv_stat']) == 1) ? $filters['harv_stat'][0] : "";

        // Get all credentials with successful harvestlogs that have a rawfile set
        $credentials = Credential::with(['provider','institution',
                                      'harvestLogs' => function ($qry) {
                                          $qry->where('status','Success')->whereNotNull('rawfile')->orderBy('yearmon','DESC');
                                      }
                              ])
                              ->when(count($inst_filters)>0, function ($query) use ($inst_filters) {
                                  return $query->whereIn('inst_id', $inst_filters);
                              })
                              ->when(count($prov_filters)>0, function ($query) use ($prov_filters) {
                                  return $query->whereIn('prov_id', $prov_filters);
                              })
                              ->when(count($status_filters)>0, function ($qry) use ($status_filters) {
                                  return $qry->whereIn('status', $status_filters);
                              })
                              ->get();

        if (!$credentials) {
            return response()->json(['result'=>false, 'msg'=>'No matching credentials to audit.']);
        }

        // Set name(s) if only one inst or provider being audited
        $first_credential = $credentials->first();
        $inst_name = (count($inst_filters)==1) ? $first_credential->institution->name : "";
        $prov_name = (count($prov_filters)==1) ? $first_credential->provider->name : "";

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $credentials_sheet = $spreadsheet->getActiveSheet();
        $credentials_sheet->setTitle('COUNTER API Credentials');

        // Setup a new sheet for the data rows
        $credentials_sheet->setCellValue('A1', 'Platform Name');
        $credentials_sheet->setCellValue('B1', 'JSON Platform Value');
        $credentials_sheet->setCellValue('C1', 'JSON Item Platform Value');
        $credentials_sheet->setCellValue('D1', 'Institution Name');
        $credentials_sheet->setCellValue('E1', 'JSON Institution Value');
        $row = 2;

        // Loop over the credentials
        foreach ($credentials as $credential) {
            $credentials_sheet->setCellValue('A'.$row, $credential->provider->name);
            $credentials_sheet->setCellValue('D'.$row, $credential->institution->name);
            $json_plat = 'no-JSON-found'; // default to no-data-found
            $json_inst = 'no-JSON-found'; // default to no-data-found
            $json_item_plat = 'no-Value-found'; // default to no-data-found

            // Find the most-recent rawfile in the harvestlogs for this credential
            if ($credential->harvestLogs) {
                foreach ($credential->harvestLogs as $harv) {
                    $jsonFile = $report_path . '/' . $credential->inst_id . '/' . $credential->prov_id . '/' . $harv->rawfile;
                    if (file_exists($jsonFile)) {
                        // decrypt and decompress the file
                        $json = json_decode(bzdecompress(Crypt::decrypt(File::get($jsonFile), false)));
                        // get JSON fields
                        if (isset($json->Report_Header)) {
                           $header = $json->Report_Header;
                           $json_plat = (isset($header->Created_By)) ? $header->Created_By : "no-Created_By";
                           $json_inst = (isset($header->Institution_Name)) ? $header->Institution_Name : "no-Institution_Name";
                           if (isset($json->Report_Items) && is_array($json->Report_Items)) {
                                if (isset($json->Report_Items[0]->Platform)) {
                                    $json_item_plat = $json->Report_Items[0]->Platform;
                                }
                           }
                        } else {
                            $json_plat = 'no-Report_Header';
                            $json_inst = 'no-Report_Header';
                        }
                    }

                    // if we got values, go on to the next credential (otherwise, try another harvest)
                    if (substr($json_plat,0,3)!="no-" && substr($json_inst,0,3)!="no-") {
                        break;
                    }
                }
            }
            $credentials_sheet->setCellValue('B'.$row, $json_plat);
            $credentials_sheet->setCellValue('C'.$row, $json_item_plat);
            $credentials_sheet->setCellValue('E'.$row, $json_inst);
            $row++;
        }
        // Auto-size the columns
        $columns = array('A','B','C','D','E');
        foreach ($columns as $col) {
            $credentials_sheet->getColumnDimension($col)->setAutoSize(true);
        }

         // Give the file a meaningful filename
         $fileName = "CCplus";
         if (!$inst_filters && !$prov_filters && count($status_filters)==0 && is_null($group)) {
             $fileName .= "_" . session('ccp_con_key', '') . "_All";
         } else {
             if (!$inst_filters) {
                 $fileName .= "_AllInstitutions";
             } else {
                 if ($group) {
                     $fileName .= "_" . preg_replace('/ /', '', $group->name);
                 } else {
                     $fileName .= ($inst_name == "") ? "_SomeInstitutions": "_" . preg_replace('/ /', '', $inst_name);
                 }
             }
             if (!$prov_filters) {
                 $fileName .= "_AllPlatforms";
             } else {
                 $fileName .= ($prov_name == "") ? "_SomePlatforms": "_" . preg_replace('/ /', '', $prov_name);
             }
             if ( count($status_filters) > 0) {
                 $fileName .= ($status_name == "") ? "_SomeStauses" : "_".$status_name;
             }
         }
         $fileName .= "_COUNTERAudit.xlsx";

        // redirect output to client
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');

    }

    /**
     * Return an array of boolean flags from provider reports columns
     *
     * @param  Collection master_reports
     * @param  Array  $global_reports  (ID's)
     * @param  Array  $conso_enabled  (ID's)
     * @param  Array  $requested (ID's)
     * @return Array  $flags
     */
     private function reportFlags($master_reports, $global_reports, $conso_enabled, $requested) {
        $flags = array();
        foreach ($master_reports as $rpt) {
            $flags[$rpt->name] = array();
            $flags[$rpt->name]['available'] = (in_array($rpt->id, $global_reports)) ? true : false;
            $flags[$rpt->name]['conso'] = (in_array($rpt->id, $conso_enabled)) ? true : false;
            $flags[$rpt->name]['requested'] = (in_array($rpt->id, $requested)) ? true : false;
        }
        return $flags;
    }

}
