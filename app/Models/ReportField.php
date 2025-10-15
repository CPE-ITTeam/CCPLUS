<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportField extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'globaldb';
    protected $table = 'reportfields';
    public $timestamps = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = ['report_id', 'legend', 'joins', 'qry', 'qry_counter', 'qry_as', 'group_it',
                           'report_filter_id', 'active', 'is_metric', 'metric_type'];

    public function report()
    {
        return $this->belongsTo('App\Models\Report', 'report_id');
    }

    public function savedReports()
    {
        $_db = config('database.connections.consodb.database');
        return $this
           ->belongsToMany('App\Models\SavedReport', $_db . '.savedreport_reportfield')
           ->withTimestamps();
    }

    public function alertSettings()
    {
        return $this->hasMany('App\Models\AlertSetting');
    }

    public function reportFilter()
    {
        return $this->belongsTo('App\Models\ReportFilter', 'report_filter_id');
    }

    public static function usageMetrics()
    {
        return self::where('metric_type','usage');
    }

    public static function searchMetrics()
    {
        return self::where('metric_type','search');
    }

    public static function turnawayMetrics()
    {
        return self::where('metric_type','turnaway');
    }

}
