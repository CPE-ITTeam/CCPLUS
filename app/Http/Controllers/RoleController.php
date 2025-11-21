<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Role;
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

        // Setup filtering options for the datatable
        $filter_options = array('statuses' => array('ALL','Active','Inactive'));

        // Role options need to be dependent on $thisUser's roles
        $all_roles = Role::where('name','<>','ServerAdmin')->get(['id','name']);
        $filter_options['roles'] = array();
        foreach ($all_roles as $role) {
            if ($role->id <= $thisUser->maxRole()) {
                $row = array('name' => $role->name, 'role_id' => $role->id, 'inst_id' => null);
                $filter_options['roles'][] = $row;
                if ($thisUser->isConsoAdmin()) {
                    $row['name'] = "Consortium ".$row['name'];
                    $row['inst_id'] = 1;
                    $filter_options['roles'][] = $row;
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
                $rec = array('u_role_id' => $role['id'], 'role_id' => $role['role_id'], 'user_id' => $user->id,
                             'inst_id' => $role['inst_id'], 'group_id' => $role['group_id'], 'name' => $user->name,
                             'email' => $user->email);                     
                $rec['role'] = ($role['inst_id']==1) ? 'Consortium '.$role['name'] : $role['name'];
                $rec['inst_name'] = $role['inst'];
                $rec['group_name'] = $role['group'];
                $rec['scope'] = (!is_null($rec['inst_id'])) ? $role['inst'] : $role['group'];
                $rec['can_edit'] = ($canManage && $role['id'] <= $maxRole);
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
        $filter_options['groups'] = InstitutionGroup::when(count($limit_groups)>0, function ($qry) use ($limit_groups) {
                                                        return $qry->whereIn('id', $limit_groups);
                                                    })->orderBy('name', 'ASC')->get(['id','name']);
        $filter_options['institutions'] = Institution::when(count($limit_insts)>0, function ($qry) use ($limit_insts) {
                                                        return $qry->whereIn('id', $limit_insts);
                                                    })->orderBy('name', 'ASC')->get();
        $filter_options['users'] = $user_data->map(function ($rec) {
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
//NOTE:: may want to accept a GROUP id for institution
//    :: (would slightly complicate authorization)
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Operation failed (403) - Forbidden']);
        }

        // Get and verify input fields
        $this->validate($request, ['user_id' => 'required', 'role_id' => 'required', 'inst_id' => 'required']);
        $input = $request->all();
        $role = Role::where('id',$input['role_id'])->first();
        $role_inst = Institution::where('id',$input['inst_id'])->first();
        $user = User::where('id',$input['user_id'])->with('roles','institution:id,name')->first();
        if (!$user || !$role || !$role_inst) {
            return response()->json(['result' => false, 'msg' => 'Operation failed - invalid references']);
        }

        // Confirm requested role
        if ( ($thisUser->inst_id != $input['inst_id'] && !$thisUser->isConsoAdmin()) ||
             ($thisUser->maxRole() < $input['role_id']) ) {
            return response()->json(['result' => false, 'msg' => 'Operation failed - not authorized']);
        }

        // If it's aleady set, bail
        if ( $user->hasRole($role->name, $role_inst->id) ) {
            return response()->json(['result' => false, 'msg' => 'Role already assigned to this user.']);
        }

        // Add the Role record
        try {
            $new_role = UserRole::create($input);
        } catch (\Exception $e) {
            return response()->json(['result'=>false, 'msg'=>'Error saving database: '.$e->getMessage()]);
        }

        // Return it
        $new_role->load('user','institution','role');
        return response()->json(['result'=>true, 'msg'=>'Role successfully saved', 'record'=>$new_role]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $this->validate($request, [
          'name' => 'required',
        ]);
        $role->name = $request->input('name');
        $role->save();

        return redirect()->route('roles.index')
                       ->with('success', 'Role updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return redirect()->route('roles.index')
                       ->with('success', 'Role deleted successfully');
    }
}
