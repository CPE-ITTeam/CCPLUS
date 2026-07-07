<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Storage;
use Session;
use App\Models\Consortium;
use App\Models\Report;
use App\Models\ReportField;
use App\Models\ReportFilter;
use App\Models\SavedReport;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\InstitutionGroup;
use App\Models\GlobalProvider;
use App\Models\Connection;
use App\Models\Platform;
use App\Models\Publisher;
use App\Models\DataType;
use App\Models\SectionType;
use App\Models\AccessType;
use App\Models\AccessMethod;
use App\Models\HarvestLog;
use App\Models\Credential;
use App\Services\HarvestService;
use App\Services\ReportService;
use League\Csv\Writer;
use SplTempFileObject;

class ReportController extends Controller
{
    private static $input_filters;
    private $group_by;
    private $format;
    private $raw_fields;
    private $raw_where;
    private $subq_fields;
    private $subq_where;
    private $rpt_only;
    private $joins;
    private $all_models;
    private $global_db;
    private $conso_db;
    protected $harvestService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(HarvestService $harvestService)
    {
        global $raw_fields, $group_by, $joins, $raw_where, $all_models;

        self::$input_filters = [];
        $raw_fields = '';
        $raw_where = '';
        $subq_raw_fields = '';
        $subq_raw_where = '';
        $group_by = [];
        $format = 'Compact';
        $joins = ['institution' => "", 'provider' => "", 'platform' => "", 'publisher' => "",
                  'datatype' => "", 'accesstype' => "", 'accessmethod' => "", 'sectiontype' => ""];
        $all_models = ['TR' => '\\App\Models\\TitleReport',    'DR' => '\\App\Models\\DatabaseReport',
                       'PR' => '\\App\Models\\PlatformReport', 'IR' => '\\App\Models\\ItemReport'];
        $this->harvestService = $harvestService;
    }

    /**
     * Return options for Reporting component
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON (array of options)
     */
    public function create(Request $request)
    {
        $thisUser = $request->user();
        $consoAdmin = $thisUser->isConsoAdmin();
        $return_data = array();

        // Get an array of platforms and institutions with successful harvests (to limit choices below)
        $provs_with_data = $this->harvestService->hasHarvests('prov_id');
        $insts_with_data = $this->harvestService->hasHarvests('inst_id');


        // Set institution and group options, depending on role(s)
        $limit_by_inst = ($consoAdmin) ? array() : $thisUser->viewerInsts();
        $return_data['institutions'] =
            Institution::whereIn('id', $insts_with_data)->orderBy('name', 'ASC')->where('id', '<>', 1)
                        ->when(count($limit_by_inst)>0, function ($qry) use ($limit_by_inst) {
                            return $qry->whereIn('id', $limit_by_inst);
                        })->get(['id','name'])->toArray();

        $limit_groups = ($consoAdmin) ? array() : $thisUser->viewerGroups();
        $return_data['groups'] = InstitutionGroup::when($consoAdmin, function ($qry) {
                                                     return $qry->whereNull('user_id');
                                                 })
                                                 ->when(count($limit_groups)>0, function ($qry) use ($limit_groups) {
                                                     return $qry->whereIn('id', $limit_groups);
                                                 })->orderBy('name','ASC')->get(['id','name']);

        // Pull globalProvider IDs based on the consortium connections defined for institutions
        // in $limit_by_inst ; everyone gets consortium-wide providers (where inst_id=1)
        $global_ids = Connection::when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
                                    return $qry->where('inst_id',1)->orWhereIn('inst_id',$limit_by_inst);
                                })->select('global_id')->distinct()->pluck('global_id')->toArray();

        // Pull global platforms that have data
        $limit_by_prov = array_intersect($global_ids, $provs_with_data);
        $globals = GlobalProvider::with('connections','connections.reports')->whereIn('id',$limit_by_prov)
                                ->orderBy('name','ASC')->get(['id','name']);

        // Build platforms from globals connected to insts in limit_by_inst and add report assignments
        $return_data['platforms'] = array();
        foreach ($globals as $global) {
            $global_inst_ids = $global->connectedInstitutions();
            if (count($limit_by_inst) == 0 || count(array_intersect($global_inst_ids, $limit_by_inst)) > 0) {
                $global->reports = $global->enabledReports();
                $global->institutions = $global_inst_ids;
                $return_data['platforms'][] = $global;
            }
        }

        // Get `databases`, bound by has-data and inst/prov limits set above
        $conso_db = config('database.connections.consodb.database');
        $return_data['databases'] = DB::table($conso_db.'.dr_report_data as DR')->selectRaw("DISTINCT(DB.name),DB.id")
                                      ->join('ccplus_global.databases as DB', 'DR.db_id', 'DB.id')
                                      ->when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
                                          return $qry->whereIn('inst_id',$limit_by_inst);
                                      })
                                      ->whereIn('DR.prov_id',$limit_by_prov)
                                      ->orderBy('DB.name', 'ASC')->get()->toArray();

        // Get institution_types
        $return_data['institution_types'] = InstitutionType::get();

        // Get options from the global tables
        $return_data['access_methods'] = AccessMethod::get();
        $return_data['access_types'] = AccessType::get();
        $return_data['data_types'] = DataType::get();
        $return_data['section_types'] = SectionType::get();

        // Set 'reports' ordered by ID (not dorder) including children (standard COUNTER views)
        $all_reports = Report::with('children','parent','parent.reportFields','reportFields')
                             ->orderBy('id','asc')->get();
        $return_data['master_reports'] = $all_reports->where('parent_id',0);

        // Set 'all_reports' keyed by ID to hold field-mappings
        $return_data['report_views'] = array();
        foreach ($all_reports as $rpt) {
            if ($rpt->parent_id == 0) continue;
            $rec = array('id' => $rpt->id, 'name' => $rpt->name, 'legend' => $rpt->legend, 'master_id' => $rpt->parent_id,
                         'report_fields' => []);
            $fields = $rpt->reportFields();
            foreach ($fields as $fld) {
                $preset = (isset($fld->preset)) ? $fld->preset : null;
                $rec['report_fields'][] = array('id' => $fld->id, 'preset' => $preset, 'is_metric' => $fld->is_metric,
                                                'metric_type' => $fld->metric_type, 'qry_as' => $fld->qry_as);
            }
            $return_data['report_views'][] = $rec;
        }

        // Get saved reports for the current user
        $return_data['saved_reports'] = SavedReport::formattedReports();

        // set FiscalYr for the user, default to Jan if missing
        $return_data['fyMo'] = 1;
        $userFY = $thisUser->getFY();
        if ( !is_null($userFY) ) {
            $date = date_parse($userFY);
            $return_data['fyMo'] = $date['month'];
        }
        return response()->json(['records' => $return_data], 200);
    }

    /**
     * Setup view for displaying usage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function display(Request $request)
    {
        return view('reports.display');
    }

    /**
     * Setup preview of usage data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        global $all_models;

        $thisUser = $request->user();
        $user_inst = $thisUser->inst_id;
        $conso_db = config('database.connections.consodb.database');

        // Start by getting a full filter-set (all elements from the datastore)
        // Saved reports have the active fields and filters built-in
        $title = "";
        $model = null;
        if (isset($request->saved_id)) {
            $saved_report = SavedReport::findOrFail($request->saved_id);
            if (!$saved_report->canManage()) {
                return response()->json(['result' => false, 'msg' => 'Access Forbidden (403)']);
            }
            $title = $saved_report->title;
            $preset_filters = $saved_report->filterBy();
            $inherited = preg_split('/,/', $saved_report->fields);
            $rangetype = $saved_report->date_range;

            // update the private global filters and get available data bounds
            self::$input_filters = $preset_filters;
            $model = $saved_report->master()->name;
            $data = self::queryAvailable($model);

            // update preset_filters date values based on data available
            if ($rangetype == 'latestMonth') {
                $preset_filters['fromYM'] = $data[0]['YM_max'];
                $preset_filters['toYM'] = $data[0]['YM_max'];
            } elseif ($rangetype == 'latestYear') {
                $want_min = strtotime('-11 months', strtotime($data[0]['YM_max']));
                $have_min = strtotime($data[0]['YM_min']);
                $from = ($have_min > $want_min) ? date("Y-m", $have_min) : date("Y-m", $want_min);
                $preset_filters['fromYM'] = $from;
                $preset_filters['toYM'] = $data[0]['YM_max'];
            } else {    // Custom
                $preset_filters['fromYM'] = $saved_report->ym_from;
                $preset_filters['toYM'] = $saved_report->ym_to;
            }
            $preset_filters['dateRange'] = $rangetype;

        // If not previewing a saved report the filters should arrive via $request as Json
        } else {
            $this->validate($request, ['filters' => 'required']);
            $preset_filters = json_decode($request->filters, true);
            $rangetype = (isset($preset_filters['dateRange'])) ? $preset_filters['dateRange'] : 'Custom';
        }

        // update the private global
        self::$input_filters = $preset_filters;

        // Get the report model and all rows of the reportFilter model
        $report = Report::where('id', $preset_filters['report_id'])->first();
        if (!$report) {
            return response()
                ->json(['result' => false, 'msg' => 'Report ID: ' . $preset_filters['report_id'] . ' is undefined']);
        } else if (!isset($request->saved_id)) {
            $model = ($preset_filters['report_id'] < 5) ? $report->name : $report->parent->name;
        }
        $all_filters = ReportFilter::all();

        // Create arrays holding all filter-options; handle institutions and providers separately
        $filter_options = array();

        // Providers and insts inclusion as options depend on successful harvests
        $show_all = ($thisUser->hasAnyRole(['Admin','Viewer']));
        $provs_with_data = $this->harvestService->hasHarvests('prov_id');
        $limit_by_inst = array();
        $_insts = array();
        if ($show_all) {
            $_insts = $this->harvestService->hasHarvests('inst_id');
            $filter_options['institution'] = Institution::whereIn('id', $_insts)->where('id', '>', 1)
                                                        ->orderBy('name', 'ASC')->get(['id','name'])->toArray();
        } else {  // Managers and Users are limited their own inst
            $filter_options['institution'] = Institution::where('id', '=', $thisUser->inst_id)
                                                        ->get(['id','name'])->toArray();
            $limit_by_inst = array(1,$user_inst);
            $_insts = array($user_inst);
        }

        // Setup providers filter options
        $globals = GlobalProvider::with('connections','connections.reports')->whereIn('id',$provs_with_data)
                                 ->orderBy('name','ASC')->get(['id','name']);

        // Filter out limited providers and add report assignments and institution
        $_provs = array();
        $filter_options['provider'] = array();
        foreach ($globals as $global) {
            $global_inst_ids = $global->connectedInstitutions();
            if (count($limit_by_inst) == 0 || count(array_intersect($global_inst_ids, $limit_by_inst)) > 0) {
                $global->reports = $global->enabledReports();
                $global->institutions = $global_inst_ids;
                $filter_options['provider'][] = $global;
                $_provs[] = $global->id;
            }
        }

        // Set database filter options depending on preset_filters if they exist
        $filterProvs = (count($preset_filters['prov_id'])>0) ? $preset_filters['prov_id'] : $_provs;
        $filterInsts = (count($preset_filters['inst_id'])>0) ? $preset_filters['inst_id'] : $_insts;
        $filter_options['database'] = DB::table($conso_db . '.dr_report_data as DR')->selectRaw("DISTINCT(DB.name),DB.id")
                                        ->join('ccplus_global.databases as DB', 'DR.db_id', 'DB.id')
                                        ->whereIn('DR.inst_id',$filterInsts)->whereIn('DR.prov_id',$filterProvs)
                                        ->orderBy('DB.name', 'ASC')->get()->toArray();

        // Set platform filter options depending on preset_filters if they exist
        $report_model = $all_models[$model];
        $platform_ids = $report_model::distinct('plat_id')
                                     ->whereIn('inst_id',$filterInsts)->whereIn('prov_id',$filterProvs)
                                     ->pluck('plat_id')->toArray();
        $filter_options['platform'] = Platform::orderBy('name', 'ASC')->whereIn('id',$platform_ids)
                                              ->where('name','<>',' ')->get(['id','name'])->toArray();

        // Set options for the other filters
        foreach ($all_filters as $filter) {
            if (is_null($filter->table_name)) { // yop
                continue;
            }
            $_key = rtrim($filter->table_name, "s");
            if ($_key != 'institution' && $_key != 'provider' && $_key != 'database' && $_key != 'platform') {
                $result = $filter->model::orderBy('name', 'ASC')->where('name','<>',' ')->get(['id','name'])->toArray();
                $filter_options[$_key] = $result;
            }
        }

        // Get all master-fields
        if ($report->parent_id == 0) {
            $master_fields = $report->reportFields;
        } else {
            $master_fields = $report->parent->reportFields;
        }

        // If previewing a savedreport, the $inherited defines which columns are enabled
        if (isset($request->saved_id)) {
            foreach ($master_fields as $fld) {
                $fld->active = (in_array($fld->id,$inherited)) ?  true : false;
            }

        // not loading a saved report
        } else  {
            // If we're previewing a subview, the inherited fields determine which fields are active intially.
            // All will still be available to allow filtering or activation during the preview.
            if ($report->parent_id > 0) {
                // Turn report->inherited_fields into key=>value array
                $inherited = $report->parsedInherited();
                foreach ($master_fields as $field) {
                    $field->active = (array_key_exists($field->id, $inherited)) ? 1 : 0;
                    if ($field->active) {
                        $filter = $all_filters->find($field->report_filter_id);
                        if ($filter && !is_null($inherited[$field->id])) {
                            $preset_filters[$filter->report_column] = $inherited[$field->id];
                        }
                    }
                }
            }
        }

        // Create fields and columns arrays for the component based on $master_fields and preset filters
        $fields = array();
        $columns = array();
        $year_mons = self::createYMarray();
        foreach ($master_fields as $fld) {
            $key = (is_null($fld->qry_as)) ? $fld->qry : $fld->qry_as;
            $field = array('id' => $key,'text' => $fld->legend,'active' => $fld->active,'is_metric' => $fld->is_metric);

            // Activate any field w/ an filter preset defined
            if (!$fld->active && $fld->reportFilter) {
                if (isset($preset_filters[$fld->reportFilter->report_column])) {
                    $report_column = $fld->reportFilter->report_column;
                    if (is_array($preset_filters[$report_column])) {
                        $_count = sizeof($preset_filters[$report_column]);
                        if ($_count >= 1) {
                            if ($preset_filters[$report_column][0] > 0 || $_count > 1) {
                                $field['active'] = 1;
                            }
                        }
                    } else {    // maybe unnecessary... just-in-case
                        if ($preset_filters[$report_column] > 0) {
                            $field['active'] = 1;
                        }
                    }
                }
            }
            $fields[] = $field;

            // If this is a summing-metric field, add a column for each month
            if ($fld->is_metric) {
                foreach ($year_mons as $ym) {
                    $columns[] = array('text' => $fld->legend, 'field' => $key, 'active' => $fld->active,
                                       'value' => $fld->qry_as . '_' . self::prettydate($ym));
                }

            // Otherwise add a single column to the map
            } else {
                $columns[] = array('text' => $fld->legend,'field' => $key,'active' => $field['active'],'value' => $key);
            }
        }

        $con = Consortium::where("ccp_key", session("con_key"))->first();
        $conso = ($con) ? $con->name : '';

        // Get list of saved reports for this user
        $saved_reports = SavedReport::where('user_id', thisUser->id)->get(['id','title'])->toArray();
        return view(
            'reports.preview',
            compact('preset_filters', 'fields', 'columns', 'saved_reports', 'title', 'filter_options', 'rangetype', 'conso')
        );
    }

    /**
     * Setup export file: name, headers and info and return an active handle for writing data records
     *
     * @param  \Illuminate\Http\Request  $request
     * @param Array $fields
     * @return Array $headerRows
     * @return String $filename
     */
    public function prepareExport(Request $request, $report, $fields)
    {
        global $format, $rpt_only;
        $thisUser = $request->user();

        // Get/set global things
        $filters = self::$input_filters;
        $con_key = Session::get('con_key');
        $con_name = Consortium::where('ccp_key', '=', $con_key)->value('name');
        $all_filters = ReportFilter::all();

        // Setup Report Header rows
        $multiple_insts = false;
        $group_name = '';
        $header_rows = array(["Report_Name",$report->legend]);
        $header_rows[] = array("Report_ID",$report->name);
        if (isset(self::$input_filters['institutiongroup_id'])) {
            if (self::$input_filters['institutiongroup_id'] > 0) {
                $multiple_insts = true;
                $group_name = InstitutionGroup::where('id', self::$input_filters['institutiongroup_id'])->value('name');
                $header_rows[] = array("Institution_Group",$group_name);
            }
        }
        if (!$multiple_insts) {
            if (isset(self::$input_filters['inst_id'])) {
                $filt = $all_filters->where('report_column', '=', 'inst_id')->first();
                if (sizeof(self::$input_filters['inst_id']) == 0) {
                    $header_rows[] = array("Institution_Name","All");
                } elseif (sizeof(self::$input_filters['inst_id']) > 1) {
                    $multiple_insts = true;
                    $header_rows[] = array("Institution_Name","Multiple");
                } else {
                    $_name = Institution::where('id', self::$input_filters['inst_id'])->value('name');
                    $header_rows[] = array("Institution_Name",$_name);
                }
            } else {
                $header_rows[] = array("Institution_Name",
                                       Institution::where('id', $thisUser->inst_id)->value('name'));
            }
        }
        $header_rows[] = array("Institution_ID","");
        $_data = "";
        foreach ($fields as $fld) {
            if ($fld['is_metric']) {
                $_data .= ($_data == "") ? ucwords($fld['qry_as'], "_") : "; " . ucwords($fld['qry_as'], "_");
            }
        }
        $header_rows[] = array("Metric_Types",$_data);
        $yops = "";
        $_data = "";
        // Loop across input_filters to build output filename at same time
        // as setting data strings for the Report_Filters and yops header rows
        $out_file = "CCPLUS";
        foreach (self::$input_filters as $key => $value) {
            $filt = $all_filters->where('report_column', '=', $key)->first();
            if ($filt) {
                if (!in_array($key, ['inst_id','institutiongroup_id','prov_id','plat_id','yop'])) {
                    // if the filter is not limiting, ignore for filename and Report_Filters
                    if (is_array($value)) {
                        if (sizeof($value) == 0) {
                            continue;
                        }
                        // Add to $_data for Report_Filters
                        if ($value[0] == 0) {
                            $_data .= ($_data == "") ? "" : "; ";
                            $_data .= $filt->attrib . ":All";
                        } elseif ($value[0] > 0) {
                            $_data .= ($_data == "") ? $filt->attrib : "; " . $filt->attrib;
                            $_data .= ":" . $filt->model::where('id', $value[0])->value('name');
                        }
                    } else {
                        if ($value == 0) {
                            $_data .= ($_data == "") ? "" : "; ";
                            $_data .= $filt->attrib . ":All";
                        } elseif ($value > 0) {
                            $_data .= ($_data == "") ? $filt->attrib : "; " . $filt->attrib;
                            $_data .= ":" . $filt->model::where('id', $value)->value('name');
                        }
                    }
                // YOP is in the filters... make the header row data for it here
                } elseif ($key == "yop" && is_array($value)) {
                    if (sizeof($value) == 2) {
                        $yops = "YOP:" . $value[0] . ' - ' . $value[1];
                    }
                // These filter values used to define the filename, but are not included in Report_Filters
                } elseif ($key == 'institutiongroup_id' && $group_name != '') {
                    $out_file .= "_" . $group_name;
                } elseif ($key == 'inst_id' || $key == 'prov_id' || $key == 'plat_id') {
                    if (sizeof($value) > 1) {
                        $out_file .= "_Multiple_" . $filt->table_name;
                    } elseif (sizeof($value) == 1) {
                        $out_file .= "_" . preg_replace('/ /', '', $filt->model::where('id', $value[0])->value('name'));
                    }
                }
            }
        }
        $header_rows[] = array("Report_Filters",$_data);
        $header_rows[] = array("Report_Attributes",$yops);
        $header_rows[] = array("Exceptions","");
        $_data  = "Begin_Date=" . self::$input_filters['fromYM'] . "-01; ";
        $_data .= "End_Date=" . date("Y-m-t", strtotime(self::$input_filters['toYM']));
        $header_rows[] = array("Reporting_Period",$_data);
        $header_rows[] = array("Created",date('c'));
        $header_rows[] = array("Created_By","CC Plus");
        $header_rows[] = array();
        $out_file .= "_" . $report->name . "_";
        $out_file .= self::$input_filters['fromYM'] . "_" . self::$input_filters['toYM'] . ".csv";

    // Check for ACTIVE alerts  and add a summary row linked to the alerts page/dashboard.
    //
    // if ( $alert_counts['Active'] > 0 ) {
    //   $warning  = "Warning! - At least one active alert is set for data in this report";
    //   $warning .= " details are here: /alerts\n\n";
    //   $rpt_info .= array($warning);
    // }

        // Setup header in 2-parts: "Basic" fields to the left, "Metric" fields to the right
        $left_head = array();
        $right_head = array();
        $year_mons = self::createYMarray();
        $num_months = sizeof($year_mons);
        $has_metrics = false;

        // Build left side the same, regardless of $format
        foreach ($fields as $field) {
            if ($field['is_metric']) {
                $has_metrics = true;
            } else {
                $left_head[] = $field['legend'];
            }
        }

        // If there are metrics, build right side. This side is $format-dependent
        if ($has_metrics) {
            // Metric-names for COUNTER format expressed as row-values (of Metric_Type), with counts in columns
            // labelled as YYYY_mm that come after the RP_Total column.
            if ($format == 'COUNTER') {
                $right_head[] = "Metric_Type";
                $right_head[] = "Reporting_Period_Total";
                if (!$rpt_only) {
                    foreach ($year_mons as $ym) {
                        $right_head[] = $ym;
                    }
                }

            // Counts for Metrics in 'Compact' format expressed in columns labelled <metric>_YYYY_mm,
            // Plus a Reporting Period Total for each metric. Single-month reports don't get an RP_total column
            } else {
                $met_head = array();
                $ttl_head = array();
                foreach ($fields as $field) {
                    if ($field['is_metric']) {
                        if ($num_months > 1) {
                            if (!$rpt_only) {
                                foreach ($year_mons as $ym) {
                                    $met_head[] = $field['legend'] . ' ' . $ym;
                                }
                            }
                            $ttl_head[] = 'Reporting Period Total' . ' ' . $field['legend'];
                        } else {
                            $met_head[] = $field['legend'];
                        }
                    }
                }
                $right_head = ($num_months > 1) ? array_merge($met_head,$ttl_head) : $met_head;
            }
        }

        // Return the headers and data
        $header_rows[] = array_merge($left_head, $right_head);
        return array('headerRows' => $header_rows, 'filename' => $out_file);
    }

    /**
     * Display a specific report
     *
     * @param  \App\Models\Report  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $report = Report::with('parent', 'children')->findOrFail($id);

        // Get report fields and filters for master reports
        $filters = array();
        if ($report->parent_id == 0) {
            $report->load('reportFields', 'reportFields.reportFilter');
            $fields = $report->reportFields->where('active', true)->values();

            // Set any connected filters to 'All'
            foreach ($fields as $field) {
                if ($field->reportFilter) {
                    $filters[$field->qry_as] = array('legend' => $field->legend, 'name' => 'All');
                }
            }

        // Build fields for report-views based on inherited fields
        } else {
            // Turn report->inherited_fields into key=>value array and get full master-data
            $inherited = $report->parsedInherited();
            $master_report = Report::with('reportFields', 'reportFields.reportFilter')->find($report->parent_id);

            // Pull master-field data for each inherited field, including filters
            $child_fields = array();
            foreach ($inherited as $key => $value) {
                $field = $master_report->reportFields->find($key);
                if ($field) {
                    $child_fields[] = $field;
                }

                // Get filter preset if present
                if ($field->reportFilter) {
                    $data = array('legend' => $field->legend, 'name' => 'All');
                    if ($value > 0) {
                        if ($field->reportFilter->model) {
                            $data['name'] = $field->reportFilter->model::where('id', $value)->value('name');
                        }
                    }
                    $filters[$field->qry_as] = $data;
                }
            }
            // Make the child_fields a collection
            $fields = collect($child_fields);
        }
        return view('reports.show', compact('report', 'fields', 'filters'));
    }
    /**
     * Return dates-available for each master report type, within contraints
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function getAvailable(Request $request)
    {
        $this->validate($request, ['filters' => 'required']);
        $_filters = json_decode($request->filters, true);

        // update the private global
        self::$input_filters = $_filters;

        $data = self::queryAvailable();
        return response()->json(['reports' => $data], 200);
    }

    /**
     * Build an an array of counts to limit-by based on set filters
     *
     * @return Array $limit_to_insts
     */
    private function queryAvailable($model = '')
    {
        global $all_models;

        // Setup query limiters based on self::$input_filters
        $limit_to_insts = self::limitToIds('inst_id');
        $limit_to_provs = self::limitToIds('prov_id');
        $limit_to_plats = self::limitToIds('plat_id');

        // Get counts and min/max yearmon for each master report
        $output = array();
        $raw_query = "Count(*) as  count, min(yearmon) as YM_min, max(yearmon) as YM_max";
        $models = ($model == '') ? $all_models : array($all_models[$model]);

        foreach ($models as $key => $model) {
            $result = $model::when($limit_to_insts, function ($query, $limit_to_insts) {
                                return $query->whereIn('inst_id', $limit_to_insts);
            })
                            ->when($limit_to_provs, function ($query, $limit_to_provs) {
                                return $query->whereIn('prov_id', $limit_to_provs);
                            })
                            ->when($limit_to_plats, function ($query, $limit_to_plats) {
                                return $query->whereIn('plat_id', $limit_to_plats);
                            })
                            ->selectRaw($raw_query)
                            ->get()
                            ->toArray();

            // if no data, set dates to one month ago (to keep them from being ...1969)
            if ($result[0]['count'] == 0) {
                $result[0]['YM_min'] = date("Y-m", mktime(0, 0, 0, date("m") - 1, date("d"), date("Y")));
                $result[0]['YM_max'] = $result[0]['YM_min'];
            }
            $output[$key] = $result[0];
        }
        return $output;
    }

    /**
     * Get usage report data records date-range, columns/filters, and sorting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function usageData(Request $request, ReportService $reportService)
    {
        global $format, $rpt_only;
        $thisUser = $request->user();

        // Validate and deal w/ inputs
        $this->validate($request, ['report_id'=>'required', 'fields'=>'required', 'from'=>'required', 'to'=>'required']);
        $runtype = (isset($request->runtype)) ? $request->runtype : 'preview';
        $report_id = $request->report_id;
        $selected_fields = $request->fields;

        // Get Report model, set report table target
        $report = Report::with('parent','reportFields','reportFields.reportFilter')
                        ->where('id', $report_id)->first();
        if (!$report) {
            return response()->json(['result' => false, 'msg' => 'Report ID: ' . $report_id . ' is undefined']);
        }

        // Use ReportService to generate the report records
        $request->merge(['report' => $report]);
        $records = $reportService->reportData($request);

        // Get limit arrays for institution, provider, and platform that reportService just used
        $limit_to_insts = $reportService->get('limit_to_insts');
        $limit_to_provs = $reportService->get('limit_to_provs');
        $limit_to_plats = $reportService->get('limit_to_plats');

        // Generate database filter-options for DR report
        $master_name = ($report->parent_id == 0) ? $report->name : $report->parent->name;
        if ($master_name == "DR") {
            $db_options = DB::table($report_table)->selectRaw("DISTINCT(DB.name),DB.id")
                            ->join($global_db . '.databases as DB', 'DR.db_id', 'DB.id')
                            ->when(count($limit_to_insts)>0, function ($query) use ($limit_to_insts, $master_name) {
                                return $query->whereIn($master_name . '.inst_id', $limit_to_insts);
                            })
                            ->when(count($limit_to_provs)>0, function ($query) use ($limit_to_provs, $master_name) {
                                return $query->whereIn($master_name . '.prov_id', $limit_to_provs);
                            })
                            ->when(count($limit_to_plats)>0, function ($query) use ($limit_to_plats, $master_name) {
                                return $query->whereIn($master_name . '.plat_id', $limit_to_plats);
                            })
                            ->orderBy('DB.name', 'ASC')->get()->toArray();
        }

        // Setup arrays for institution, provider, and platform whereIn clauses
        $pf_options = Platform::orderBy('name', 'ASC')->whereIn('id',$limit_to_plats)
                              ->where('name','<>',' ')->get(['id','name'])->toArray();
                              
        // If we're exporting,
        if ($runtype == 'export') {
            // Build an organized field list and separate the "basic" fields from the "metric" ones
            $basic_fields = array();
            $metric_fields = array();
            foreach ($selected_fields as $key => $fdata) {
                if (!$fdata['active']) {
                    continue;
                }
                $data = $report_fields->where('qry_as', '=', $key)->first();
                $legend = ($data) ? $data->legend : $key;

                // If metric field...
                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $key)) {
                    $metric_fields[$key] = $data;
                    $metric_fields[$key]['legend'] = $legend;
                    $metric_fields[$key]['is_metric'] = true;
                    // treat as basic
                } else {
                    $basic_fields[$key] = $data;
                    $basic_fields[$key]['legend'] = $legend;
                    $basic_fields[$key]['is_metric'] = false;
                }
            }
            // Call prepareExport to setup the headers and filename
            $export_settings = self::prepareExport($report, array_merge($basic_fields, $metric_fields));
            // Drop a log record for the report-generation
            $logrec = date("Y-m-d H:m") . " : " . $thisUser->email . " : " . $export_settings['filename'];
            Storage::append('exports.log', $logrec);
            // Return the records, headers, and filename
            return response()->json(['result' => true, 'usage'=>$records, 'headers'=>$export_settings['headerRows'],
                                     'filename'=>$export_settings['filename']],200);

        // If not exporting, return the records as JSON
        } else {
            if ($master_name == "DR") {
                return response()->json(['result' => true, 'usage'=>$records, 'pf_options'=>$pf_options,
                                         'db_options'=>$db_options],200);
            } else {
                return response()->json(['result' => true, 'usage' => $records, 'pf_options'=>$pf_options], 200);
            }
        }

        $logrec = date("Y-m-d H:m") . " : " . $_user . " : " . $export_settings['filename'];
        Storage::append('exports.log', $logrec);
    }

    /**
     * Update usage report preview columns
     *
     * @param  \Illuminate\Http\Request  $request
     *         Expects $request to be a JSON object holding the Vue state.filter_by object and report_id
     * @return \Illuminate\Http\Response
     */
    public function updateColumns(Request $request)
    {
        // Get and verify input or bail with error in json response
        $this->validate($request, ['fields'=>'required', 'format'=>'required']);
        $_format = (isset($request->format)) ? $request->format : 'Compact';

        // set filters to just the dates; it's all we need for making columns
        self::$input_filters = array('fromYM' => $request->from, 'toYM' => $request->to);

        // Build columns array based on fields and date-range 
        $columns = array();
        $input_fields = $request->fields;
        $year_mons = self::createYMarray();

        // Build columns for COUNTER format
        if ($_format == 'COUNTER') {
            $metric_count = 0;
            foreach ($input_fields as $fld) {
                if ($fld['is_metric']) {
                    $metric_count++;
                } else {
                    $columns[] = array('active' => $fld['active'], 'field' => $fld['id'], 'key' => $fld['qry_as'],
                                       'title' => $fld['legend'], 'is_metric' => 0);
                }
            }
            $columns[] = array('active' => 1, 'field' => 'Metric_Type', 'key' => $fld['metric_type'],
                               'title' => 'Metric_Type');
            if ($metric_count > 0) {
                $columns[] = array('active' => 1, 'field' => 'Reporting_Period_Total', 'key' => 'Reporting_Period_Total',
                                   'title' => 'Reporting_Period_Total', 'is_metric' => 1);
                foreach ($year_mons as $ym) {
                    $columns[] = array('active' => 1, 'field' => $ym, 'key' => $ym, 'title' => $ym, 'is_metric' => 1);
                }
            }

        // Build columns for Compact format
        } else {
            if (sizeof($year_mons) > 1) {
                $metrics = array();
            }
            foreach ($input_fields as $fld) {
                if (!$fld['active']) {
                    continue;
                }
                // If this is a summing-metric field, add a column for each month
                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $fld['qry_as'])) {
                    foreach ($year_mons as $ym) {
                        $col['key'] = $fld['qry_as'] . '_' . self::prettydate($ym);
                        $col['title'] = $fld['legend'] . ' - ' . self::prettydate($ym);
                        $columns[] = $col;
                    }

                    // If we're spanning multiple months, put the totals column into a separate array
                    if (sizeof($year_mons) > 1) {
                        $col['key'] = "RP_" . $fld['qry_as'];
                        $col['title'] = $fld['legend'] . " - " . "Reporting Period Total";
                        $metrics[] = $col;
                    }

                // Otherwise add a single column to the map
                } else {
                    $col['key'] = $fld['qry_as'];
                    $col['title'] = $fld['legend'];
                    $columns[] = $col;
                }
            }

            // Tack on totals columns
            if (sizeof($year_mons) > 1) {
                $columns = array_merge($columns, $metrics);
            }
        }
        return response()->json(['result' => true, 'columns' => $columns]);
    }

    /**
     * Set joins, the raw-select string, and group_by array based on fields, columns, and formatting
     *
     * @param  ReportField $all_fields
     * @param  Array $selected_fields
     * @param  String $format
     * @return
     */
    private function setupQueryFields($all_fields, $selected_fields)
    {
        global $joins, $raw_fields, $group_by, $subq_fields, $subq_where, $global_db, $conso_db, $raw_where,
               $format, $rpt_only;
        $year_mons = self::createYMarray();
        $total_fields = "";
        $subq_case = "";

        // Loop through all the fields
        foreach ($selected_fields as $key => $field) {
            if ($field['active']) {
                $data = $all_fields->where('qry_as', '=', $key)->first();
                if (!$data) {
                    continue;
                }

                // set join if needed
                if (!is_null($data->joins)) {
                    if (preg_match('/_conso_/', $data->joins)) {
                        $_join = preg_replace('/_conso_/', $conso_db, $data->joins);
                    }
                    if (preg_match('/_global_/', $data->joins)) {
                        $_join = preg_replace('/_global_/', $global_db, $data->joins);
                    }
                    $joins[$key] = $_join;
                }

                // Output format drives how query fields and clauses are built
                // For "COUNTER", metrics and joins are embedded in a subquery
                if ($format == 'COUNTER') {
                    if ($data->is_metric) {
                        $subq_case .= $data->qry_counter . ' ';
                    } else {
                        $raw_fields .= $data->qry_as . ',';
                        if ($data->qry != $data->qry_as) {
                            $subq_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                        } else {
                            $subq_fields .= $data->qry_as . ',';
                        }
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $group_by[] = $data->qry_as;
                    }
                } else {
                    if ($data->is_metric) {
                        if (!$rpt_only) {
                            // For "Compact", Metric fields that sum-by-yearmon become output columns.
                            // Assign metric-by-year as query fields
                            foreach ($year_mons as $ym) {
                                $raw_fields .= preg_replace('/@YM@/', $ym, $data->qry) . ' as ';
                                $raw_fields .= $data->qry_as . '_' . self::prettydate($ym) . ',';
                            }
                            // Build raw_where string (for ignoring zero-records)
                            // Metric fields that sum-by-yearmon become output columns. Assign metric-by-yr as query fields
                            $raw_where .= ($raw_where != "") ? " or " : "(";
                            $raw_where .= $data->qry_as . ">0";
                        }
                        // (if we're spanning multiple months,extend the reporting-period-total string)
                        if (sizeof($year_mons) > 1) {
                            $total_fields .= "sum(" . $data->qry_as . ") as RP_" . $data->qry_as . ',';
                        }
                    } else {
                        if ($data->qry != $data->qry_as) {
                            $raw_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                        } else {
                            $raw_fields .= $data->qry_as . ',';
                        }

                        // update filter based on column setting
                        if (isset($field['limit'])) {
                            $input_filters[$key] = $field['limit'];
                        }
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $group_by[] = $data->qry;
                    }
                }
            }
        }

        if ($format == 'COUNTER') {
            $raw_fields .= "Metric_Type, sum(data) as Reporting_Period_Total";
            $subq_fields .= "yearmon, RF.qry_as as Metric_Type, sum(CASE" . $subq_case . " ELSE 0 END) as data";

            // For "COUNTER", Metric names become column-values and sums are displayed by yearmon.
            if (!$rpt_only) {
                foreach ($year_mons as $ym) {
                    $raw_fields .= ",sum(case yearmon when '" . $ym . "' then data else 0 end) as '" . $ym . "'";
                }
            }
            $group_by[] = "Metric_Type";
        } else {
            $raw_where .= ($raw_where == "") ? "" : ")";
            $raw_fields = $raw_fields . $total_fields;
            $raw_fields = rtrim($raw_fields, ',');
        }
        return;
    }

    /**
     * Build an eloquent Where-Array based on From/To yearmon range in $input_filters
     *
     * @return Array  $dates
     */
    private function filterDates()
    {
        $dates = array();

        // Add date range as a condition if they're *both* set
        if (isset(self::$input_filters['fromYM']) && isset(self::$input_filters['toYM'])) {
            if (self::$input_filters['fromYM'] != '' && self::$input_filters['toYM'] != '') {
                $dates[] = array('yearmon','>=',self::$input_filters['fromYM']);
                $dates[] = array('yearmon','<=',self::$input_filters['toYM']);
            }
        }
        return $dates;
    }

    /**
     * Build an an array of IDs we want to limit-by based on the input filters
     *
     * @param  String $column
     * @return Array $limit_to_IDs
     */
    private function limitToIds($column)
    {
        $thisUser = auth()->user();
        $return_values = array();

        // Handle institution cases explicitly
        if ($column == 'inst_id') {
            // If user is not an "admin" or "viewer", return only their own inst.
            if (!$thisUser->hasAnyRole(['Admin','Viewer'])) {
                array_push($return_values, $thisUser->inst_id);
                return $return_values;

            // If both inst_id and group_id are set, return all inst_ids from the group
            } elseif (isset(self::$input_filters['institutiongroup_id'])) {
                if (self::$input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find(self::$input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                    return $group->institutions->pluck('id')->toArray();
                }
            }
            // Otherwise, return the inst_id filter values
            if (isset(self::$input_filters['inst_id'])) {
                $return_values = self::$input_filters['inst_id'];
            }
        }
        if (isset(self::$input_filters[$column])) {
            if (self::$input_filters[$column] > 0) {
                $return_values = self::$input_filters[$column];
            }
        }
        return $return_values;
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray()
    {
        $range = array();
        $start = strtotime(self::$input_filters['fromYM']);
        $end = strtotime(self::$input_filters['toYM']);
        if ($start > $end) {
            return $range;
        }
        while ($start <= $end) {
            $range[] = date('Y-m', $start);
            $start = strtotime("+1 month", $start);
        }
        return $range;
    }

    // Reformat a date string
    private function prettydate($date)
    {
        list($yyyy, $mm) = explode("-", $date);
        return date("M_Y", mktime(0, 0, 0, $mm, 1, $yyyy));
    }
}
