<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    /**
     * The database table used by the model.
     */
    protected $connection = 'consodb';
    protected $table = 'user_roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'id', 'user_id', 'role_id', 'group_id', 'inst_id'];
    protected $casts =[
        'id'=>'integer', 'user_id'=>'integer', 'role_id'=>'integer', 'inst_id'=>'integer', 'group_id'=>'integer'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function role()
    {
        return $this->belongsTo('App\Models\Role', 'role_id');
    }

    public function institution()
    {
        return $this->belongsTo('App\Models\Institution', 'inst_id');
    }

    public function institutiongroup()
    {
        return $this->belongsTo('App\Models\InstitutionGroup', 'group_id');
    }

}
