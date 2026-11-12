<?php

namespace App\Http\Controllers;

use App\Models\AccessMethod;
use App\Models\AccessType;
use App\Models\Connection;
use App\Models\Consortium;
use App\Models\DataType;
use App\Models\GlobalProvider;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\InstitutionType;
use App\Models\Platform;
use App\Models\Report;
use App\Models\ReportFilter;
use App\Models\SavedReport;
use App\Models\SectionType;
use App\Services\HarvestService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class ReportController extends BaseController
{
    private static array $input_filters = [];
    protected HarvestService $harvestService;

    public function __construct(HarvestService $harvestService)
    {
        $this->harvestService = $harvestService;
    }

    /**
     * Return options for Reporting component.
     */
    public function create(Request $request)
    {
        $thisUser = $request->user();
        $consoAdmin = $thisUser->isConsoAdmin();
        $return_data = [];

        $provs_with_data = $this->harvestService->hasHarvests('prov_id');
        $insts_with_data = $this->harvestService->hasHarvests('inst_id');

        // Institution & group options scoped to user's role(s)
        $limit_by_inst = $consoAdmin ? [] : $thisUser->viewerInsts();
        $return_data['institutions'] = Institution::whereIn('id', $insts_with_data)
            ->where('id', '<>', 1)
            ->when(count($limit_by_inst) > 0, fn($qry) => $qry->whereIn('id', $limit_by_inst))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name'])
            ->toArray();

        $limit_groups = $consoAdmin ? [] : $thisUser->viewerGroups();
        $return_data['groups'] = InstitutionGroup::when($consoAdmin, fn($qry) => $qry->whereNull('user_id'))
            ->when(count($limit_groups) > 0, fn($qry) => $qry->whereIn('id', $limit_groups))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        // Connected global platforms with harvested data
        $global_ids = Connection::when(count($limit_by_inst) > 0, function ($qry) use ($limit_by_inst) {
            return $qry->where('inst_id', 1)->orWhereIn('inst_id', $limit_by_inst);
        })->select('global_id')->distinct()->pluck('global_id')->toArray();

        $limit_by_prov = array_intersect($global_ids, $provs_with_data);
        $globals = GlobalProvider::with('connections', 'connections.reports')
            ->whereIn('id', $limit_by_prov)
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $return_data['platforms'] = [];
        foreach ($globals as $global) {
            $global_inst_ids = $global->connectedInstitutions();
            if (count($limit_by_inst) === 0 || count(array_intersect($global_inst_ids, $limit_by_inst)) > 0) {
                $global->reports = $global->enabledReports();
                $global->institutions = $global_inst_ids;
                $return_data['platforms'][] = $global;
            }
        }

        // Databases bound by has-data and inst/prov limits
        $conso_db = config('database.connections.consodb.database');
        $global_db = config('database.connections.globaldb.database');
        $return_data['databases'] = DB::table("{$conso_db}.dr_report_data as DR")
            ->selectRaw('DISTINCT(DB.name), DB.id')
            ->join("{$global_db}.databases as DB", 'DR.db_id', 'DB.id')
            ->when(count($limit_by_inst) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_by_inst))
            ->whereIn('DR.prov_id', $limit_by_prov)
            ->orderBy('DB.name', 'ASC')
            ->get()
            ->toArray();

        $return_data['institution_types'] = InstitutionType::get();
        $return_data['access_methods']    = AccessMethod::get();
        $return_data['access_types']      = AccessType::get();
        $return_data['data_types']        = DataType::get();
        $return_data['section_types']     = SectionType::get();

        // Master reports and standard COUNTER views
        $all_reports = Report::with('children', 'parent', 'parent.reportFields', 'reportFields')
            ->orderBy('id', 'asc')
            ->get();
        $return_data['master_reports'] = $all_reports->where('parent_id', 0);

        $return_data['report_views'] = [];
        foreach ($all_reports as $rpt) {
            if ($rpt->parent_id == 0) {
                continue;
            }
            $rec = [
                'id'            => $rpt->id,
                'name'          => $rpt->name,
                'legend'        => $rpt->legend,
                'master_id'     => $rpt->parent_id,
                'report_fields' => [],
            ];
            foreach ($rpt->reportFields() as $fld) {
                $rec['report_fields'][] = [
                    'id'          => $fld->id,
                    'preset'      => $fld->preset ?? null,
                    'is_metric'   => $fld->is_metric,
                    'metric_type' => $fld->metric_type,
                    'qry_as'      => $fld->qry_as,
                ];
            }
            $return_data['report_views'][] = $rec;
        }

        $return_data['saved_reports'] = SavedReport::formattedReports();

        // Fiscal year start month (default January)
        $return_data['fyMo'] = 1;
        $userFY = $thisUser->getFY();
        if (!is_null($userFY)) {
            $date = date_parse($userFY);
            $return_data['fyMo'] = $date['month'];
        }

        return response()->json(['records' => $return_data], 200);
    }

    /**
     * Get usage report data records, date-range, columns/filters, and sorting.
     */
    public function usageData(Request $request, ReportService $reportService)
    {
        $thisUser = $request->user();

        $this->validate($request, [
            'report_id' => 'required',
            'fields'    => 'required',
            'from'      => 'required',
            'to'        => 'required',
        ]);

        $runtype = $request->runtype ?? 'preview';
        $report_id = $request->report_id;
        $selected_fields = $request->fields;

        $report = Report::with('parent', 'reportFields', 'reportFields.reportFilter')
            ->where('id', $report_id)
            ->first();

        if (!$report) {
            return $this->respondError("Report ID: {$report_id} is undefined");
        }

        $request->merge(['report' => $report]);
        $records = $reportService->reportData($request);

        $limit_to_insts = $reportService->get('limit_to_insts');
        $limit_to_provs = $reportService->get('limit_to_provs');
        $limit_to_plats = $reportService->get('limit_to_plats');
        self::$input_filters = $reportService->get('input_filters');

        // Database filter options for DR reports
        $master_name = ($report->parent_id == 0) ? $report->name : $report->parent->name;
        $db_options = [];
        if ($master_name === 'DR') {
            $conso_db = config('database.connections.consodb.database');
            $global_db = config('database.connections.globaldb.database');
            $db_options = DB::table("{$conso_db}.dr_report_data as DR")
                ->selectRaw('DISTINCT(DB.name), DB.id')
                ->join("{$global_db}.databases as DB", 'DR.db_id', 'DB.id')
                ->when(count($limit_to_insts) > 0, fn($q) => $q->whereIn('DR.inst_id', $limit_to_insts))
                ->when(count($limit_to_provs) > 0, fn($q) => $q->whereIn('DR.prov_id', $limit_to_provs))
                ->when(count($limit_to_plats) > 0, fn($q) => $q->whereIn('DR.plat_id', $limit_to_plats))
                ->orderBy('DB.name', 'ASC')
                ->get()
                ->toArray();
        }

        $pf_options = Platform::orderBy('name', 'ASC')
            ->whereIn('id', $limit_to_plats)
            ->where('name', '<>', ' ')
            ->get(['id', 'name'])
            ->toArray();

        if ($runtype === 'export') {
            $report_fields = $report->reportFields;
            $basic_fields = [];
            $metric_fields = [];

            foreach ($selected_fields as $key => $fdata) {
                if (!$fdata['active']) {
                    continue;
                }
                $data = $report_fields->where('qry_as', '=', $key)->first();
                $legend = $data ? $data->legend : $key;

                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $key)) {
                    $metric_fields[$key] = [
                        'legend'    => $legend,
                        'qry_as'    => $key,
                        'is_metric' => true,
                    ];
                } else {
                    $basic_fields[$key] = [
                        'legend'    => $legend,
                        'qry_as'    => $key,
                        'is_metric' => false,
                    ];
                }
            }

            $format = $request->input('format', 'Compact');
            $rptOnly = (bool) $request->input('rpt_only', false);
            $export_settings = $this->prepareExport($report, array_merge($basic_fields, $metric_fields), $format, $rptOnly);

            $logrec = date('Y-m-d H:i') . " : {$thisUser->email} : {$export_settings['filename']}";
            Storage::append('exports.log', $logrec);

            return response()->json([
                'result'   => true,
                'usage'    => $records,
                'headers'  => $export_settings['headerRows'],
                'filename' => $export_settings['filename'],
            ], 200);
        }

        $payload = [
            'result'     => true,
            'usage'      => $records,
            'pf_options' => $pf_options,
        ];
        if ($master_name === 'DR') {
            $payload['db_options'] = $db_options;
        }
        return response()->json($payload, 200);
    }
    /**
     * Update usage report preview columns.
     */
    public function updateColumns(Request $request)
    {
        $this->validate($request, ['fields' => 'required', 'format' => 'required']);
        $_format = $request->format ?? 'Compact';
        self::$input_filters = ['fromYM' => $request->from, 'toYM' => $request->to];
        $columns = [];
        $input_fields = $request->fields;
        $year_mons = $this->createYMarray();

        if ($_format === 'COUNTER') {
            $metric_count = 0;
            foreach ($input_fields as $fld) {
                if ($fld['is_metric']) {
                    $metric_count++;
                } else {
                    $columns[] = [
                        'active'    => $fld['active'],
                        'field'     => $fld['id'],
                        'key'       => $fld['qry_as'],
                        'title'     => $fld['legend'],
                        'is_metric' => 0,
                    ];
                }
            }
            $columns[] = [
                'active' => 1,
                'field'  => 'Metric_Type',
                'key'    => 'Metric_Type',
                'title'  => 'Metric_Type',
            ];
            if ($metric_count > 0) {
                $columns[] = [
                    'active'    => 1,
                    'field'     => 'Reporting_Period_Total',
                    'key'       => 'Reporting_Period_Total',
                    'title'     => 'Reporting_Period_Total',
                    'is_metric' => 1,
                ];
                foreach ($year_mons as $ym) {
                    $columns[] = [
                        'active'    => 1,
                        'field'     => $ym,
                        'key'       => $ym,
                        'title'     => $ym,
                        'is_metric' => 1,
                    ];
                }
            }
        } else {
            $metrics = [];
            foreach ($input_fields as $fld) {
                if (!$fld['active']) {
                    continue;
                }
                if (preg_match('/^(searches_|total_|unique_|limit_|no_lic)/', $fld['qry_as'])) {
                    foreach ($year_mons as $ym) {
                        $columns[] = [
                            'key'   => $fld['qry_as'] . '_' . $this->prettydate($ym),
                            'title' => $fld['legend'] . ' - ' . $this->prettydate($ym),
                        ];
                    }
                    if (count($year_mons) > 1) {
                        $metrics[] = [
                            'key'   => 'RP_' . $fld['qry_as'],
                            'title' => $fld['legend'] . ' - Reporting Period Total',
                        ];
                    }
                } else {
                    $columns[] = [
                        'key'   => $fld['qry_as'],
                        'title' => $fld['legend'],
                    ];
                }
            }
            if (count($year_mons) > 1) {
                $columns = array_merge($columns, $metrics);
            }
        }

        return response()->json(['result' => true, 'columns' => $columns]);
    }

    /**
     * Setup export file headers and filename.
     */
    private function prepareExport(Report $report, array $fields, string $format = 'Compact', bool $rpt_only = false): array
    {
        $thisUser = auth()->user();
        $all_filters = ReportFilter::all();
        $multiple_insts = false;
        $group_name = '';
        $header_rows = [
            ['Report_Name', $report->legend],
            ['Report_ID', $report->name],
        ];
        if (!empty(self::$input_filters['institutiongroup_id']) && self::$input_filters['institutiongroup_id'] > 0) {
            $multiple_insts = true;
            $group_name = InstitutionGroup::where('id', self::$input_filters['institutiongroup_id'])->value('name');
            $header_rows[] = ['Institution_Group', $group_name];
        }
        if (!$multiple_insts) {
            if (isset(self::$input_filters['inst_id'])) {
                if (count(self::$input_filters['inst_id']) === 0) {
                    $header_rows[] = ['Institution_Name', 'All'];
                } elseif (count(self::$input_filters['inst_id']) > 1) {
                    $header_rows[] = ['Institution_Name', 'Multiple'];
                } else {
                    $_name = Institution::where('id', self::$input_filters['inst_id'])->value('name');
                    $header_rows[] = ['Institution_Name', $_name];
                }
            } else {
                $header_rows[] = ['Institution_Name', Institution::where('id', $thisUser->inst_id)->value('name')];
            }
        }
        $header_rows[] = ['Institution_ID', ''];
        $metricLabels = [];
        foreach ($fields as $fld) {
            if ($fld['is_metric']) {
                $metricLabels[] = ucwords($fld['qry_as'], '_');
            }
        }
        $header_rows[] = ['Metric_Types', implode('; ', $metricLabels)];
        $yops = '';
        $filterParts = [];
        $out_file = 'CCPLUS';
        foreach (self::$input_filters as $key => $value) {
            $filt = $all_filters->where('report_column', '=', $key)->first();
            if (!$filt) {
                continue;
            }
            if (!in_array($key, ['inst_id', 'institutiongroup_id', 'prov_id', 'plat_id', 'yop'], true)) {
                if (is_array($value)) {
                    if (count($value) === 0) {
                        continue;
                    }
                    $filterParts[] = ($value === 0)
                        ? "{$filt->attrib}:All"
                        : "{$filt->attrib}:" . $filt->model::where('id', $value)->value('name');
                } else {
                    $filterParts[] = ($value == 0)
                        ? "{$filt->attrib}:All"
                        : "{$filt->attrib}:" . $filt->model::where('id', $value)->value('name');
                }
            } elseif ($key === 'yop' && is_array($value) && count($value) === 2) {
                $yops = "YOP:{$value} - {$value}";
            } elseif ($key === 'institutiongroup_id' && $group_name !== '') {
                $out_file .= "{$group_name}";
            } elseif (in_array($key, ['inst_id', 'prov_id', 'plat_id'], true) && is_array($value)) {
                if (count($value) > 1) {
                    $out_file .= "Multiple{$filt->table_name}";
                } elseif (count($value) === 1) {
                    $out_file .= '' . preg_replace('/ /', '', $filt->model::where('id', $value)->value('name'));
                }
            }
        }
        $header_rows[] = ['Report_Filters', implode('; ', $filterParts)];
        $header_rows[] = ['Report_Attributes', $yops];
        $header_rows[] = ['Exceptions', ''];
        $header_rows[] = [
            'Reporting_Period',
            'Begin_Date=' . self::$input_filters['fromYM'] . '-01; End_Date=' . date('Y-m-t', strtotime(self::$input_filters['toYM'])),
        ];
        $header_rows[] = ['Created', date('c')];
        $header_rows[] = ['Created_By', 'CC Plus'];
        $header_rows[] = [];
        $out_file .= "{$report->name}" . self::$input_filters['fromYM'] . '_' . self::$input_filters['toYM'] . '.csv';
        $left_head = [];
        $right_head = [];
        $year_mons = $this->createYMarray();
        $num_months = count($year_mons);
        $has_metrics = false;
        foreach ($fields as $field) {
            if ($field['is_metric']) {
                $has_metrics = true;
            } else {
                $left_head[] = $field['legend'];
            }
        }
        if ($has_metrics) {
            if ($format === 'COUNTER') {
                $right_head[] = 'Metric_Type';
                $right_head[] = 'Reporting_Period_Total';
                if (!$rpt_only) {
                    foreach ($year_mons as $ym) {
                        $right_head[] = $ym;
                    }
                }
            } else {
                $met_head = [];
                $ttl_head = [];
                foreach ($fields as $field) {
                    if ($field['is_metric']) {
                        if ($num_months > 1) {
                            if (!$rpt_only) {
                                foreach ($year_mons as $ym) {
                                    $met_head[] = "{$field['legend']} {$ym}";
                                }
                            }
                            $ttl_head[] = "Reporting Period Total {$field['legend']}";
                        } else {
                            $met_head[] = $field['legend'];
                        }
                    }
                }
                $right_head = ($num_months > 1) ? array_merge($met_head, $ttl_head) : $met_head;
            }
        }
        $header_rows[] = array_merge($left_head, $right_head);
        return [
            'headerRows' => $header_rows,
            'filename'   => $out_file,
        ];
    }
    private function createYMarray(): array
    {
        $range = [];
        $start = strtotime(self::$input_filters['fromYM']);
        $end = strtotime(self::$input_filters['toYM']);
        if ($start > $end) {
            return $range;
        }
        while ($start <= $end) {
            $range[] = date('Y-m', $start);
            $start = strtotime('+1 month', $start);
        }
        return $range;
    }
    private function prettydate(string $date): string
    {
        [$yyyy, $mm] = explode('-', $date);
        return date('M_Y', mktime(0, 0, 0, (int) $mm, 1, (int) $yyyy));
    }
}
