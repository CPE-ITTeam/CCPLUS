<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consortium extends Model
{
    /**
     * The database table used by the model.
     */
    protected $connection = 'globaldb';
    protected $table = 'consortia';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ccp_key', 'name', 'email', 'fiscalYr', 'is_active'
    ];
    protected $casts = ['id' => 'integer', 'is_active' => 'integer'];

    public function effectiveFiscalYr(): string
    {
        return $this->fiscalYr ?: config('ccplus.fiscalYr', 'January');
    }

    public function ingests()
    {
        return $this->hasMany('App\Models\HarvestLog');
    }
}
