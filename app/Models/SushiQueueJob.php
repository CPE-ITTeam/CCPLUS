<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SushiQueueJob extends Model
{
    /**
     * The database table used by the model.
     */
    protected $connection = 'globaldb';
    protected $table = 'jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'consortium_id', 'harvest_id', 'priority', 'replace_data'
    ];

    public function consortium()
    {
        return $this->belongsTo('App\Models\Consortium', 'consortium_id');
    }

    public function harvest()
    {
        return $this->belongsTo('App\Models\HarvestLog', 'harvest_id');
    }
}
