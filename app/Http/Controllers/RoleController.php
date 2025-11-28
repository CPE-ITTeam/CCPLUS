<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\User;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Consortium;
use Illuminate\Http\Request;

class RoleController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return JSON
     */
    public function index(Request $request)
    {
        // Admins see all, managers see only their inst, everyone else gets an error
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Initialize some arrays/values
        $data = array();
        $limit_insts = [];
        $server_admin = config('ccplus.server_admin');

        // Limit by institution based on users's role(s)
        if (!$thisUser->isServerAdmin()) {
            $limit_insts = $thisUser->adminInsts();
            if ($limit_insts === [1]) $limit_insts = [];
        }

        // Setup filtering options for the datatable; note that filter options
        // for the roles dataset are single-select (not mselect), so the array
        // keys are singular (role, user, group, etc.)
        $filter_options = array();

        // Role options need to be dependent on $thisUser's roles
        $all_roles = Role::where('name','<>','ServerAdmin')->get(['id','name']);
        $filter_options['role'] = array();
        foreach ($all_roles as $role) {
            if ($role->id <= $thisUser->maxRole()) {
                $row = array('name' => $role->name, 'role_id' => $role->id, 'inst_id' => null);
                $filter_options['role'][] = $row;
                if ($thisUser->isConsoAdmin()) {
                    $row['name'] = "Consortium ".$row['name'];
                    $row['inst_id'] = 1;
                    $filter_options['role'][] = $row;
                }
            }
        }

        // Pull user records - exclude serverAdmin
        // (Note that the 'roles' relationship returns UserRole(s), NOT Roles)
        $user_data = User::with('roles')->where('email', '<>', $server_admin)
                         ->when(count($limit_insts)>0, function ($qry) use ($limit_insts) {
                             return $qry->whereIn('inst_id', $limit_insts);
                         })->orderBy('name', 'ASC')->get();

        // Make user role names one string, role IDs into an array, and status to a string for the view
        $maxRole = $thisUser->maxRole();
        foreach ($user_data as $user) {
            $canManage = $user->canManage();
            foreach ($user->allRoles() as $role) {
                // Setup array for this user data
                $rec = array('id' => $role['id'], 'role_id' => $role['role_id'], 'user_id' => $user->id,
                             'inst_id' => $role['inst_id'], 'group_id' => $role['group_id'], 'name' => $user->name,
                             'email' => $user->email);                     
                $rec['role_string'] = ($role['inst_id']==1) ? 'Consortium '.$role['name'] : $role['name'];
                $rec['inst_name'] = $role['inst'];
                $rec['group_name'] = $role['group'];
                $rec['scope'] = (!is_null($rec['inst_id'])) ? $role['inst'] : $role['group'];
                $rec['can_edit'] = false;   // Disallow editing roles - Add+Delete only
                $rec['can_delete'] = ($canManage && $role['id'] <= $maxRole);
                $data[] = $rec;
            }
        }

        // Add filtering options for institutions, groups, and users to cover institutions and groups
        // that the current user has admin rights for; $limit_insts already set (above)
        $limit_groups = [];
        if (!$thisUser->isServerAdmin()) {
            $limit_groups = $thisUser->adminGroups();
            if ($limit_groups === [1]) $limit_groups = [];
        }
        $filter_options['group'] = InstitutionGroup::when(count($limit_groups)>0, function ($qry) use ($limit_groups) {
                                                        return $qry->whereIn('id', $limit_groups);
                                                    })->orderBy('name', 'ASC')->get(['id','name']);
        $filter_options['institution'] = Institution::when(count($limit_insts)>0, function ($qry) use ($limit_insts) {
                                                        return $qry->whereIn('id', $limit_insts);
                                                    })->orderBy('name', 'ASC')->get();
        $filter_options['user'] = $user_data->map(function ($rec) {
            return [ 'id' => $rec['id'], 'name' => $rec['name'] ];
        })->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();

        // Get and verify input fields
        $this->validate($request, ['user' => 'required', 'role' => 'required']);
        $input = $request->all();

        // An institution or group assignment is required
        if (isset($input['conso']) && $thisUser->isConsoAdmin()) {
            $inst_id = ($input['conso']=='Active') ? 1 : $input['institution'];
        } else {
            $inst_id = (isset($input['institution'])) ? $input['institution'] : null;
        }
        $group_id = (isset($input['group'])) ? $input['group'] : null;
        if ( ($inst_id && !$thisUser->isAdmin($inst_id,null)) || ($group_id && !$thisUser->isAdmin(null,$group_id)) ) {
            return response()->json(['result' => false, 'msg' => 'Operation failed (403) - Forbidden']);
        }
        $role_inst = ($inst_id) ? Institution::where('id',$inst_id)->first() : null;
        $role_group = ($group_id) ? InstitutionGroup::where('id',$group_id)->first() : null;

        // Confirm that Role, User and (Inst-or-Group) exist
        $role = Role::where('id',$input['role'])->first();
        $user = User::where('id',$input['user'])->with('roles','institution:id,name')->first();
        if ( !$user || !$role || (!$role_inst && !$role_group) ) {
            return response()->json(['result' => false, 'msg' => 'Operation failed - invalid references']);
        }

        // Confirm requested role is allowed
        if ($thisUser->maxRole() < $input['role']) {
            return response()->json(['result' => false, 'msg' => 'Operation failed - not authorized']);
        }
        // If user aleady has the role, bail out
        if ($user->hasRole($role->name, $inst_id, $group_id)) {
            return response()->json(['result' => false, 'msg' => 'User already has the requested role.']);
        }

        // Add the Role record
        try {
            $new_role = UserRole::create(
                            ['user_id'=>$user->id, 'role_id'=>$role->id, 'inst_id'=>$inst_id, 'group_id'=>$group_id]
                        );
        } catch (\Exception $e) {
            return response()->json(['result'=>false, 'msg'=>'Error saving to database: '.$e->getMessage()]);
        }

        // Return the new role (with keys that match index())
        $new_role->load('user','institution','institutiongroup','role');
        $record = array('id' => $new_role->id, 'role_id' => $role->id, 'user_id' => $user->id, 'inst_id'=>$inst_id,
                        'group_id'=>$group_id, 'name' => $user->name, 'email' => $user->email);
        $record['role_string'] = ($inst_id==1) ? 'Consortium '.$role['name'] : $role['name'];
        $record['inst_name'] = ($new_role->institution) ? $new_role->institution->name : "";
        $record['group_name'] = ($new_role->institutiongroup) ? $new_role->institutiongroup->name : "";
        $record['scope'] = (!is_null($inst_id)) ? $record['inst_name'] : $record['group_name'];
        $record['can_edit'] = false;
        $record['can_delete'] = true;

        return response()->json(['result'=>true, 'msg'=>'Role successfully saved', 'record'=>$record]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = UserRole::findOrFail($id);
        $role->delete();
        return response()->json(['result'=>true, 'msg'=>'Role deleted successfully', 'record'=>null]);
    }
}
