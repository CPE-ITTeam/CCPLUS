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
    // Turn off timestampt and auto-incrementing
    public $timestamps = false;
    public $incrementing = false; 
    // Primary key is a string
    protected $keyType = 'string'; 

    /**
      * The attributes that are mass assignable.
      * @var array
      */
    protected $fillable = ['id', 'name'];

    public function registry()
    {
        return $this->belongsTo('App\Models\CounterRegistry', 'datahost_id');
    }
}
