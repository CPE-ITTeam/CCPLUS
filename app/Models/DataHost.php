<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataHost extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'data_hosts';
    // Turn off timestamps
    public $timestamps = false;

    /**
      * The attributes that are mass assignable.
      * @var array
      */
    protected $fillable = ['id', 'datahost_key', 'name'];
    protected $casts = ['id'=>'integer'];

    public function registry()
    {
        return $this->belongsTo('App\Models\CounterRegistry', 'datahost_id');
    }
}
