<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Severity extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'severities';

     /**
      * The attributes that are mass assignable.
      *
      * @var array
      */

    // Check the migration ant the SeveritiesTableSeeder.
    // The ID is not auto-incemented and is assigned in ranges to either Alerts or Harvests
    protected $fillable = [ 'id', 'name'];

    public function failedHarvests()
    {
        return $this->hasMany('App\Models\FailedHarvest');
    }

    public function systemAlerts()
    {
        return $this->hasMany('App\Models\SystemAlert');
    }
}
