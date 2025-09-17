<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'consodb';
    protected $table = 'institutions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
         'id', 'name', 'local_id', 'is_active', 'notes', 'type_id', 'password', 'shibURL', 'fte'
    ];
    protected $casts =['id'=>'integer', 'is_active'=>'integer', 'type_id'=>'integer'];

    public function canManage()
    {
      // Admin can manage any institution
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can only manage their own institution
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->id;
        }

        return false;
    }

    public function institutionType()
    {
        return $this->belongsTo('App\Models\InstitutionType', 'type_id');
    }

    public function institutionGroups()
    {
        return $this
            ->belongsToMany('App\Models\InstitutionGroup')
            ->withTimestamps();
    }

    public function isAMemberOf($institutiongroup)
    {
        if ($this->institutiongroups()->where('institution_group_id', $institutiongroup)->first()) {
            return true;
        }
        return false;
    }

    public function users()
    {
        return $this->hasMany('App\Models\User', 'inst_id');
    }

    public function providers()
    {
        return $this->hasMany('App\Models\Provider');
    }

    public function credentials()
    {
        return $this->hasMany('App\Models\Credential', 'inst_id');
    }

    public function alertSettings()
    {
        return $this->hasMany('App\Models\AlertSetting', 'inst_id');
    }

    public function titleReports()
    {
        return $this->hasMany('App\Models\TitleReport');
    }

    public function databaseReports()
    {
        return $this->hasMany('App\Models\DatabaseReport');
    }

    public function platformReports()
    {
        return $this->hasMany('App\Models\PlatformReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\Models\ItemReport');
    }
}
