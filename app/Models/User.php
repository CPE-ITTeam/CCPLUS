<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;

    /**
     * The database table used by the model.
     */
    protected $connection = 'consodb';
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'email', 'email_verified_at', 'password', 'inst_id', 'phone', 'fiscalYr',
        'optin_alerts', 'is_active', 'password_change_required'
    ];
    protected $casts =[
        'id'=>'integer', 'inst_id'=>'integer', 'optin_alerts'=>'integer', 'is_active'=>'integer',
        'password_change_required'=>'integer'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [ 'password' ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    // ];

    /**
     * The attributes that should be encrypted on save (password is already hashed)
     * @var array
     */
    protected $encrypted = [ 'name', 'phone' ];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function canManage()
    {
        // ServerAdmin can manage any user and is only changeable by another ServerAdmin
        if (auth()->user()->hasRole("ServerAdmin")) {
            return true;
        } else {
            if ($this->roles->where("role.name", "ServerAdmin")->first()) return false;
        }

        // If Admin for this user's inst, allow it 
        if (auth()->user()->hasRole("Admin", $this->inst_id)) return true;

        // Users can manage themselves
        return $this->id == auth()->id();
    }

    public function institution()
    {
        return $this->belongsTo('App\Models\Institution', 'inst_id');
    }

    public function roles()
    {
        return $this->hasMany('App\Models\UserRole','user_id')
                    ->with('role:id,name','institution:id,name');
    }

    public function isServerAdmin()
    {
        return !is_null($this->roles->where("role.name", "ServerAdmin")->first());
    }

    public function isConsoAdmin()
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        return !is_null($this->roles->where('inst_id',1)->where('role.name','Admin')->first());
    }

    public function isAdmin($inst=null)
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        return (is_null($inst))
            ? !is_null(($this->roles->where("role.name", "Admin")->first()))
            : !is_null(($this->roles->where("role.name", "Admin")->where("inst_id", $inst)->first()));
    }

    public function adminInsts() {
        if ($this->roles->whereIn("role.name", ["ServerAdmin"])->first()) return [1];
        $insts = array();
        foreach ($this->roles as $uRole) {
            if ($uRole->role->name == 'Admin') {
                $insts[] = $uRole->inst_id;
            }
        }
        return $insts;
    }

    public function viewerInsts() {
        if ($this->roles->whereIn("role.name", ["ServerAdmin","Admin"])->first()) return [1];
        $insts = array();
        foreach ($this->roles as $uRole) {
            if ($uRole->role->name == 'Admin' || $uRole->role->name == 'Viewer') {
                $insts[] = $uRole->inst_id;
            }
        }
        return $insts;
    }

    public function allRoles()
    {
        $roles = array();
        foreach ($this->roles as $r) {
            array_push($roles, ['id'=>$r->id , 'name'=>$r->role->name,
                                'inst_id'=>$r->inst_id, 'inst'=>$r->institution->name]);
        }
        return $roles;
    }

    public function alerts()
    {
        return $this->hasMany('App\Models\Alert', 'modified_by');
    }

    public function savedReports()
    {
        return $this->hasMany('App\Models\SavedReport', 'user_id');
    }

    public function hasAnyRole($roles)
    {
        if ($this->roles->where('role.name','ServerAdmin')->first()) return true;
        if (is_array($roles)) {
            if ($this->roles->whereIn('role.name', $roles)->first()) return true;
        } else {
            if ($this->roles->where('role.name', $roles)->first()) return true;
        }
        return 0;
    }

    public function maxRole()
    {
        return $this->roles->max('role_id');
    }

    public function maxRoleName()
    {
        $_id = $this->roles->max('role_id');
        $userRole = $this->roles->where('role_id', $_id)->first();
        return $userRole->role->name;
    }

    public function hasRole($role, $inst = null)
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        if ($this->roles->where("role.name", $role)->where("inst_id", $inst)->first()) return true;
        return 0;
    }

    public function getFY()
    {
        return (is_null($this->fiscalYr)) ? config('ccplus.fiscalYr'): $this->fiscalYr;
    }
}
