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
    protected $fillable = [ 'id', 'name', 'type_id' ];
    protected $casts =['id'=>'integer'];

    public function institutions()
    {
        return $this->belongsToMany('App\Models\Institution')->withTimestamps();
    }

    public function canManage()
    {
      // ServerAdmin can manage any group
      if (auth()->user()->isServerAdmin()) {
        return true;
      }
      return (auth()->user()->hasRole("Admin", null, $this->id));
    }

    public function typeRestriction()
    {
        return $this->belongsTo('App\Models\InstitutionType', 'type_id');
    }

}
