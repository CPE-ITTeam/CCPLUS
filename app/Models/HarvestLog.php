<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HarvestLog extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'harvestlogs';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
        'status', 'sushisettings_id', 'release', 'report_id', 'yearmon', 'source', 'attempts', 'error_id', 'rawfile'
    ];

    public function failedHarvests()
    {
        return $this->hasMany('App\Models\FailedHarvest', 'harvest_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Models\Report', 'report_id');
    }

    public function sushiSetting()
    {
        return $this->belongsTo('App\Models\SushiSetting', 'sushisettings_id');
    }

    public function canManage()
    {
      // Admins can manage any record
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage harvests for their own inst only
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->sushiSetting->inst_id;
        }
      // Otherwise, return false
        return false;
    }

    public function lastError()
    {
        return $this->belongsTo('App\Models\CcplusError', 'error_id');
    }
}
