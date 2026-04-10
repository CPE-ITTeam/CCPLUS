<?php

namespace App\Models;

use App\Models\ReportFilter;
use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'savedreports';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'title', 'user_id', 'date_range', 'ym_from', 'ym_to', 'report_id', 'fields', 'filters',
      'format', 'exclude_zeros', 'rpt_only'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Models\Report', 'report_id');
    }

    public function master()
    {
        $theReport = $this->report()->with('parent')->first();
        return ($theReport->parent) ? $theReport->parent : $this->report();
    }

    public function canManage()
    {
      // Admin can manage anything
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage reports for their own inst
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->user->inst_id;
        }
      // Users can manage their own reports
        return $this->user_id == auth()->id();
    }

    // Turn filters into key=>value array
    public function parsedFilters()
    {
        $return_filters = array();
        foreach (preg_split('/\+/', $this->filters) as $filter) {
            $_f = preg_split('/:/', $filter);
            if (!isset($_f[1])) {
                $return_filters[$_f[0]] = null;
            } else {
                // allow for bracketed array of values
                if (preg_match("/\[(.*)\]/i", $_f[1], $matches)) {
                    $arr = array();
                    $values = preg_split("/,/", $matches[1]);
                    foreach ($values as $val) {
                        $arr[] = intval($val);
                    }
                    $return_filters[$_f[0]] = $arr;
                } else {
                    $return_filters[$_f[0]] = intval($_f[1]);
                }
            }
        }
        return $return_filters;
    }

    // Return a filters array that matches the vue-datastore filter_by object
    // before calling this, the caller really should lazy-load SavedReport
    //  ->with('master','master.reportFields','master.reportFields.reportFilter')
    public function filterBy()
    {
        $return_filters = array('report_id' => $this->master->id);
        $return_filters['toYM'] = $this->ym_to;
        $return_filters['fromYM'] = $this->ym_from;

        // Define the filter limits for this SavedReport
        $my_filters = $this->parsedFilters();
        if (count($my_filters) > 0) {
            foreach ($my_filters as $key => $value) {
                $rf = ReportFilter::where('id', $key)->first();
                if ($rf) {
                    $return_filters[$rf->report_column] = $value;
                }
            }
        }

        // Ensure that all master field filters exist in $return_filters
        $fields = $this->master->reportFields;
        $fields->load('reportFilter');
        foreach ($fields as $field) {
            if ($field->reportFilter) {
                $_col = $field->reportFilter->report_column;
                if (!isset($return_filters[$_col])) {
                    $return_filters[$_col] = ($_col == 'institutiongroup_id') ? 0 : [];
                }
            }
        }
        if (!isset($return_filters['institutiongroup_id'])) {   // This isn't a field, just a filter
            $return_filters['institutiongroup_id'] = 0;
        }
        return $return_filters;
    }

   /**
     * Return a structured array of saved reports for the current user, or for a single
     * SavedReport record given an ID
     *
     * @param  SavedReport $id
     * @return Array
     */
    public static function formattedReports($id = null)
    {
        $thisUser = auth()->user();
        $report_data = array();

        // Get SavedReport record(s)
        $saved_reports = SavedReport::with('master','master.reportFields','master.reportFields.reportFilter','report')
                                    ->when(!is_null($id), function ($qry) use ($id) {
                                        return $qry->where('id',$id);
                                    })
                                    ->when(is_null($id), function ($qry) use ($thisUser) {
                                        return $qry->where('user_id', $thisUser->id);
                                    })->get();
        if (!$saved_reports) {
            return $report_data;
        }

        // Get the report filters
        $all_filters = ReportFilter::get(['id','table_name']);

        // Get names and IDs for providers, institutions, platforms, and groups
        $all_insts = Institution::where('id', '>', 1)->get(['id','name']);
        $all_provs = GlobalProvider::get(['id','name']);
        $all_plats = Platform::get(['id','name']);
        $all_groups = InstitutionGroup::get(['id','name']);

        // Build the output data array
        foreach ($saved_reports as $report) {
            $master_id = ($report->report->parent_id>0) ? $report->report->parent_id : $report->report->id;
            $last_harvest = HarvestLog::where('report_id', $master_id)->max('yearmon');
            $data = array('id' => $report->id, 'title' => $report->title,
                          'report_id' => $report->report_id, 'master_id' => $master_id, 'format' => $report->format,
                          'report_legend' => $report->report->legend, 'report_name' => $report->report->name,
                          'exclude_zeros' => $report->exclude_zeros, 'date_range' => $report->date_range,
                          'ym_from' => $report->ym_from, 'ym_to' => $report->ym_to, 'can_delete' => true,
                          'last_harvest' => $last_harvest, 'updated_at' => $report->updated_at);

            // Setup array of filter-data to be added to the report
            $filter_data = $report->filterBy();

            // Get master fields for $report->fields and tack on filter relationship
            $fields = $report->master->reportFields->whereIn('id', preg_split('/,/', $report->fields));
            $fields->load('reportFilter');
            $data['report_fields'] = array();
            foreach($fields as $field) {
                $rec = array('id'=>$field->id, 'name'=>$field->legend, 'qry_as'=>$field->qry_as,
                             'is_metric'=>$field->is_metric, 'metric_type'=>$field->metric_type, 'names'=>'All');
                if ($field->reportFilter) {
                    if ($field->qry_as == 'institution' &&
                        (count($filter_data['inst_id']) > 0 || $filter_data['institutiongroup_id'] > 0)) {
                        if ($filter_data['institutiongroup_id'] > 0) {
                            $rec['name'] = 'Institution Group';
                            $_group = $all_groups->where('id', $filter_data['institutiongroup_id'])->first();
                            $rec['names'] = ($_group) ? $_group->name : "";
                        } else {
                            $rec['names'] = '';
                            foreach ($filter_data['inst_id'] as $val) {
                                $_inst = $all_insts->where('id', $val)->first();
                                if ($_inst) {
                                    $rec['names'] .= $_inst->name . ', ';
                                }
                            }
                        }
                        $rec['names'] = rtrim(trim($rec['names']), ',');
                    } elseif ($field->qry_as == 'provider' && count($filter_data['prov_id']) > 0) {
                        $rec['names'] = '';
                        foreach ($filter_data['prov_id'] as $val) {
                            $_prov = $all_provs->where('id', $val)->first();
                            if ($_prov) {
                                $rec['names'] .= $_prov->name . ', ';
                            }
                        }
                        $rec['names'] = rtrim(trim($rec['names']), ',');
                    } elseif ($field->qry_as == 'platform' && count($filter_data['plat_id']) > 0) {
                        $rec['names'] = '';
                        foreach ($filter_data['plat_id'] as $val) {
                            $_plat = $all_plats->where('id', $val)->first();
                            if ($_plat) {
                                $rec['names'] .= $_plat->name . ', ';
                            }
                        }
                        $rec['names'] = rtrim(trim($rec['names']), ',');
                    } elseif ($field->qry_as == 'yop' && count($filter_data['yop']) > 0) {
                        $rec['names'] = $filter_data['yop'][0] . ' to ' . $filter_data['yop'][1];
                    } elseif (!in_array($field->qry_as, ['institution','provider','platform'])) {
                        if (isset($filter_data[$field->reportFilter->report_column])) {
                            $filter_id = $filter_data[$field->reportFilter->report_column];
                            if ($field->reportFilter->model) {
                                $rec['names'] = $field->reportFilter->model::where('id', $filter_id)->value('name');
                            }
                        }
                    }
                }
                // Filter-handling
                $rec['limit'] = [];
                $rec['column'] = null;
                if ($field->reportFilter) {
                    $rec['column'] = $field->reportFilter->report_column;
                    // If a filter is set on this field's column, add it as 'limit'
                    $rec['limit'] = (in_array($field->reportFilter->report_column,array_keys($filter_data)))
                                     ? $filter_data[$field->reportFilter->report_column] : [];
                }
                $data['report_fields'][] = $rec;
            }
            $report_data[] = $data;
        }
        return (is_null($id)) ? $report_data : $report_data[0];
    }
}
