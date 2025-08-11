<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionGroup extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'institutiongroups';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [ 'id', 'name' ];
    protected $casts =['id'=>'integer'];

    public function institutions()
    {
        return $this->belongsToMany('App\Models\Institution')->withTimestamps();
    }
}
