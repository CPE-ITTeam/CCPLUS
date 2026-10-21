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
    protected $fillable = [ 'id', 'name', 'type_id', 'user_id' ];
    protected $casts =['id'=>'integer', 'type_id'=>'integer', 'user_id'=>'integer'];

    public function institutions()
    {
        return $this->belongsToMany('App\Models\Institution')->withTimestamps();
    }

    // Note:: users with Admin role for a group are not allowed to modify the group
    // if it is created by a consoAdmin (user_id==null)
    public function canManage()
    {
      // ConsoAdmin manages all groups
      if (auth()->user()->isConsoAdmin()) return true;
      // Users can manage their own groups
      return ($this->user_id == auth()->id());
    }

    public function typeRestriction()
    {
        return $this->belongsTo('App\Models\InstitutionType', 'type_id');
    }

}
