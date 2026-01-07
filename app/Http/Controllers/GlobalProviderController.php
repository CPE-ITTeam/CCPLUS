<?php

namespace App\Http\Controllers;

use App\Models\GlobalProvider;
use App\Models\Consortium;
use App\Models\Report;
use App\Models\Provider;
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
     * @param  String $role    // 'admin' or 'viewer'
     * @return \Illuminate\Http\Response
     */
    public function index($role)
    {
        global $masterReports, $allConnectors;

        // Set and confirm the role returning data for
        $thisUser = auth()->user();
        $type = ($role=='admin') ? 'admin' : 'viewer';
        abort_unless($type=='viewer' || $thisUser->hasAnyRole(['Admin']), 403);

        // Set institution limits based on users's role(s) and what's been requested
        $_insts = ($type == 'admin') ? $thisUser->adminInsts() : $thisUser->viewerInsts();
        $limit_to_insts = ($_insts == [1]) ? [] : $_insts;

        // Pull globalProvider IDs based on the consortium providers defined for institutions in
        // $limit_to_insts. Admins and Viewers both get consortium-wide providers (where inst_id=1)
        $globalIDs = Provider::where('inst_id',1)
                             ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                                return $qry->orWhereIn('inst_id',$limit_to_insts);
                             })
                             ->select('global_id')->distinct()->pluck('global_id')->toArray();

        // Pull master reports and connection fields regardless of JSON flag
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // Get provider records and filter as-needed
        $gp_data = GlobalProvider::whereIn('id', $globalIDs)->orderBy('name', 'ASC')->get();

        // get all the consortium instances and preserve the current instance database setting
        $instances = Consortium::get();

        // Build the providers array to pass back to the datatable
        $providers = array();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
            $provider['registry_id'] = (is_null($gp->registry_id) || $gp->registry_id=="") ? null : $gp->registry_id;

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
            foreach ($instances as $instance) {
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

        // Return the data array
        return response()->json(['records' => $providers], 200);
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
      $this->validate($request, [ 'name' => 'required', 'is_active' => 'required', 'service_url' => 'required',
                                  'release' => 'required' ]);
      $input = $request->all();

      // Create new global provider
      $provider = new GlobalProvider;
      $provider->name = $input['name'];
      $provider->is_active = $input['is_active'];
      $provider->refreshable = $input['refreshable'];
      $provider->refresh_result = null;
      $provider->day_of_month = (isset($input['day_of_month'])) ? $input['day_of_month'] : 15;
      $provider->platform_parm = $input['platform_parm'];

      // Turn array of report checkboxes into an array of IDs
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
      $provider->master_reports = $master_reports;
      $provider->save();

      // Create a CounterRegistry record
      $registry = new CounterRegistry;
      $registry->global_id = $provider->id;
      $registry->service_url = $input['service_url'];
      $input_release = (isset($input['release'])) ? $input['release'] : "";
      $registry->release = (strlen(trim($input_release)) > 0) ? $input_release : "0";

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
      $registry->save();

      // Build return object to match what index() shows
      $provider['can_delete'] = true;
      $provider['connection_count'] = 0;
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['connector_state'] = $input['connector_state'];
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();
      $provider['service_url'] = $provider->service_url();
      $parsedUrl = parse_url($provider->service_url());
      $provider['host_domain'] = (isset($parsedUrl['host'])) ? $parsedUrl['host'] : "-missing-";    

      return response()->json(['result' => true, 'msg' => 'Platform successfully created',
                               'provider' => $provider]);
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

      $provider = GlobalProvider::with('registries')->findOrFail($id);
      $orig_name = $provider->name;
      $orig_refreshable = $provider->refreshable;

      // Validate form inputs
      $this->validate($request, [ 'is_active' => 'required' ]);
      $input = $request->all();

      // Going INactive or making the platform NO-Refresh means we also clear the refresh_result
      $isActive = ($input['is_active']) ? 1 : 0;
      $provider->is_active = $isActive;
      if (!$isActive) {
          $provider->refresh_result = null;
      }
      if (array_key_exists('refreshable', $input)) {
          $provider->refreshable = ($input['refreshable']) ? 1 : 0;
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
      $input_release = (isset($input['release'])) ? $input['release'] : "";
      $release = (strlen(trim($input_release)) > 0) ? $input_release : "0";
      // If there is only one registry, allow input value to modify the release set for the entry
      if ($provider->registries->count() == 1) {
          $registry = $provider->registries->first();
          $registry->release = $release;
      } else {
          $registry = $provider->registries->where('release',$release)->first();
      }
      if ($registry) {
          $registry->service_url = (isset($input['service_url'])) ? $input['service_url'] : null;
      } else {
          // Create a CounterRegistry record
          $registry = new CounterRegistry;
          $registry->global_id = $provider->id;
          $registry->service_url = (isset($input['service_url'])) ? $input['service_url'] : null;
          $registry->release = $release;
      }
      if ($registry) {
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
      }
      $registry->save();

      // Set Provider's selected_release if the input flag is on
      $isSelected = (isset($input['is_selected'])) ? $input['is_selected'] : 0;
      if ($isSelected) {
          $provider->selected_release = trim($registry->release);
      }

      // Handle other provider values
      $provider->day_of_month = (isset($input['day_of_month'])) ? $input['day_of_month'] : 15;
      $args = array('platform_parm','content_provider','registry_id');
      foreach ($args as $key) {
          if (array_key_exists($key, $input)) {
              $provider->{$key} = ($input[$key]) ? trim($input[$key]) : null;
          }
      }

      // Turn array of report checkboxes into an array of IDs
      if (array_key_exists('report_state', $input)) {
          $master_reports = array();
          foreach ($masterReports as $rpt) {
              if (!isset($input['report_state'][$rpt->name])) continue;
              if ($input['report_state'][$rpt->name]) {
                  $master_reports[] = $rpt->id;
              }
          }
          $provider->master_reports = $master_reports;
      }
      $provider->updated_at = now();
      $provider->save();
      $provider->load('registries');
      $provider['status'] = ($provider->is_active) ? "Active" : "Inactive";
      $provider['report_state'] = (isset($input['report_state'])) ? $input['report_state'] : array();
      // Set connector_state by-release
      foreach ($provider->registries as $registry) {
          $registry->connector_state = $this->connectorState($registry->connectors);
          $registry->is_selected = ($registry->release == $provider->selected_release);
      }
      $provider['release'] = $release;
      $provider['service_url'] = ($registry) ? $registry->service_url : $provider->service_url();
      // Set connection field labels in an array for the datatable display
      $provider['updated'] = (is_null($provider->updated_at)) ? null : date("Y-m-d H:i", strtotime($provider->updated_at));

      // Apply changes system-wide
      $provider->appyToInstances();

      return response()->json(['result' => true, 'msg' => 'Global Platform settings successfully updated',
                               'provider' => $provider]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\GlobalProvider  $id
     */
    public function destroy($id)
    {
        $globalProvider = GlobalProvider::findOrFail($id);

        // Loop through all consortia instances and delete from the providers tables
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
                Provider::where('global_id',$id)->delete();
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
     * Export provider records from the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        global $masterReports, $allConnectors;

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('stat' => '', 'refresh' => '');
        }

        // Prep variables for use in querying
        $filter_stat = null;
        if ($filters['stat'] != 'ALL' && $filters['stat'] != '') {
            $filter_stat = ($filters['stat'] == 'Active') ? 1 : 0;
        }
        $filter_refresh = null;
        $filter_no_registryID = null;
        $filter_not_refreshable = null;
        if ($filters['refresh'] == 'Refresh Disabled') {
            $filter_not_refreshable = true;
        } else if ($filters['refresh'] == 'No Registry ID') {
            $filter_no_registryID = true;
        } else if ($filters['refresh'] == 'Deprecated') {
            $filter_refresh = 'orphan';
        } else if ($filters['refresh'] != 'ALL') {
            $filter_refresh = strtolower($filters['refresh']);
        }

        // Admins get all providers
        $global_providers = GlobalProvider::with('registries')
                                          ->when($filter_stat!=null, function ($query, $filter_stat) {
                                            return $query->where('is_active', $filter_stat);
                                          })
                                          ->when($filter_refresh, function ($qry) use ($filter_refresh) {
                                            return $qry->where('refresh_result',$filter_refresh);
                                          })
                                          ->when($filter_not_refreshable, function ($qry) {
                                            return $qry->where('refreshable',0);
                                          })
                                          ->when($filter_no_registryID, function ($qry) {
                                            return $qry->whereNull('registry_id')->orWhere('registry_id',"");
                                          })
                                          ->orderBy('name', 'ASC')->get();
  
        // get connection fields and master reports
        $this->getMasterReports();
        $this->getConnectionFields();

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
        $bold_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];
        $centered_style = [
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,],
        ];
        $outline_style = [
            'borders' => [ 'outline' => [ 'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,],],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        for ($row=1; $row<7; $row++) {
            $info_sheet->mergeCells("A" . $row . ":H" . $row);
        }
        $info_sheet->setCellValue('A2',"  * The Platforms tab represents a starting place for updating or importing settings.");

        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $approach = $richText->createTextRun("  * The recommended approach is to add to, or modify, a previously run full export.");
        $approach->getFont()
                 ->setColor( new \PhpOffice\PhpSpreadsheet\Style\Color( \PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED ) );
        $info_sheet->setCellValue('A3', $richText);
        $_txt = "  * Only additions and updates are supported. Imports will not remove existing platforms.";
        $info_sheet->setCellValue('A4', $_txt);
        $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $richText->createText("  * Once updates to the Platforms tab are complete, save the sheet as a ");
        $saving = $richText->createTextRun("CSV UTF-8");
        $saving->getFont()->setBold(true);
        $richText->createText(" file and import it into CC-Plus.");
        $info_sheet->setCellValue('A5', $richText);
        $info_sheet->getStyle('A1:H6')->applyFromArray($outline_style);
        for ($row=7; $row<13; $row++) {
            $info_sheet->mergeCells("A" . $row . ":H" . $row);
        }
        $info_sheet->setCellValue( 'A8',"  * The table below describes the data type and order that the import expects. Any rows");
        $info_sheet->setCellValue( 'A9',"  * without an ID value in column A or a name in column B are ignored; columns C-D (Release");
        $info_sheet->setCellValue('A10',"  * and Service URL) are also required. If values are missing or invalid for columns D-N,");
        $info_sheet->setCellValue('A11',"  * they will be set to the default. Any header row or columns beyond N will be ignored.");
        $info_sheet->getStyle('A7:H12')->applyFromArray($outline_style);
        $info_sheet->getStyle('A14:H14')->applyFromArray($head_style);
        $info_sheet->setCellValue('A14', 'COL');
        $info_sheet->setCellValue('B14', 'Column Name');
        $info_sheet->setCellValue('C14', 'Required');
        $info_sheet->setCellValue('D14', 'Data Type');
        $info_sheet->setCellValue('E14', 'Valid Values');
        $info_sheet->setCellValue('F14', 'Description');
        $info_sheet->setCellValue('G14', 'Default if empty');
        $info_sheet->setCellValue('H14', 'Notes');
        $info_sheet->getStyle('A15:A28')->applyFromArray($centered_style);
        $info_sheet->getStyle('C15:C18')->applyFromArray($bold_style);
        $info_sheet->getStyle('E15:E28')->applyFromArray($centered_style);
        $info_sheet->getStyle('F15:F18')->applyFromArray($head_style);
        $info_sheet->getStyle('G15:G28')->applyFromArray($centered_style);
        $info_sheet->setCellValue('A15', 'A');
        $info_sheet->setCellValue('B15', 'Id');
        $info_sheet->setCellValue('C15', 'No');
        $info_sheet->setCellValue('D15', 'Integer');
        $info_sheet->setCellValue('E15', '');
        $info_sheet->setCellValue('F15', 'COUNTER Registry ID');
        $info_sheet->setCellValue('G15', 'Null');
        $info_sheet->setCellValue('H15', 'If omitted, Platform name used for matching');
        $info_sheet->setCellValue('A16', 'B');
        $info_sheet->setCellValue('B16', 'Name');
        $info_sheet->setCellValue('C16', 'Yes');
        $info_sheet->setCellValue('D16', 'String');
        $info_sheet->setCellValue('E16', '');
        $info_sheet->setCellValue('F16', 'Platform name');
        $info_sheet->setCellValue('G16', '');
        $info_sheet->setCellValue('H16', '');
        $info_sheet->setCellValue('A17', 'C');
        $info_sheet->setCellValue('B17', 'Release');
        $info_sheet->setCellValue('C17', 'Yes');
        $info_sheet->setCellValue('D17', 'String');
        $info_sheet->setCellValue('E17', '5 , 5.1, etc.');
        $info_sheet->setCellValue('F17', 'COUNTER release');
        $info_sheet->setCellValue('G17', '');
        $info_sheet->setCellValue('H17', '');
        $info_sheet->setCellValue('A18', 'D');
        $info_sheet->setCellValue('B18', 'Server URL');
        $info_sheet->setCellValue('C18', 'Yes');
        $info_sheet->setCellValue('D18', 'String');
        $info_sheet->setCellValue('E18', 'Valid URL');
        $info_sheet->setCellValue('F18', 'URL for Platform COUNTER service');
        $info_sheet->setCellValue('G18', '');
        $info_sheet->setCellValue('H18', '');
        $info_sheet->setCellValue('A19', 'E');
        $info_sheet->setCellValue('B19', 'Active');
        $info_sheet->setCellValue('C19', '');
        $info_sheet->setCellValue('D19', 'String');
        $info_sheet->setCellValue('E19', 'Y or N');
        $info_sheet->setCellValue('F19', 'Make the platform active?');
        $info_sheet->setCellValue('G19', 'Y');
        $info_sheet->setCellValue('H19', '');
        $info_sheet->setCellValue('A20', 'F');
        $info_sheet->setCellValue('B20', 'Harvest Day');
        $info_sheet->setCellValue('C20', '');
        $info_sheet->setCellValue('D20', 'Integer');
        $info_sheet->setCellValue('E20', '1-28');
        $info_sheet->setCellValue('F20', 'Day of the month to harvest reports ');
        $info_sheet->setCellValue('G20', '15');
        $info_sheet->setCellValue('H20', '');
        $info_sheet->setCellValue('A21', 'G');
        $info_sheet->setCellValue('B21', 'PR');
        $info_sheet->setCellValue('C21', '');
        $info_sheet->setCellValue('D21', 'String');
        $info_sheet->setCellValue('E21', 'Y or N');
        $info_sheet->setCellValue('F21', 'Platform supplies PR reports?');
        $info_sheet->setCellValue('G21', 'Y');
        $info_sheet->setCellValue('H21', '');
        $info_sheet->setCellValue('A22', 'H');
        $info_sheet->setCellValue('B22', 'DR');
        $info_sheet->setCellValue('C22', '');
        $info_sheet->setCellValue('D22', 'String');
        $info_sheet->setCellValue('E22', 'Y or N');
        $info_sheet->setCellValue('F22', 'Platform supplies DR reports?');
        $info_sheet->setCellValue('G22', 'N');
        $info_sheet->setCellValue('H22', '');
        $info_sheet->setCellValue('A23', 'I');
        $info_sheet->setCellValue('B23', 'TR');
        $info_sheet->setCellValue('C23', '');
        $info_sheet->setCellValue('D23', 'String');
        $info_sheet->setCellValue('E23', 'Y or N');
        $info_sheet->setCellValue('F23', 'Platform supplies TR reports?');
        $info_sheet->setCellValue('G23', 'N');
        $info_sheet->setCellValue('H23', '');
        $info_sheet->setCellValue('A24', 'J');
        $info_sheet->setCellValue('B24', 'IR');
        $info_sheet->setCellValue('C24', '');
        $info_sheet->setCellValue('D24', 'String');
        $info_sheet->setCellValue('E24', 'Y or N');
        $info_sheet->setCellValue('F24', 'Platform supplies IR reports?');
        $info_sheet->setCellValue('G24', 'N');
        $info_sheet->setCellValue('H24', '');
        $info_sheet->setCellValue('A25', 'K');
        $info_sheet->setCellValue('B25', 'Customer ID');
        $info_sheet->setCellValue('C25', '');
        $info_sheet->setCellValue('D25', 'String');
        $info_sheet->setCellValue('E25', 'Y or N');
        $info_sheet->setCellValue('F25', 'Customer ID is required for COUNTER API connections');
        $info_sheet->setCellValue('G25', 'Y');
        $info_sheet->setCellValue('H25', '');
        $info_sheet->setCellValue('A26', 'L');
        $info_sheet->setCellValue('B26', 'Requestor ID');
        $info_sheet->setCellValue('C26', '');
        $info_sheet->setCellValue('D26', 'String');
        $info_sheet->setCellValue('E26', 'Y or N');
        $info_sheet->setCellValue('F26', 'Requestor ID is required for COUNTER API connections');
        $info_sheet->setCellValue('G26', 'N');
        $info_sheet->setCellValue('H26', '');
        $info_sheet->setCellValue('A27', 'M');
        $info_sheet->setCellValue('B27', 'API Key');
        $info_sheet->setCellValue('C27', '');
        $info_sheet->setCellValue('D27', 'String');
        $info_sheet->setCellValue('E27', 'Y or N');
        $info_sheet->setCellValue('F27', 'API Key is required for COUNTER API connections');
        $info_sheet->setCellValue('G27', 'N');
        $info_sheet->setCellValue('H27', '');
        $info_sheet->setCellValue('A28', 'N');
        $info_sheet->setCellValue('B28', 'Platform Parameter');
        $info_sheet->setCellValue('C28', '');
        $info_sheet->setCellValue('D28', 'String');
        $info_sheet->setCellValue('E28', '');
        $info_sheet->setCellValue('F28', 'Provider-specific Platform Name');
        $info_sheet->setCellValue('G28', 'NULL');
        $info_sheet->setCellValue('H28', '');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 29; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E','F','G','H');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // setup arrays with the report and connectors mapped to their column ids
        $rpt_col = array('PR' => 'G', 'DR' => 'H', 'TR' => 'I', 'IR' => 'J');
        $cnx_col = array('customer_id' => 'K', 'requestor_id' => 'L', 'api_key' => 'M');

        // Load the provider data into a new sheet
        $providers_sheet = $spreadsheet->createSheet();
        $providers_sheet->setTitle('Platforms');
        $providers_sheet->setCellValue('A1', 'Registry-ID');
        $providers_sheet->setCellValue('B1', 'Platform Name');
        $providers_sheet->setCellValue('C1', 'Release');
        $providers_sheet->setCellValue('D1', 'Active');
        $providers_sheet->setCellValue('E1', 'Server URL');
        $providers_sheet->setCellValue('F1', 'Day-Of-Month');
        $providers_sheet->setCellValue('G1', 'PR-Reports');
        $providers_sheet->setCellValue('H1', 'DR-Reports');
        $providers_sheet->setCellValue('I1', 'TR-Reports');
        $providers_sheet->setCellValue('J1', 'IR-Reports');
        $providers_sheet->setCellValue('K1', 'Customer-ID');
        $providers_sheet->setCellValue('L1', 'Requestor-ID');
        $providers_sheet->setCellValue('M1', 'API-Key');
        $providers_sheet->setCellValue('N1', 'Platform');
        $row = 2;
        foreach ($global_providers as $provider) {
            $registries = array();
            if ($provider->registries->count() > 0) {
                $registries = $provider->registries->sortBy('release')->toArray();
            } else {
                $reg = array('release' => $provider->selected_release, 'service_url' => '');
                $registries[] = $reg;
            }
            foreach ($registries as $reg) {
                $providers_sheet->getRowDimension($row)->setRowHeight(15);
                $providers_sheet->setCellValue('A' . $row, $provider->registry_id);
                $providers_sheet->setCellValue('B' . $row, $provider->name);
                $_stat = ($provider->is_active) ? "Y" : "N";
                $providers_sheet->setCellValue('C' . $row, $reg['release']);
                $providers_sheet->setCellValue('D' . $row, $_stat);
                $providers_sheet->setCellValue('E' . $row, $reg['service_url']);
                $providers_sheet->setCellValue('F' . $row, $provider->day_of_month);
                foreach ($masterReports as $master) {
                    $value = (in_array($master->id, $provider->master_reports)) ? 'Y' : 'N';
                    $providers_sheet->setCellValue($rpt_col[$master->name] . $row, $value);
                }
                foreach ($allConnectors as $field) {
                    if ($field->name == 'extra_args') continue;
                    $value = (in_array($field->id, $provider->connectors())) ? 'Y' : 'N';
                    $providers_sheet->setCellValue($cnx_col[$field->name] . $row, $value);
                }
                $providers_sheet->setCellValue('N' . $row, $provider->platform_parm);
                $row++;
            }
        }
        // Auto-size the columns
        $columns = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N');
        foreach ($columns as $col) {
            $providers_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // redirect output to client browser
        $fileName = "CCplus_Global_Platforms.xlsx";
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
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
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing providers, connection fields and master_reports
        $global_providers = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();
        $this->getMasterReports();
        $this->getConnectionFields();

        // Setup Mapping for report-COLUMN indeces to master_report ID's (ID => COL)
        $rpt_columns = array( 3=>6, 2=>7, 1=>8, 4=>9);
        $col_defaults = array( 3=>'N', 4=>'', 5=>15, 6=>'N', 7=>'Y', 8=>'N', 9=>'Y', 10=>'N', 11=>'N', 12=>'N', 13=>'N');

        // Process the input rows
        $prov_skipped = 0;
        $prov_updated = 0;
        $prov_created = 0;
        foreach ($rows as $row) {
            // Ignore header row and records with no registryID and no name 
            if ($row[0] == 'Registry-ID' || (!isset($row[0]) && !isset($row[1]))) {
                continue;
            }
            // RegistryID-or-Name up-to-URL required
            if (trim($row[0]) == "" && (trim($row[1]) == "" || sizeof($row) < 5)) {
                continue;
            }

            // Update/Add the provider data/settings
            // Check Registry-ID and name columns for silliness or errors
            $_regid = trim($row[0]);
            $_name = trim($row[1]);
            $current_prov = null;
            if ( strlen($_regid) > 0 ) {
                $current_prov = $global_providers->where("registry_id", $_regid)->first();  // Look for match on ID
            }
            if ($current_prov) {      // found matching Registry-ID
                if (strlen($_name) < 1) {       // If import-name empty, use current value
                    $_name = trim($current_prov->name);
                } else {                        // trap changing a name to a name that already exists
                    $existing_prov = $global_providers->where("name", $_name)->first();
                    if ($existing_prov) {
                        $_name = trim($current_prov->name);     // override, use current - no change
                    }
                }
            } else {        // Registry-ID not found, try to find by name
                $current_prov = $global_providers->where("name", $_name)->first();
                if ($current_prov) {
                    $_name = trim($current_prov->name);
                }
            }

            // Name and URL both required - skip if either is empty
            if (strlen($_name) < 1 || strlen(trim($row[4])) < 1) {
                $prov_skipped++;
                continue;
            }

            // Enforce defaults
            foreach ($col_defaults as $_col => $_val) {
                if (strlen(trim($row[$_col])) < 1) {
                    $row[$_col] = $_val;
                }
            }
            $_active = ($row[3] == 'N') ? 0 : 1;
            $_day = ( is_null($row[5]) || strlen(trim($row[5]))<1 ) ? 15 : trim($row[5]);

            // Setup provider data as an array
            $_prov = array('name' => $_name, 'is_active' => $_active, 'day_of_month' => $_day);

            // Add reports to the array ($rpt_columns defined above)
            $reports = array();
            foreach ($rpt_columns as $id => $col) {
                if ($row[$col] == 'Y') $reports[] = $id;
            }
            $_prov['master_reports'] = $reports;

            // Set platform_parm
            if ($row[11] == 'Y') {
                $_prov['platform_parm'] = $row[13];
            }

            // Update or create the GlobalProvider record and Registry record
            if ($current_prov) {      // Update
                $_prov['id'] = $current_prov->id;
                $current_prov->update($_prov);
                $prov_updated++;
            } else {                 // Create
                // Set as not-refreshable if no Registry-ID given
                if ( is_null($row[0]) || strlen(trim($row[0]))<1 ) {
                    $_prov['refreshable'] = 0;
                }
                $current_prov = GlobalProvider::create($_prov);
                $current_prov->load('registries');
                $global_providers->push($current_prov);
                $prov_created++;
            }

            // Setup connectors for the registry record (columns 10-12 have the connector fields)
            $connectors = array();
            for ($cnx=1; $cnx<4; $cnx++) {
                if ($row[$cnx+9] == 'Y') $connectors[] = $cnx;
            }

            // Find/Update/Create the counter_registry record based on "release" value
            $_release = trim($row[2]);
            $registry = $current_prov->registries->where('release',$_release)->first();
            // Update existing entry
            if ($registry) {
                $registry->service_url = $row[4];
                $registry->connectors = $connectors;
                $registry->save();

            // Create an entry
            } else if (strlen($_release)>0) {
                $_rel = (strlen($_release)>0) ? $_release : '5';
                $_reg = array('global_id' => $current_prov->id, 'release' => $_rel,
                              'service_url' => $row[4], 'connectors' => $connectors);
                $registry = CounterRegistry::create($_reg);
            }
            $current_prov->load('registries');
        }

        // get all the consortium instances and preserve the current instance database setting
        $instances = Consortium::get();

        // Rebuild full array of global providers to update (needs to match what index() does)
        $updated_providers = array();
        $gp_data = GlobalProvider::with('registries')->orderBy('name', 'ASC')->get();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";
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
            $provider['report_state'] = $this->reportState($gp->master_reports);
            $provider['can_delete'] = true;
            $provider['connection_count'] = 0;
            $provider['updated'] = (is_null($gp->updated_at)) ? "" : date("Y-m-d H:i", strtotime($gp->updated_at));
            // Collect details from the instance for this provider
            foreach ($instances as $instance) {
                $details = $this->instanceDetails($instance->ccp_key, $gp);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                $provider['connection_count'] += $details['connections'];
            }
            $updated_providers[] = $provider;
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

        return response()->json(['result' => true, 'msg' => $msg, 'platforms' => $updated_providers]);
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
     * Return harvest_count, connection_count, and last_harvest for a global provider in a given instance
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
        $connections = $result[0]->num;
        $last = $result[0]->last;

        // Get the number of harvests
        $qry .= " and last_harvest is not null";
        $result = DB::select($qry);
        $count = $result[0]->num;

        // return the numbers
        return array('harvest_count' => $count , 'connections' => $connections, 'last_harvest' => $last);
    }
}
