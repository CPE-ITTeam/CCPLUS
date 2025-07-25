<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Consortium;
use App\Report;
use App\GlobalSetting;
use App\GlobalProvider;
use App\ConnectionField;
use DB;
use GuzzleHttp\Client;

class GlobalAdminController extends Controller
{
    private $masterReports;
    private $allConnectors;

    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Index method for GlobalAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        global $masterReports, $allConnectors;

        // Get consortia, master reports, and connection fields
        $consortia = Consortium::orderby('name')->get();
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // Get global settings, minus the server admin credentials
        $skip_vars = array('server_admin','server_admin_pass','max_name_length');
        $settings = GlobalSetting::whereNotIn('name',$skip_vars)->get()->toArray();

        // Get global providers and preserve the current instance database setting
        $gp_data = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();

        // Build the providers array to pass onto the view
        $providers = array();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";

            // Set release-related fields
            $provider['registries'] = array();
            $provider['releases'] = array();
            $provider['release'] = $gp->default_release();
            $provider['service_url'] = $gp->service_url();
            foreach ($gp->registries->sortBy('release') as $registry) {
                $reg = $registry->toArray();
                $reg['connector_state'] = $this->connectorState($registry->connectors);
                $reg['is_selected'] = ($registry->release == $provider['release']);
                $provider['registries'][] = $reg;
                $provider['releases'][] = trim($registry->release);
            }
            if (is_null($gp->selected_release)) {
                $provider['selected_release'] = $provider['release'];
            }

            // Build arrays of booleans for connecion fields and reports for the U/I chackboxes
            $provider['report_state'] = $this->reportState($gp->master_reports);

            // Walk all instances scan for harvests connected to this provider
            // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
            $provider['can_delete'] = true;
            $provider['connection_count'] = 0;
            $connections = array();
            foreach ($consortia as $instance) {
                // Collect details from the instance for this provider
                $details = $this->instanceDetails($instance->ccp_key, $gp);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                if ($details['connections'] > 0) {
                    $connections[] = array('key'=>$instance->ccp_key, 'name'=>$instance->name, 'num'=>$details['connections'],
                    'last_harvest'=>$details['last_harvest']);
                    $provider['connection_count'] += 1;
                }
            }
            $parsedUrl = parse_url($provider['service_url']);
            $provider['host_domain'] = (isset($parsedUrl['host'])) ? $parsedUrl['host'] : "-missing-";
            $provider['connections'] = $connections;
            $provider['updated'] = (is_null($gp->updated_at)) ? "" : date("Y-m-d H:i", strtotime($gp->updated_at));
            $providers[] = $provider;
        }

        $filters = array('stat' => '', 'refresh' => '');
        return view('globaladmin.home', compact('consortia','settings','providers','filters','masterReports','all_connectors'));
    }

    /**
     * Change instnance method for GlobalAdmin Controller
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  String $key    // Consortium Instance database key
     * @param  String $page   // Page to redirect to
     */
    public function changeInstance(Request $request, $key, $page)
    {
        // Get input arguments from the request
        $consortium = Consortium::where('ccp_key',$key)->first();
        if (!$consortium) {
            return response()->json(['result' => 'Instance not found!']);
        }

        // Update the active configuration and the session to use the new key
        $conso_db = "ccplus_" . $key;
        config(['database.connections.consodb.database' => $conso_db]);
        session(['ccp_con_key' => $key]);
        try {
            DB::reconnect('consodb');
        } catch (\Exception $e) {
            return response()->json(['result' => 'Error decoding input!']);
        }
        if ($page == 'home') {
            return redirect()->route('admin.home');
        } else if ($page == 'harvests' || $page == 'reports') {
            return redirect()->route($page . '.index');
        } else {
            return redirect()->route('home');
        }
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getMasterReports() {
        global $masterReports;
        $masterReports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getConnectionFields() {
        global $allConnectors;
        $allConnectors = ConnectionField::get();
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @return Array  $report-state
     */
    private function reportState($reports) {
        global $masterReports;
        $rpt_state = array();
        foreach ($masterReports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  Array  $connectors
     * @return Array  $connector-state
     */
    private function connectorState($connectors) {
      global $allConnectors;
      $cnx_state = array();
      foreach ($allConnectors as $fld) {
          $cnx_state[$fld->name] = (in_array($fld->id, $connectors)) ? true : false;
      }
      return $cnx_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  String  $instanceKey
     * @param  GlobalProvider  $gp
     * @return Array  $details
     */
    private function instanceDetails($instanceKey, $gp) {

        // Query the tables directly for what we're after, starting with connection count
        $qry  = "Select count(*) as num, max(last_harvest) as last from ccplus_" . $instanceKey . ".sushisettings ";
        $qry .= "where prov_id = " . $gp->id;

        $result = DB::select($qry);
        $connections = $result[0]->num;
        $last = $result[0]->last;

        // Get the number of harvests
        $qry .= " and last_harvest is not null";
        $result = DB::select($qry);
        $count = $result[0]->num;

        // return the numbers
        return array('harvest_count' => $count , 'connections' => $connections, 'last_harvest' => $last);
    }

    public function ipTest() {
        $options = [
            'http_errors' => false,
            'headers' => [ 'Accept' => 'application/json', 'User-Agent' => "Mozilla/5.0 (CC-Plus custom) Firefox/80.0" ]
        ];
        $client = new Client();   //GuzzleHttp\Client
               // Make the request and convert into JSON
        try {
            $response = $client->request('GET', "https://icanhazip.com",$options);
            dd($response->getBody()->getContents());
        } catch (\Exception $e) {
            dd('Error: '.$e->getMessage());
        }    
    }
}
