<?php
namespace App\Services;

use Illuminate\Http\Request;
use DB;
use App\Models\Report;
use App\Models\InstitutionGroup;
use App\Models\Platform;

class ReportService
{
   /**
    * Class Constructor
    */
    private $myGlobals, $input_filters, $group_by, $format, $raw_fields, $subq_fields,
            $global_db, $conso_db, $rpt_only, $joins, $all_models, $limit_to_insts,
            $limit_to_provs, $limit_to_plats;

    public function __construct()
    {
        $this->myGlobals = ['input_filters', 'group_by', 'format', 'raw_fields', 'raw_where', 'subq_fields',
                            'global_db', 'conso_db', 'rpt_only', 'joins', 'all_models', 'limit_to_insts',
                            'limit_to_provs', 'limit_to_plats'];
        $this->input_filters = [];
        $this->format = 'Compact';
        $this->raw_fields = '';
        $this->raw_where = '';
        $this->subq_fields = '';
        $this->global_db = null;
        $this->conso_db = null;
        $this->rpt_only = false;
        $this->joins = ['institution' => "", 'provider' => "", 'platform' => "", 'publisher' => "",
                        'datatype' => "", 'accesstype' => "", 'accessmethod' => "", 'sectiontype' => ""];
        $this->all_models = ['TR' => '\\App\Models\\TitleReport',    'DR' => '\\App\Models\\DatabaseReport',
                             'PR' => '\\App\Models\\PlatformReport', 'IR' => '\\App\Models\\ItemReport'];
        $this->limit_to_insts = [];
        $this->limit_to_provs = [];
        $this->limit_to_plats = [];
    }

    // Setter and Getter for shared global variables
    public function set(string $key, mixed $value): void
    {
        if ( !in_array($key, $this->myGlobals) ) return;
        $this->{$key} = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ( !in_array($key, $this->myGlobals) ) return null;
        return $this->{$key} ?? $default;
    }

    /**
     * Get usage report data records date-range, columns/filters, and sorting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON array
     */
    public function reportData(Request $request)
    {
        // Pull elements from input request
        $selected_fields = (isset($request->fields)) ? $request->fields : null;
        $runtype = (isset($request->runtype)) ? $request->runtype : 'preview';
        $preview = (isset($request->preview) && $runtype == 'preview') ? $request->preview : 0;
        $this->format = (isset($request->format)) ? $request->format : $this->format;
        $this->rpt_only = (isset($request->RPTonly)) ? $request->RPTonly : $this->rpt_only;
        $exclude_zeros = (isset($request->zeros)) ? $request->zeros : 0;

        // $request->report should arrive ->with('parent','reportFields','reportFields.reportFilter')
        $report = (isset($request->report)) ? $request->report : null;

        // If report or fields not properly defined in $request, bail and return nothing
        if (!$report || !$selected_fields) {
            return [];
        }

        // Set master report and report_field values
        if ($report->parent_id == 0) {
            $master_id = $report->id;
            $master_name = $report->name;
            $report_fields = $report->reportFields;
        } else {
            $master_id = $report->parent_id;
            $master_name = $report->parent->name;
            $report_fields = $report->parent->reportFields;
        }

        // if input_filters already set, ensure that fromYM, toYM, and yop are set
        if ( count($this->input_filters) > 0 ) {
            if (!isset($this->input_filters['fromYM'])) $this->input_filters['fromYM'] = $request->from;
            if (!isset($this->input_filters['toYM'])) $this->input_filters['toYM'] = $request->to;
            if (!isset($this->input_filters['yop'])) $this->input_filters['yop'] = null;

        // No filters set, build them based on reportFields
        } else {
            // Loop through selected_fields and set filters from 'limit' arrays there
            $_filters = array('fromYM' => $request->from, 'toYM' => $request->to);
            $_filters['yop'] = null;
            foreach ($selected_fields as $field) {
                if (isset($field['limit'])) {
                    $reportField = $report_fields->where('id',$field['id'])->first();
                    if ($reportField) {
                        if ($reportField->reportFilter) {
                            $_filters[$reportField->reportFilter->report_column] = $field['limit'];
                        }
                    }
                }
            }
            $this->input_filters = $_filters;
        }

        // Get/set global filters and master/report fields
        $this->global_db = config('database.connections.globaldb.database');
        $this->conso_db = config('database.connections.consodb.database');
        $report_table = $this->conso_db . '.' . strtolower($master_name) . '_report_data as ' . $master_name;

        // Setup joins, fields to select, raw_where, and group_by based on active columns and formattting
        $this->setupQueryFields($report_fields, $selected_fields);
        
        // Setup arrays for institution, provider, and platform whereIn clauses
        $this->limit_to_insts = $this->limitToIds('inst_id');
        $this->limit_to_provs = $this->limitToIds('prov_id');
        $this->limit_to_plats = $this->limitToIds('plat_id');
        $limit_to_dbase = $this->limitToIds('db_id');
        $limit_to_dtype = $this->limitToIds('datatype_id');
        $limit_to_atype = $this->limitToIds('accesstype_id');
        $limit_to_ameth = $this->limitToIds('accessmethod_id');
        $limit_to_stype = $this->limitToIds('sectiontype_id');
        // Create where clause conditions for this report beginning with date-range
        $conditions = $this->filterDates();

        // Set sorting based on report-type 
        $sortDir = (isset($request->sortDesc)) ? 'DESC' : 'ASC';
        if ($master_name == 'TR' || $master_name == 'IR') {
            $sortBy = (isset($request->sortBy)) ? $request->sortBy : 'Title';
        } elseif ($master_name == 'DR') {
            $sortBy = (isset($request->sortBy)) ? $request->sortBy : 'Dbase';
        } elseif ($master_name == 'PR') {
            $sortBy = (isset($request->sortBy)) ? $request->sortBy : 'platform';
        }

        // Set local variables for the globals we will use in the (sub)queries
        $joins = $this->joins;
        $input_filters = $this->input_filters;
        $subq_fields = $this->subq_fields;
        $raw_where = $this->raw_where;
        $global_db = $this->global_db;
        $limit_to_insts = $this->limit_to_insts;
        $limit_to_provs = $this->limit_to_provs;
        $limit_to_plats = $this->limit_to_plats;

        // Run the query for "COUNTER" formatted output
        if ($this->format == "COUNTER") {
            $conditions[] = array('RF.is_metric',1);
            $inner_group = $this->group_by;
            $inner_group[] = 'yearmon';
            $records = DB::table(function ($query) use ($report_table, $joins, $subq_fields, $conditions, $inner_group,
                           $limit_to_insts, $limit_to_provs, $limit_to_dbase, $limit_to_plats, $limit_to_dtype, $limit_to_atype,
                           $limit_to_ameth, $limit_to_stype, $master_name, $master_id, $global_db) {
                      $query->from($report_table)
                      ->when($master_name == "TR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.titles as TI', $master_name . '.title_id', 'TI.id');
                      })
                      ->when($master_name == "DR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.databases as DB', $master_name . '.db_id', 'DB.id');
                      })
                      ->when($master_name == "IR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.items as Item', $master_name . '.item_id', 'Item.id')
                                       ->join($global_db . '.titles as TI', 'Item.title_id', 'TI.id');
                      })
                      ->when($joins['institution'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.plat_id', 'PLAT.id');
                      })
                      ->when($joins['publisher'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.publisher_id', 'PUBL.id');
                      })
                      ->when($joins['datatype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.datatype_id', 'DTYP.id');
                      })
                      ->when($joins['accesstype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.accesstype_id', 'ATYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.accessmethod_id', 'AMTH.id');
                      })
                      ->when($joins['sectiontype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.sectiontype_id', 'STYP.id');
                      })
                      ->join($global_db . '.reportfields as RF', 'report_id', '=', $master_id, 'inner', true)
                      ->selectRaw($subq_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) use ($master_name) {
                          return $query->whereIn($master_name . '.inst_id', $limit_to_insts);
                      })
                      ->when($limit_to_provs, function ($query, $limit_to_provs) use ($master_name) {
                          return $query->whereIn($master_name . '.prov_id', $limit_to_provs);
                      })
                      ->when($limit_to_dbase, function ($query, $limit_to_dbase) use ($master_name) {
                          return $query->whereIn($master_name . '.db_id', $limit_to_dbase);
                      })
                      ->when($limit_to_plats, function ($query, $limit_to_plats) use ($master_name) {
                          return $query->whereIn($master_name . '.plat_id', $limit_to_plats);
                      })
                      ->when($limit_to_dtype, function ($query, $limit_to_dtype) use ($master_name) {
                          return $query->whereIn($master_name . '.datatype_id', $limit_to_dtype);
                      })
                      ->when($limit_to_atype, function ($query, $limit_to_atype) use ($master_name) {
                          return $query->whereIn($master_name . '.accesstype_id', $limit_to_atype);
                      })
                      ->when($limit_to_ameth, function ($query, $limit_to_ameth) use ($master_name) {
                          return $query->whereIn($master_name . '.accessmethod_id', $limit_to_ameth);
                      })
                      ->when($limit_to_stype, function ($query, $limit_to_stype) use ($master_name) {
                          return $query->whereIn($master_name . '.sectiontype_id', $limit_to_stype);
                      })
                      ->when($input_filters['yop'], function ($query) use ($input_filters) {
                          return $query->whereBetween('yop', $input_filters['yop']);
                      })
                      ->when(sizeof($conditions) > 0, function ($query) use ($conditions) {
                          return $query->where($conditions);
                      })
                      ->groupBy($inner_group);
            }, 'stats')
                ->selectRaw($this->raw_fields)
                ->groupBy($this->group_by)
                ->when($exclude_zeros, function ($query) {
                    return $query->havingRaw('Reporting_Period_Total>0');
                })
                ->orderBy($sortBy, $sortDir)
                ->orderBy('Metric_Type', 'ASC')
                ->when($preview, function ($query, $preview) {
                      return $query->limit($preview)->get();
                }, function ($query) {
                    return $query->get();
                });
        // Run the query for the "Compact" format
        } else {
            $records = DB::table($report_table)
                      ->when($master_name == "TR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.titles as TI', $master_name . '.title_id', 'TI.id');
                      })
                      ->when($master_name == "DR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.databases as DB', $master_name . '.db_id', 'DB.id');
                      })
                      ->when($master_name == "IR", function ($query, $join) use ($master_name, $global_db) {
                          return $query->join($global_db . '.items as Item', $master_name . '.item_id', 'Item.id')
                                       ->join($global_db . '.titles as TI', 'Item.title_id', 'TI.id');
                      })
                      ->when($joins['institution'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.inst_id', 'INST.id');
                      })
                      ->when($joins['provider'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.prov_id', 'PROV.id');
                      })
                      ->when($joins['platform'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.plat_id', 'PLAT.id');
                      })
                      ->when($joins['publisher'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.publisher_id', 'PUBL.id');
                      })
                      ->when($joins['datatype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.datatype_id', 'DTYP.id');
                      })
                      ->when($joins['accesstype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.accesstype_id', 'ATYP.id');
                      })
                      ->when($joins['accessmethod'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.accessmethod_id', 'AMTH.id');
                      })
                      ->when($joins['sectiontype'], function ($query, $join) use ($master_name) {
                          return $query->join($join, $master_name . '.sectiontype_id', 'STYP.id');
                      })
                      ->selectRaw($this->raw_fields)
                      ->when($limit_to_insts, function ($query, $limit_to_insts) use ($master_name) {
                          return $query->whereIn($master_name . '.inst_id', $limit_to_insts);
                      })
                      ->when($limit_to_provs, function ($query, $limit_to_provs) use ($master_name) {
                          return $query->whereIn($master_name . '.prov_id', $limit_to_provs);
                      })
                      ->when($limit_to_dbase, function ($query, $limit_to_dbase) use ($master_name) {
                          return $query->whereIn($master_name . '.db_id', $limit_to_dbase);
                      })
                      ->when($limit_to_plats, function ($query, $limit_to_plats) use ($master_name) {
                          return $query->whereIn($master_name . '.plat_id', $limit_to_plats);
                      })
                      ->when($limit_to_dtype, function ($query, $limit_to_dtype) use ($master_name) {
                          return $query->whereIn($master_name . '.datatype_id', $limit_to_dtype);
                      })
                      ->when($limit_to_atype, function ($query, $limit_to_atype) use ($master_name) {
                          return $query->whereIn($master_name . '.accesstype_id', $limit_to_atype);
                      })
                      ->when($limit_to_ameth, function ($query, $limit_to_ameth) use ($master_name) {
                          return $query->whereIn($master_name . '.accessmethod_id', $limit_to_ameth);
                      })
                      ->when($limit_to_stype, function ($query, $limit_to_stype) use ($master_name) {
                          return $query->whereIn($master_name . '.sectiontype_id', $limit_to_stype);
                      })
                      ->when($input_filters['yop'], function ($query) use ($input_filters) {
                          return $query->whereBetween('yop', $input_filters['yop']);
                      })
                      ->when($exclude_zeros && $raw_where, function ($query) use ($raw_where) {
                          return $query->whereRaw($raw_where);
                      })
                      ->when(sizeof($conditions) > 0, function ($query) use ($conditions) {
                          return $query->where($conditions);
                      })
                      ->groupBy($this->group_by)
                      ->orderBy($sortBy, $sortDir)
                      ->when($preview, function ($query, $preview) {
                          return $query->limit($preview)->get();
                      }, function ($query) {
                          // return $query->get()->paginate($rows);
                          return $query->get();
                      });
        }

        // Return the report records
        return $records;
    }

    /**
     * Set joins, the raw-select string, and group_by array based on fields, columns, and formatting
     *
     * @param  ReportField $all_fields
     * @param  Array $selected_fields
     * @return
     */
    private function setupQueryFields($all_fields, $selected_fields)
    {
        $year_mons = $this->createYMarray();
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
                        $_join = preg_replace('/_conso_/', $this->conso_db, $data->joins);
                    }
                    if (preg_match('/_global_/', $data->joins)) {
                        $_join = preg_replace('/_global_/', $this->global_db, $data->joins);
                    }
                    $this->joins[$key] = $_join;
                }

                // Output format drives how query fields and clauses are built
                // For "COUNTER", metrics and joins are embedded in a subquery
                if ($this->format == 'COUNTER') {
                    if ($data->is_metric) {
                        $subq_case .= $data->qry_counter . ' ';
                    } else {
                        $this->raw_fields .= $data->qry_as . ',';
                        if ($data->qry != $data->qry_as) {
                            $this->subq_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                        } else {
                            $this->subq_fields .= $data->qry_as . ',';
                        }
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $this->group_by[] = $data->qry_as;
                    }
                } else {
                    if ($data->is_metric) {
                        if (!$this->rpt_only) {
                            // For "Compact", Metric fields that sum-by-yearmon become output columns.
                            // Assign metric-by-year as query fields
                            foreach ($year_mons as $ym) {
                                $this->raw_fields .= preg_replace('/@YM@/', $ym, $data->qry) . ' as ';
                                $this->raw_fields .= $data->qry_as . '_' . $this->prettydate($ym) . ',';
                            }
                            // Build raw_where string (for ignoring zero-records)
                            // Metric fields that sum-by-yearmon become output columns. Assign metric-by-yr as query fields
                            $this->raw_where .= ($this->raw_where != "") ? " or " : "(";
                            $this->raw_where .= $data->qry_as . ">0";
                        }
                        // (if we're spanning multiple months,extend the reporting-period-total string)
                        if (sizeof($year_mons) > 1) {
                            $total_fields .= "sum(" . $data->qry_as . ") as RP_" . $data->qry_as . ',';
                        }
                    } else {
                        if ($data->qry != $data->qry_as) {
                            $this->raw_fields .= $data->qry . ' as ' . $data->qry_as . ',';
                        } else {
                            $this->raw_fields .= $data->qry_as . ',';
                        }

                        // update filter based on column setting
                        if (isset($field['limit'])) {
                            $this->input_filters[$key] = $field['limit'];
                        }
                    }
                    // Group the field if reportField says to
                    if ($data->group_it) {
                        $this->group_by[] = $data->qry;
                    }
                }
            }
        }

        if ($this->format == 'COUNTER') {
            $this->raw_fields .= "Metric_Type, sum(data) as Reporting_Period_Total";
            $this->subq_fields .= "yearmon, RF.qry_as as Metric_Type, sum(CASE" . $subq_case . " ELSE 0 END) as data";

            // For "COUNTER", Metric names become column-values and sums are displayed by yearmon.
            if (!$this->rpt_only) {
                foreach ($year_mons as $ym) {
                    $this->raw_fields .= ",sum(case yearmon when '" . $ym . "' then data else 0 end) as '" . $ym . "'";
                }
            }
            $this->group_by[] = "Metric_Type";
        } else {
            $this->raw_where .= ($this->raw_where == "") ? "" : ")";
            $this->raw_fields = $this->raw_fields . $total_fields;
            $this->raw_fields = rtrim($this->raw_fields, ',');
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
        if (isset($this->input_filters['fromYM']) && isset($this->input_filters['toYM'])) {
            if ($this->input_filters['fromYM'] != '' && $this->input_filters['toYM'] != '') {
                $dates[] = array('yearmon','>=',$this->input_filters['fromYM']);
                $dates[] = array('yearmon','<=',$this->input_filters['toYM']);
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
            } elseif (isset($this->input_filters['institutiongroup_id'])) {
                if ($this->input_filters['institutiongroup_id'] > 0) {
                    $group = InstitutionGroup::find($this->input_filters['institutiongroup_id']);
                    $return_values = $group->institutions->pluck('id')->toArray();
                    return $group->institutions->pluck('id')->toArray();
                }
            }
            // Otherwise, return the inst_id filter values
            if (isset($this->input_filters['inst_id'])) {
                $return_values = $this->input_filters['inst_id'];
            }
        }
        if (isset($this->input_filters[$column])) {
            if ($this->input_filters[$column] > 0) {
                $return_values = $this->input_filters[$column];
            }
        }
        return $return_values;
    }

    // Turn a fromYM/toYM range into an array of yearmon strings
    private function createYMarray()
    {
        $range = array();
        $start = strtotime($this->input_filters['fromYM']);
        $end = strtotime($this->input_filters['toYM']);
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
