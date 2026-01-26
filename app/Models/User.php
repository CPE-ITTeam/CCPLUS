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

    public function institution()
    {
        return $this->belongsTo('App\Models\Institution', 'inst_id');
    }

    public function roles()
    {
        return $this->hasMany('App\Models\UserRole','user_id')
                    ->with('role:id,name','institution:id,name','institutiongroup:id,name');
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

    public function isServerAdmin()
    {
        return !is_null($this->roles->where("role.name", "ServerAdmin")->first());
    }

    public function isConsoAdmin()
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        return !is_null($this->roles->where('inst_id',1)->where('role.name','Admin')->first());
    }

    // public function isGroupAdmin()
    // {
    //     if ($this->roles->where("role.name", "ServerAdmin")->first() ||
    //         $this->roles->where('inst_id',1)->where('role.name','Admin')->first()) return true;
    //     return (!is_null($this->roles->where('group_id',1)->where('role.name','Admin')->first()));
    // }

    public function adminInsts() {
        if ($this->isConsoAdmin() || $this->roles->whereIn("role.name", ["ServerAdmin"])->first()) return [1];
        $insts = array();
        foreach ($this->roles as $uRole) {
            if ( !is_null($uRole->inst_id) && $uRole->role->name == 'Admin' ) {
                if (!in_array($uRole->inst_id,$insts)) {
                    $insts[] = $uRole->inst_id;
                }
            } else if ( !is_null($uRole->group_id) && $uRole->role->name == 'Admin' ) {
                $new_insts = array_diff($uRole->institutiongroup->institutions->pluck('id')->toArray(),$insts);
                $insts = array_merge($insts, $new_insts);
            }
        }
        return $insts;
    }

    public function adminGroups() {
        if ($this->isConsoAdmin() || $this->roles->whereIn("role.name", ["ServerAdmin"])->first()) return [1];
        $groups = array();
        foreach ($this->roles as $uRole) {
            if ( !is_null($uRole->group_id) && $uRole->role->name == 'Admin' ) {
                if (!in_array($uRole->group_id,$groups)) $groups[] = $uRole->group_id;
            }
        }
        return $groups;
    }

    public function viewerInsts() {
        if ($this->isConsoAdmin() || $this->roles->whereIn("role.name", ["ServerAdmin"])->first()) return [1];
        $insts = array();
        foreach ($this->roles as $uRole) {
            if ( !is_null($uRole->inst_id) &&
                 ($uRole->role->name == 'Admin' ||$uRole->role->name == 'Viewer') ) {
                if (!in_array($uRole->inst_id,$insts)) {
                    $insts[] = $uRole->inst_id;
                }
            } else if ( !is_null($uRole->group_id) &&
                ($uRole->role->name == 'Admin' ||$uRole->role->name == 'Viewer') ) {
                $new_insts = array_diff($uRole->institutiongroup->institutions->pluck('id')->toArray(),$insts);
                $insts = array_merge($insts, $new_insts);
            }
        }
        return $insts;
    }

    public function viewerGroups() {
        if ($this->isConsoAdmin() || $this->roles->whereIn("role.name", ["ServerAdmin"])->first()) return [1];
        $groups = array();
        foreach ($this->roles as $uRole) {
            if ( !is_null($uRole->group_id) && ($uRole->role->name == 'Viewer' || $uRole->role->name == 'Admin') ) {
                if (!in_array($uRole->group_id,$groups)) $groups[] = $uRole->group_id;
            }
        }
        return $groups;
    }

//NOTE:: Need to test+confirm that this works:: i.e. chaining off to in_array of adminInsts() & adminGroups()
    public function isAdmin($inst=null, $group=null)
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        if (!is_null($inst)) {
            return (in_array($inst, $this->adminInsts()));
        } else if (!is_null($group)) {
            return (in_array($group, $this->adminGroups()));
        } else {
            return (!is_null($this->roles->where("role.name", "Admin")->first()));
        }
    }

    public function allRoles()
    {
        $roles = array();
        foreach ($this->roles as $r) {
            $rec = array('id'=>$r->id , 'role_id'=>$r->role_id, 'inst_id'=>$r->inst_id, 'group_id'=>$r->group_id,
                         'name'=>$r->role->name);
            $rec['inst'] = ($r->institution) ? $r->institution->name : "";
            $rec['group'] = ($r->institutiongroup) ? $r->institutiongroup->name : "";
            array_push($roles,$rec);
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

    public function maxRole($inst = null)
    {
        return (is_null($inst)) ? $this->roles->max('role_id')
                                : $this->roles->where('inst_id',$inst)->max('role_id');
    }

    public function maxRoleName()
    {
        $_id = $this->roles->max('role_id');
        $userRole = $this->roles->where('role_id', $_id)->first();
        return $userRole->role->name;
    }

    public function fullRoleName() {
        if ($this->isServerAdmin()) return "ServerAdmin";
        if ($this->isConsoAdmin()) return "Consortium Admin";
        if (!is_null($this->roles->where('role.name','Admin')->first())) return "Admin";
        if (!is_null($this->roles->where('role.name','Viewer')->where('inst_id',1)->first())) return "Consortium Viewer";
        if (!is_null($this->roles->where('role.name','Viewer')->first())) return "Viewer";
        return "None";
    }

    public function hasRole($role, $inst = null, $group = null)
    {
        if ($this->roles->where("role.name", "ServerAdmin")->first()) return true;
        if (!is_null($inst)) {
            if ($this->roles->where("role.name", $role)->where("inst_id", $inst)->first()) return true;
        } else if (!is_null($group)) {
            if ($this->roles->where("role.name", $role)->where("group_id", $group)->first()) return true;
        } else {
            return (!is_null($this->roles->where("role.name", "Admin")->first()));
        }
    }

    public function getFY()
    {
        return (is_null($this->fiscalYr)) ? config('ccplus.fiscalYr'): $this->fiscalYr;
    }
}
