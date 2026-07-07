<?php

namespace App\Http\Controllers;

use App\Models\GlobalProvider;
use App\Models\Consortium;
use App\Models\Report;
use App\Models\Connection;
use App\Models\ConnectionField;
use App\Models\CounterRegistry;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DB;

class GlobalProviderController extends Controller
{
    private $masterReports;
    private $allConnectors;
    private $instanceData;

    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $equest)
    {
        global $masterReports, $allConnectors;

        // Set and confirm the user's role(s)
        $thisUser = $request->user();
        abort_unless($thisUser->isServerAdmin(), 403);

        // Get all provider records
        $gp_data = GlobalProvider::with('registries','registries.dataHost')->orderBy('name', 'ASC')->get();

        // Pull master reports and connection fields
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // get all the consortium instances
        $instances = Consortium::get();

        // setup resultMap with static values for the U/I
        $resultMap = array(
            'success' => array('refresh_text'=>'Open Registry Details', 'refresh_icon'=>'mdi-web-check', 'refresh_color'=>'blue'),
            'new'     => array('refresh_text'=>'New Platform Entry', 'refresh_icon'=>'mdi-web-plus', 'refresh_color'=>'green'),
            'orphan'  => array('refresh_text'=>'Deprecated', 'refresh_icon'=>'mdi-web-remove', 'refresh_color'=>'red'),
            'partial' => array('refresh_text'=>'Incomplete Result', 'refresh_icon'=>'mdi-web-remove', 'refresh_color'=>'orange'),
            'failed'  => array('refresh_text'=>'Last Refresh Failed', 'refresh_icon'=>'mdi-web-remove', 'refresh_color'=>'red'),
            'norefresh' => array('refresh_text'=>'Registry Refresh Disabled', 'refresh_icon'=>'mdi-web-cancel',
                                 'refresh_color'=>'black'),
            'noregistry' => array('refresh_text'=>'Registry Refresh Disabled', 'refresh_icon'=>'mdi-web-cancel',
                                  'refresh_color'=>'black'),
        );

        // Build the providers array to pass back to the datatable
        $all_releases = array();
        $providers = array();
        foreach ($gp_data as $gp) {
            $provider = array('id'=>$gp->id, 'name'=>$gp->name, 'abbrev'=>$gp->abbrev, 'day_of_month'=>$gp->day_of_month,
                              'platform_parm' => $gp->platform_parm);
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
            $provider['refreshable'] = ($gp->refreshable) ? "Active" : "Inactive";
            $provider['registry_id'] = (is_null($gp->registry_id) || $gp->registry_id=="") ? null : $gp->registry_id;
            // Handle refresh_result and set the meta-values for the U/I
            // result value will match a key in $resultMap, 'noregistry', 'norefresh', or null
            $resultKey = "";
            if ( is_null($provider['registry_id']) ) $resultKey = 'noregistry';
            if ( !$gp->refreshable ) $resultKey = 'norefresh';
            if (in_array($gp->refresh_result, array_keys($resultMap))) {
                if ($resultKey=="") $resultKey = $gp->refresh_result;
                $provider['refresh_result'] = $resultKey;
            } else {
                $resultKey = 'norefresh';
                $provider['refresh_result'] = null;
            }
            $provider['refresh_text']  = $resultMap[$resultKey]['refresh_text'];
            $provider['refresh_icon']  = $resultMap[$resultKey]['refresh_icon'];
            $provider['refresh_color'] = $resultMap[$resultKey]['refresh_color'];
            // Set release-related fields
            $provider['cur_release'] = $gp->default_release();
            $service_url = $gp->service_url();
            $provider['service_url'] = $service_url;
            $provider['registries'] = array();
            $provider['reg_releases'] = array();
            $provider['is_selected'] = 'Inactive';
            $provider['connector_state'] = array();
            foreach ($gp->registries->sortBy('release') as $registry) {
                if (!in_array($registry->release,$all_releases)) $all_releases[] = $registry->release;
                $reg = $registry->toArray();
                $reg['connector_state'] = $this->connectorState($registry->connectors);
                $reg['report_state'] = $this->reportState($registry->master_reports);
                $reg['is_selected'] = ($registry->release == $provider['cur_release']) ? 'Active' : 'Inactive';
                $reg['data_host'] = ($registry->dataHost) ? $registry->dataHost->name : "-missing-";
                if ( $reg['is_selected'] == 'Active' ) {
                    $provider['is_selected'] = 'Active';
                    $provider['connector_state'] = $reg['connector_state'];
                    $provider['report_state'] = $reg['report_state'];
                    $provider['data_host'] = $reg['data_host'];
                    // Set array of booleans for connectors (so they show as Y/N in exports - not shown in U/I)
                    foreach ($provider['connector_state'] as $con => $val) {
                        $provider[$con] = ($val) ? 'Y' : 'N';
                    }
                }
                $provider['registries'][] = $reg;
                $provider['reg_releases'][] = trim($registry->release);
            }

            // Walk all instances scan for harvests connected to this provider
            // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
            $provider['can_delete'] = true;
            $provider['instance_count'] = 0;
            $connections = array();
            foreach ($instances as $instance) {
                // Collect details from the instance for this provider
                $details = $this->instanceDetails($instance->ccp_key, $gp);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                if ($details['cnxcount'] > 0) {
                    $connections[] = array('key'=>$instance->ccp_key, 'name'=>$instance->name, 'num'=>$details['cnxcount'],
                                            'last_harvest'=>$details['last_harvest']);
                    $provider['instance_count'] += 1;
                }
            }
            $provider['can_edit'] = true;
            $provider['connections'] = $connections;
            $provider['updated'] = (is_null($gp->updated_at)) ? "" : date("Y-m-d H:i", strtotime($gp->updated_at));
            $providers[] = $provider;
        }
        // Pass master_reports and all_connectors with releases filter options for the edit form
        $filter_options = array();
        $filter_options['releases'] = $all_releases;
        $filter_options['all_connectors'] = $all_connectors;
        $filter_options['master_reports'] = $masterReports; 
        $filter_options['statuses'] = array('Active','Inactive');
        // Set refresh_result filter options to key->value pairs so they make better sense in the U/I
        $filter_options['results'] = array( array('key'=>'success','value' =>'Success'), array('key'=>'new','value' =>'New'),
            array('key'=>'orphan','value' =>'Deprecated'), array('key'=>'partial','value' =>'Incomplete'),
            array('key'=>'failed','value' =>'Failed'), array('key'=>'norefresh','value' =>'Refresh Disabled'),
            array('key'=>'noregistry','value' =>'No Registry ID'));
        // Return the data array
        return response()->json(['records' => $providers, 'options' => $filter_options], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      global $masterReports, $allConnectors;

      // Validate form inputs
      $this->validate($request, [ 'name' => 'required', 'status' => 'required', 'service_url' => 'required',
                                  'cur_release' => 'required', 'refreshable' => 'required' ]);
      $input = $request->all();

      // Create new global provider
      $provider = new GlobalProvider;
      $provider->name = $input['name'];
      $provider->is_active = ($input['status'] == 'Active') ? 1 : 0;
      $provider->refreshable = ($input['refreshable'] == 'Active') ? 1 : 0;
      $provider->refresh_result = null;
      $provider->day_of_month = (isset($input['day_of_month'])) ? min(max($input['day_of_month'],1),28) : 15;
      $provider->platform_parm = $input['platform_parm'];
      $provider->save();

      // Create a CounterRegistry record
      $registry = new CounterRegistry;
      $registry->global_id = $provider->id;
      $registry->service_url = $input['service_url'];
      $input_release = (isset($input['cur_release'])) ? trim($input['cur_release']) : "";
      $registry->release = (strlen($input_release) > 0) ? $input_release : "0";

      // Turn array of connection checkboxes into an array of IDs
      $connectors = array();
      $this->getConnectionFields();
      foreach ($allConnectors as $cnx) {
          if (!isset($input['connector_state'][$cnx->name])) continue;
          if ($input['connector_state'][$cnx->name]) {
              $connectors[] = $cnx->id;
          }
      }
      $registry->connectors = $connectors;
      // Turn array of report checkboxes into an array of IDs amd save in registry
      $master_reports = array();
      if (isset($input['report_state'])) {
          $this->getMasterReports();
          foreach ($masterReports as $rpt) {
            if (!isset($input['report_state'][$rpt->name])) continue;
            if ($input['report_state'][$rpt->name]) {
                $master_reports[] = $rpt->id;
            }
          }
      }
      $registry->master_reports = $master_reports;
      $registry->save();
      $provider->load('registries');

      // Setup Return record
      $record = array('id'=>$provider->id, 'name'=>$provider->name, 'abbrev'=>$provider->abbrev,
                      'day_of_month'=>$provider->day_of_month, 'platform_parm' => $provider->platform_parm);
      $record['can_edit'] = true;
      $record['can_delete'] = true;
      $record['cnxcount'] = 0;
      $record['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $record['refreshable'] = ($provider->refreshable) ? "Active" : "Inactive";
      $record['connector_state'] = (isset($input['connector_state'])) ? $input['connector_state'] : array();
      $record['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();
      $record['data_host'] = $provider->name;
      $record['registries'] = $provider->registries->toArray();
      $record['cur_release'] = $provider->default_release();
      $record['reg_releases'] = array($input_release);
      $record['is_selected'] = 'Active';
      $record['service_url'] = $provider->service_url();
      $record['connections'] = array();
      $record['instance_count'] = 0;
      $record['updated'] = (is_null($provider->updated_at)) ? "" : date("Y-m-d H:i", strtotime($provider->updated_at));

      return response()->json(['result' => true, 'msg' => 'Platform successfully created', 'record' => $record]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\GlobalProvider  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
      global $masterReports, $allConnectors;

      $provider = GlobalProvider::with('registries','registries.dataHost')->findOrFail($id);
      $orig_name = $provider->name;
      $orig_refreshable = $provider->refreshable;

      // Validate form inputs
      $input = $request->all();

      // If user hits toggle, it arrives as 'is_active' without a status value.
      // If so, reset input array to hold just status value
      if (!isset($input['status']) && isset($input['is_active'])) {
          $value = ($input['is_active'] == 0) ? 'Inactive' : 'Active';
          $input = array('status' => $value);
      }

      // Going INactive or making the platform NO-Refresh means we also clear the refresh_result
      $isActive = ($input['status'] == 'Active') ? 1 : 0;
      $provider->is_active = $isActive;
      if (!$isActive) {
          $provider->refresh_result = null;
      }
      if (array_key_exists('refreshable', $input)) {
          $provider->refreshable = ($input['refreshable'] == 'Active') ? 1 : 0;
      } else {
          $provider->refreshable = 0;
      }

      // If refreshable toggle changed, update refresh_result (mark refreshable=1 as success
      // to get the correct Counter api link in the U/I.)
      if ($provider->refreshable != $orig_refreshable) {
          $provider->refresh_result = ($provider->refreshable == 1) ? "success" : null;
      }

      // Pull all connection fields and master reports
      $this->getConnectionFields();
      $this->getMasterReports();

      // Update the record in the global table
      $input_name = (isset($input['name'])) ? $input['name'] : $orig_name;
      if (isset($input['name'])) {
          $provider->name = $input_name;
      }

      // Get or create the registry record and set service_url
      $input_release = (isset($input['cur_release'])) ? $input['cur_release'] : "";
      $release = (strlen(trim($input_release)) > 0) ? $input_release : "0";
      // If there is only one registry, allow input value to modify the release set for the entry
      if ($provider->registries->count() == 1) {
          $registry = $provider->registries->first();
          $registry->release = $release;
      } else {
          $registry = $provider->registries->where('release',$release)->first();
      }

      if (!$registry) {
          // Create a CounterRegistry record
          $registry = new CounterRegistry;
          $registry->global_id = $provider->id;
          $registry->service_url = (isset($input['service_url'])) ? $input['service_url'] : null;
          $registry->release = $release;
      }

      // Allow input form to modify service_url when refreshable is off
      if (isset($input['service_url']) && !$provider->refreshable) {
          $registry->service_url = $input['service_url'];
      }

      // Turn array of connection checkboxes into an array of IDs
      $new_connectors = array();
      if (array_key_exists('connector_state', $input)) {
          foreach ($allConnectors as $cnx) {
              if (!isset($input['connector_state'][$cnx->name])) continue;
              if ($input['connector_state'][$cnx->name]) {
                  $new_connectors[] = $cnx->id;
              }
          }
          $registry->connectors = $new_connectors;
      }
      // Turn array of report checkboxes into an array of IDs
      $master_reports = array();
      if (array_key_exists('report_state', $input)) {
          foreach ($masterReports as $rpt) {
              if (!isset($input['report_state'][$rpt->name])) continue;
              if ($input['report_state'][$rpt->name]) {
                  $master_reports[] = $rpt->id;
              }
          }
      }
      $registry->master_reports = $master_reports;
      $registry->save();

      // Set Provider's selected_release if the input flag is on
      $isSelected = (isset($input['is_selected'])) ? $input['is_selected'] : null;
      if ($isSelected == 'Active') {
          $provider->selected_release = trim($registry->release);
      }

      // Handle other provider values
      $provider->day_of_month = (isset($input['day_of_month'])) ? min(max($input['day_of_month'],1),28) : 15;
      $args = array('platform_parm','content_provider','registry_id');
      foreach ($args as $key) {
          if (array_key_exists($key, $input)) {
              $provider->{$key} = ($input[$key]) ? trim($input[$key]) : null;
          }
      }
      $provider->updated_at = now();
      $provider->save();
      $provider->load('registries','registries.dataHost');

      // Set connector_state by-release
      $reg_releases = array();
      foreach ($provider->registries as $registry) {
          $registry->connector_state = $this->connectorState($registry->connectors);
          $registry->report_state = $this->reportState($registry->master_reports);
          $registry->is_selected = ($registry->release == $provider->selected_release);
          $reg_releases[] = $registry->release;
      }

      // Apply changes system-wide
      $provider->applyToInstances();

      // Setup Return record
      $defaultRegistry = $provider->default_registry();
      $record = array('id'=>$provider->id, 'name'=>$provider->name, 'abbrev'=>$provider->abbrev,
                      'day_of_month'=>$provider->day_of_month, 'platform_parm' => $provider->platform_parm);
      $record['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $record['refreshable'] = ($provider->refreshable) ? "Active" : "Inactive";
      // Set release-related fields
      $record['registries'] = $provider->registries->toArray();
      $record['registry_id'] = ($defaultRegistry) ? $defaultRegistry->id : null;
      $record['cur_release'] = $provider->default_release();
      $record['is_selected'] = ($record['cur_release'] == $release) ? 'Active' : 'Inactive';
      $record['reg_releases'][] = $reg_releases;
      $record['service_url'] = ($defaultRegistry) ? $defaultRegistry->service_url : $provider->service_url();
      $record['data_host'] = "-missing-";
      if ($defaultRegistry) {
          $record['data_host'] = ($defaultRegistry->dataHost) ? $defaultRegistry->dataHost->name : "-missing-";
      }
      $record['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();
      $record['connector_state'] = (isset($input['connector_state'])) ? $input['connector_state'] : array();

      // Check all instances scan for harvests connected to this provider to set the can_delete flag
      $record['can_edit'] = true;
      $record['can_delete'] = true;
      $record['instance_count'] = 0;
      $connections = array();
      $instances = Consortium::get();
      foreach ($instances as $instance) {
          // Collect details from the instance for this provider
          $details = $this->instanceDetails($instance->ccp_key, $provider);
          if ($details['harvest_count'] > 0) {
              $record['can_delete'] = false;
          }
          if ($details['cnxcount'] > 0) {
              $connections[] = array('key'=>$instance->ccp_key, 'name'=>$instance->name, 'num'=>$details['cnxcount'],
                                      'last_harvest'=>$details['last_harvest']);
              $record['instance_count'] += 1;
          }
      }
      $record['connections'] = $connections;
      $record['updated'] = (is_null($provider->updated_at)) ? "" : date("Y-m-d H:i", strtotime($provider->updated_at));

      return response()->json(['result' => true, 'msg' => 'Platform settings successfully updated',
                               'record' => $record]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\GlobalProvider  $id
     */
    public function destroy($id)
    {
        $globalProvider = GlobalProvider::findOrFail($id);

        // Loop through all consortia instances and delete related connections
        $instances = Consortium::get();
        $keepDB  = config('database.connections.consodb.database');
        foreach ($instances as $instance) {
            // switch the database connection
            config(['database.connections.consodb.database' => "ccplus_" . $instance->ccp_key]);
            try {
                DB::reconnect('consodb');
            } catch (\Exception $e) {
                return response()->json(['result' => 'Error connecting to database for the ' . $instance->name . ' instance!']);
            }

            try {
                Connection::where('global_id',$id)->delete();
            } catch (\Exception $ex) {
                return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
            }
        }
        // Restore the database habdle
        config(['database.connections.consodb.database' => $keepDB]);

        // Delete the global entry
        try {
            $globalProvider->delete();
        } catch (\Exception $ex) {
            return response()->json(['result' => false, 'msg' => $ex->getMessage()]);
        }

        return response()->json(['result' => true, 'msg' => 'Global Platform successfully deleted']);
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
        if (!$thisUser->isServerAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }

        // Validate form inputs
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();

        // Unrecognized action - NOTE - Registry Refresh happens in the CounterRegistryController... not here
        if (!in_array($input['action'],['Set Active','Set Inactive','Delete'])) {
            return response()->json(['result' => false, 'msg' => 'Unrecognized bulk action requested'], 200);
        }

        // Get platforms
        $gp_data = GlobalProvider::whereIn('id',$input['ids'])->get();

        // Update providers one-at-a-time; we need to apply changes to instances as we go.
        $successIds = array();
        $skippedIds = array();
        $failureIds = array();
        $new_value = ($input['action'] == 'Set Active') ? 1 : 0;
        foreach ($gp_data as $global) {

            // Update is_active value
            if ($input['action'] == 'Set Active' || $input['action'] == 'Set Inactive') {
                if ($global->is_active == $new_value) {  // skip if already has the new value
                    $global->update(['is_active' => $new_value]);
                    $successIds[] = $global->id;
                } else {
                    $skippedIds[] = $global->id;
                }
            // Delete the global entry
            } else if ($input['action'] == 'Delete') {
                try {
                    $global->delete();
                    $successIds[] = $global->id;
                } catch (\Exception $ex) {
                    $failureIds[] = $global->id;
                }
            }
            // Apply changes system-wide
            $global->applyToInstances();
        }
        $msg = '';
        if (count($skippedIds)> 0 || count($failureIds)>0) {
            $msg  = count($successIds).' records successfully updated';
            $msg .= (count($skippedIds>0)) ? '; '.count($skippedIds).' were skipped' : '';
            $msg .= (count($failureIds>0)) ? '; '.count($failureIds).' failed' : '';
        }
        return response()->json(['result' => true, 'msg' => $msg, 'affectedIds' => $successIds], 200);
    }

    /**
     * GET route to pull platform data for exporting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        global $masterReports, $allConnectors;

        // Set and confirm the user's role(s)
        $thisUser = auth()->user();
        abort_unless($thisUser->isServerAdmin(), 403);

        // Get all provider records
        $gp_data = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();

        // Pull master reports and connection fields
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // get all the consortium instances
        $instances = Consortium::get();

        // Build the providers array to pass back to the datatable
        $all_releases = array();
        $providers = array();
        foreach ($gp_data as $gp) {
            $provider = array('registry_id'=>null, 'name'=>$gp->name, 'day_of_month'=>$gp->day_of_month,
                              'platform_parm' => $gp->platform_parm);
            $provider['registry_id'] = (is_null($gp->registry_id) || $gp->registry_id=="") ? null : $gp->registry_id;
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
            $provider['refreshable'] = ($gp->refreshable) ? "Active" : "Inactive";
            foreach ($gp->registries->sortBy('release') as $registry) {
                $provider['release'] = trim($registry->release);
                $provider['service_url'] = $registry->service_url;
                // Set Y/N flags for connectors
                $connector_state = $this->connectorState($registry->connectors);
                foreach ($connector_state as $con => $val) {
                    $provider[$con] = ($val) ? 'Y' : 'N';
                }
                // Set Y/N flags for reports
                $report_state = $this->reportState($registry->master_reports);
                foreach ($report_state as $rpt => $val) {
                    $provider[$rpt] = ($val) ? 'Y' : 'N';
                }
                $providers[] = $provider;
            }
        }
        // Return the data array
        return response()->json(['records' => $providers], 200);
    }

    /**
     * Import providers from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        global $masterReports, $allConnectors;

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (count($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing providers, connection fields and master_reports
        $global_providers = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();
        $this->getMasterReports();
        $this->getConnectionFields();

        // Set defaults for any columns with unset/missing values
        //  0 : Registry ID  ,  1 : Platform Name  ,  2 : COUNTER Release  ,  3 : Status  ,  4 : Service URL
        //  5 : Refreshable  ,  6 : Harvest Day    ,  7 : PR  ,  8 : DR  ,  9 : TR  ,  10 : IR
        // 11 : Customer ID  , 12 : Requestor ID   , 13 : API Key  ,  14 : Platform Parameter        
        $col_defaults = array( 3=>'Active', 4=>'', 5=>'Y', 6=>15, 7=>'Y', 8=>'N', 9=>'N', 10=>'N',
                              11=>'Y', 12=>'N', 13=>'N', 14=>null );
        // Map of report-COLUMN indeces to master_report ID's (reportID => COL) and defaults
        $rpt_columns = array(3=>7, 2=>8, 1=>9, 4=>10);
        // Map of connector-COLUMN indeces to connection_field IDs (fieldID => COL)
        $cnx_columns = array(1=>11, 2=>12, 3=>13, 4=>15);

        // Process the input rows
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        foreach ($rows as $row) {

            // At least first 5 columns required (skip not-counted)
            if (count($row) < 5)  continue;
            // Ignore header row (skip not-counted)
            if ($row[0] == 'Registry ID' || $row[1] == 'Platform Name') continue;

            // Set RegistryID, Name, Release, and service_url in variables
            $_regid = trim($row[0]);
            $_name = trim($row[1]);
            $_release = trim($row[2]);
            $_service_url = trim($row[4]);

            // RegistryID -or Name, Release, and service_url are required
            if ( ($_regid == "" && $_name == "") || $_release == "" || $_service_url == "") {
                $prov_skipped++;
                continue;
            }

            // Update/Add the provider data/settings
            // Check Registry-ID and name columns for silliness or errors
            $platform = null;
            if ( strlen($_regid) > 0 ) {
                $platform = $global_providers->where("registry_id", $_regid)->first();  // Look for match on ID
            }
            if ($platform) {      // found matching Registry-ID
                if (strlen($_name) < 1) {       // If import-name empty, use current value
                    $_name = trim($platform->name);
                } else {                        // trap changing a name to a name that already exists
                    $existing_prov = $global_providers->where("name", $_name)->first();
                    if ($existing_prov) {
                        $_name = trim($platform->name);     // override, use current - no change
                    }
                }
            } else {        // Registry-ID not found, try to find by name
                $platform = $global_providers->where("name", $_name)->first();
                if ($platform) {
                    $_name = trim($platform->name);
                }
            }

            // If no name or (no registry and no service_url), skip the row
            if (strlen($_name)<1 || (strlen($_regid)==0 && strlen($_service_url)==0)) {
                $prov_skipped++;
                continue;
            }

            // Enforce defaults and set any missing values
            foreach ($col_defaults as $_col => $_val) {
                if (!isset($row[$_col])) {
                    $row[$_col] = $_val;
                } else if (strlen(trim($row[$_col])) < 1) {
                    $row[$_col] = $_val;
                }
            }
            $_active = ($row[3] == 'Active') ? 1 : 0;
            $_day = (strlen(trim($row[5])) == 0) ? 15 : trim($row[5]);
            // Keep $_day sane; >28 means *some* harvests will never be started
            if ($_day<0 || $_day>28) $_day = 15;

            // Setup provider update data as an array
            $_prov = array('name' => $_name, 'is_active' => $_active, 'day_of_month' => $_day);

            // Set platform_parm
            if (isset($row[14])) {
                $_prov['platform_parm'] = trim($row[14]);
            }

            // Update or create the GlobalProvider record and Registry record
            if ($platform) {      // Update
                $_prov['id'] = $platform->id;
                $platform->update($_prov);
                $prov_updated++;
            } else {                 // Create
                // Set as not-refreshable if no Registry-ID given
                if ( strlen($_regid) == 0 ) {
                    $_prov['refreshable'] = 0;
                }
                $platform = GlobalProvider::create($_prov);
                $global_providers->push($platform);
                $prov_created++;
            }

            // Setup reports for the registry record
            $reports = array();
            foreach ($rpt_columns as $id => $col) {
                if ($row[$col] == 'Y') $reports[] = $id;
            }

            // Setup connectors for the registry record
            $connectors = array();
            foreach ($cnx_columns as $id => $col) {
                if (!isset($row[$col])) continue;   // extra_args is pointing at 15... for now
                if ($row[$col] == 'Y') $connectors[] = $id;
            }

            // Find/Update/Create the counter_registry record based on "release" value
            $registry = $platform->registries->where('release',$_release)->first();

            // Update existing entry
            if ($registry) {
                $registry->service_url = $_service_url;
                $registry->master_reports = $reports;
                $registry->connectors = $connectors;
                $registry->save();

            // Create a registry entry (this is either a new platform or new registry entry for an existing platform)
            } else {
                $_reg = array('global_id' => $platform->id, 'release' => $_release, 'service_url' => $_service_url,
                              'master_reports' => $reports, 'connectors' => $connectors);
                $registry = CounterRegistry::create($_reg);
            }
        }

        // return the current full list of providers with a success message
        $detail = "";
        $detail .= ($prov_updated > 0) ? $prov_updated . " updated" : "";
        if ($prov_created > 0) {
            $detail .= ($detail != "") ? ", " . $prov_created . " added" : $prov_created . " added";
        }
        if ($prov_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $prov_skipped . " skipped" : $prov_skipped . " skipped";
        }
        $msg  = 'Import successful, Platforms : ' . $detail;

        return response()->json(['result' => true, 'msg' => $msg]);
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
     * Return harvest_count, connection count, and last_harvest for a global provider in a given instance
     *
     * @param  String  $instanceKey
     * @param  GlobalProvider  $gp
     * @return Array  $details
     */
    private function instanceDetails($instanceKey, $gp) {

        // Query the tables directly for what we're after, starting with connection count
        $qry  = "Select count(*) as num, max(last_harvest) as last from ccplus_" . $instanceKey . ".credentials ";
        $qry .= "where prov_id = " . $gp->id;
        $result = DB::select($qry);
        $cnxcount = $result[0]->num;
        $last = $result[0]->last;

        // Get the number of harvests
        $qry .= " and last_harvest is not null";
        $result = DB::select($qry);
        $count = $result[0]->num;

        // return the numbers
        return array('harvest_count' => $count , 'cnxcount' => $cnxcount, 'last_harvest' => $last);
    }
}
