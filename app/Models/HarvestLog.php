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
        'status', 'credentials_id', 'release', 'report_id', 'yearmon', 'source', 'attempts', 'error_id', 'rawfile'
    ];

    public function failedHarvests()
    {
        return $this->hasMany('App\Models\FailedHarvest', 'harvest_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Models\Report', 'report_id');
    }

    public function credential()
    {
        return $this->belongsTo('App\Models\Credential', 'credentials_id');
    }

    public function canManage()
    {
      // ConsoAdmins can manage any record
        if (auth()->user()->isConsoAdmin()) return true;
      // Otherwise, user must be admin of the harvest inst
        return auth()->user()->isAdmin($this->credential->inst_id);
    }

    public function lastError()
    {
        return $this->belongsTo('App\Models\CcplusError', 'error_id');
    }
}
