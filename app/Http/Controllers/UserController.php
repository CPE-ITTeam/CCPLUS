<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Consortium;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
//Enables us to output flash messaging
use Session;

class UserController extends Controller
{
    /**
     * Return listing of the resource.
     *
     * @return JSON
     */
    public function index(Request $request)
    {
        // Conso/Server Admins see all, LocalAdmins see only their inst, everyone else gets an error
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }

        // Initialize some arrays/values
        $data = array();
        $limit_to_insts = [];
        $server_admin = config('ccplus.server_admin');

        // Setup filtering options for the datatable
        $filter_options = array('statuses' => array('ALL','Active','Inactive'));
        $filter_options['roles'] = Role::where('name','<>','ServerAdmin')->get(['id','name'])->toArray();

        // Limit by institution based on users's role(s)
        if (!$thisUser->isServerAdmin()) {
            $limit_to_insts = $thisUser->adminInsts();
            if ($limit_to_insts === [1]) $limit_to_insts = [];
        }

        // Pull user records - exclude serverAdmin
        $user_data = User::with('roles','institution:id,name')
                            ->when(count($limit_to_insts)>0, function ($qry) use ($limit_to_insts) {
                                return $qry->whereIn('inst_id', $limit_to_insts);
                            })
                            ->where('email', '<>', $server_admin)
                            ->orderBy('name', 'ASC')->get();

        // Make user role names one string, role IDs into an array, and status to a string for the view
        foreach ($user_data as $rec) {
            // Setup array for this user data
            $user = array('id' => $rec->id, 'email' => $rec->email, 'name' => $rec->name, 'inst_id' => $rec->inst_id,
                          'institution' => $rec->institution, 'password' => '', 'phone' => $rec->phone,
                          'last_login' => $rec->last_login);
            $user['status'] = ($rec->is_active) ? "Active" : "Inactive";
            $user['user_role'] = $rec->fullRoleName();
            // Set all user roles as arrays of <role>:<ID> pairs for institutions and groups
            $inst_roles = array();
            $group_roles = array();
            foreach ($rec->allRoles() as $r) {
                $_name = preg_replace('/ /', '', $r['name']);
                if  (!is_null($r['inst_id']))  $inst_roles[] = $_name . ":" . $r['inst_id'];
                if (!is_null($r['group_id'])) $group_roles[] = $_name . ":" . $r['group_id'];
            }
            $user['inst_roles'] = $inst_roles;
            $user['group_roles'] = $group_roles;
            $user['fiscalYr'] = ($rec->fiscalYr) ? $rec->fiscalYr : config('ccplus.fiscalYr');
            $user['can_edit'] = $rec->canManage();
            $user['can_delete'] = $rec->canManage();
            $data[] = $user;
        }

        // Limit roles in UI to current user's max role
        $filter_options['uroles'] = array();
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())->orderBy('id', 'DESC')
                         ->where('name','<>','ServerAdmin')->get(['name', 'id'])->toArray();
        foreach ($all_roles as $idx => $r) {
            if ($r['name'] == 'Admin' && $thisUser->isConsoAdmin())  $filter_options['uroles'][] = "Consortium Admin";
            if ($r['name'] == 'Viewer' && $thisUser->isConsoAdmin()) $filter_options['uroles'][] = "Consortium Viewer";
            $filter_options['uroles'][] = $r['name'];
        }

        // Add filtering options for institutions
        $instIds = $user_data->pluck('inst_id')->toArray();
        $filter_options['institutions'] = ($thisUser->isConsoAdmin())
            ? Institution::orderBy('name', 'ASC')->get(['id','name'])->toArray()
            : Institution::orderBy('name', 'ASC')->get(['id','name'])->whereIn('id',$instIds)->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Settings method to return user settings for profile page
     * @param  User $user
     * @return JSON
     */
    public function settings(User $user)
    {
        $thisUser = auth()->user();
        if ($thisUser->id != $user->id && !$user->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $user->load('roles', 'institution:id,name');
        $data = $user->toArray();
        $data['inst_name'] = $user->institution->name;
        $data['fiscalYr'] = ($user->fiscalYr) ? $user->fiscalYr : config('ccplus.fiscalYr');

        return response()->json(['records' => $data, 'options' => [], 'result' => true], 200);
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
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Operation failed (403) - Forbidden']);
        }
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|same:confirm_pass',
            'inst_id' => 'required'
        ]);

        // Put inputs into an array, check inst_id for the new user against the current user/role
        $input = $request->all();
        if ($thisUser->inst_id != $input['inst_id'] && !$thisUser->isConsoAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Operation Forbidden (403)']);
        }

        // make sure email is unique 
        $exists = User::where('email',$input['email'])->first();
        if ($exists) {
            return response()->json(['result' => false, 'msg' => 'Email address is already assigned to another user']);
        }
        if (isset($input['status'])) {
            $input['is_active'] = ($input['status'] == 'Active') ? 1 : 0;
        } else {
            $input['is_active'] = 0;
        }

        // Create the user
        $user = User::create($input);

        // Add Role record for Viewer
        $viewer_role_id = Role::where('name','Viewer')->value('id');
        try {
            $new_role = UserRole::create(['user_id'=>$user->id, 'role_id'=>$viewer_role_id,
                                            'inst_id'=>$user->inst_id, 'group_id'=>null]);
        } catch (\Exception $e) {
            return response()->json(['result'=>false, 'msg'=>'Error adding role to database: '.$e->getMessage()]);
        }
        $user->load(['institution:id,name']);

        // Set current consortium name if there are more than 1 active in this system
        $consortia = Consortium::where('is_active',1)->get();
        $con_name = "";
        if ($consortia->count() > 1) {
            $key = $request->header('X-Tenant');
            $current = $consortia->where('ccp_key',$key)->first();
            $con_name = ($current) ? $current->name : "";
        }

        // Send email to the user about their new account, but fail silently
        $data = array('name' => $user->name, 'password' => $input['password']);
        try {
            Mail::to($input['email'])->send(new \App\Mail\NewUser($con_name,$data));
        } catch (\Exception $e) { }

        // Setup array to hold new user to match index fields
        $new_user = array('id' => $user->id, 'email' => $user->email, 'name' => $user->name,
                          'inst_id' => $user->inst_id, 'institution' => $user->institution,
                          'last_login' => null);
        $new_user['status'] = ($user->is_active) ? "Active" : "Inactive";
        $new_user['user_role'] = $user->fullRoleName();
        $new_user['fiscalYr'] = ($user->fiscalYr) ? $user->fiscalYr : config('ccplus.fiscalYr');
        $new_user['can_edit'] = true;
        $new_user['can_delete'] = true;

        return response()->json(['result' => true, 'msg' => 'User successfully created', 'record' => $new_user]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  User $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $thisUser = auth()->user();

        // Ensure current user allowed to modify the record and is not trying to change ServerAdmin user
        if (!$user->canManage() ||
            ($user->isServerAdmin() && $user->email == config('ccplus.server_admin')) ) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        // Setup form field validation
        $fields = array();
        //  Validate that password changes match confirmation
        if (isset($request->password)) {
            $fields['password'] = 'same:confirm_pass';
        }
        //  Validate email address if it is NOT 'Administrator'(users table require unique anyway)
        if (isset($request->email) && $request->email != 'Administrator') {
            $fields['email'] = 'email|unique:consodb.users,email,' . $user->id;
        }
        if ( count($fields)>0 ) {
            $this->validate($request, $fields);
        }
        $input = $request->all();
        if (empty($input['password'])) {
            $input = Arr::except($input, array('password'));
        }
        $input = Arr::except($input, array('confirm_pass'));

        // Disallow non-ConsoAdmins from assigning/changing inst_id to/from insts they don't admin
        if (!$thisUser->isConsoAdmin() &&
            (!$thisUser->hasRole('Admin',$user->inst_id) || !$thisUser->hasRole('Admin',$input['inst_id']))) {
            return response()->json(['result' => false, 'msg' => 'Update Forbidden (403)']);
        }

        // check fiscalYr - save NULL if same as global setting
        if (isset($input['fiscalYr'])) {
            $months = array('January','February','March','April','May','June','July','August','September',
                            'October','November','December');
            if (!in_array($input['fiscalYr'], $months) || $input['fiscalYr'] == config('ccplus.fiscalYr')) {
                $input['fiscalYr'] = null;
            }
        }

        // Only assess/update roles if they arrive as input
        $all_roles = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        if (isset($input['roles'])) {

            // Make sure roles include at least "Viewer"
            $viewer_role_id = $all_roles->where('name', 'Viewer')->pluck('id');
            $new_roles = $input['roles'];
            if (count($new_roles) == 0) {
                array_unshift($new_roles, $view_role_id);
            }

            $current_user_roles = UserRole::where('user_id',$user->id)->get();

            // Add role that aren't set; track, by-inst, any that get skipped
            $skipped_insts = array();
            foreach ($new_roles as $r_new) {
                // disallow adding role higher than user setting it
                // set "input" to match what is in current_user_roles to avoid deleting them
                if ($thisUser->maxRole($r_new['inst_id']) < $r_new['id']) {
                    $skipped_insts[] = $r_new['inst_id'];
                    continue;
                }
                if ( !$user->hasRole($r_new['name'],$r_new['inst_id']) ) {
                    $input = array('user_id'=>$user->id, 'inst_id'=>$r_new['inst_id'], 'role_id'=>$r_new['inst_id']);
                    $result = UserRole::create($input);
                }
            }

            // Remove user roles that are not in the input roles
            // If a current role is in $skipped_insts, don't delete ANY roles for that inst since the user tried
            // to add a role above their own.. which means the previous role(s) may not be in $input['roles']
            foreach ($current_user_roles as $role) {
                if (!array_find($input['roles'], function ($input) use ($role) {
                        return ($input['role_id'] == $role->id && $input['inst_id'] == $role->inst_id);
                    }) && !in_array($role->inst_id, $skipped_insts)) {
                    UserRole::where('id',$role->id)->delete();
                }
            }
            $input = Arr::except($input, array('roles'));
        }

        // Update the user record and re-load roles
        $user->update($input);
        $user->load(['institution:id,name','roles']);

        // Setup array to hold updated user record
        $updated_user = $user->toArray();
        $updated_user['status'] = ($user->is_active) ? "Active" : "Inactive";
        $updated_user['inst_name'] = $user->institution->name;
        $updated_user['fiscalYr'] = ($user->fiscalYr) ? $user->fiscalYr : config('ccplus.fiscalYr');
        $updated_user['user_role'] = $rec->fullRoleName();
        // Set all user roles as arrays of <role>:<ID> pairs for institutions and groups
        $inst_roles = array();
        $group_roles = array();
        foreach ($user->allRoles() as $r) {
            $_name = preg_replace('/ /', '', $r['name']);
            if (!is_null($r['inst_id'])) $inst_roles[] = $_name . ":" . $r['inst_id'];
            if (!is_null($r['group_id'])) $group_roles[] = $_name . ":" . $r['group_id'];
        }
        $updated_user['inst_roles'] = $inst_roles;
        $updated_user['group_roles'] = $group_roles;
        $updated_user['can_edit'] = $user->canManage();
        $updated_user['can_delete'] = $user->canManage();
        return response()->json(['result' => true, 'msg' => 'User settings successfully updated',
                                 'record' => $updated_user]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  User $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        if (!$user->canManage() ||
            ($user->isServerAdmin() && $user->email == config('ccplus.server_admin')) ) {
            return response()->json(['result' => false, 'msg' => 'Delete failed (403) - Forbidden']);
        }
        if (auth()->id() == $id) {
            return response()->json(['result' => false,
                                     'msg' => 'Self-deletion forbidden (403); have an Admin assist you.']);
        }
        $user->delete();
        return response()->json(['result' => true, 'msg' => 'User successfully deleted']);
    }

    /**
     * Bulk operations from the U/I.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function bulk(Request $request)
    {
        global $thisUser;
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Request failed (403) - Forbidden']);
        }
        $consoAdmin = $thisUser->isConsoAdmin();

        // Validate form inputs
        $this->validate($request, ['ids' => 'required', 'action' => 'required']);
        $input = $request->all();

        // Get user records, excluding ServerAdmin, for IDs in input limited by role if necessary
        $server_admin = config('ccplus.server_admin');
        $limit_insts = ($consoAdmin) ? [] : $thisUser->adminInsts();
        $userData = User::where('email','<>',$server_admin)->whereIn('id',$input['ids'])
                        ->when( count($limit_insts) > 0, function ($qry, $limit_insts) {
                            return $qry->whereIn('inst_id', $limit_insts);
                        })->get(['id','is_active']);

        // Handle set active/inactive
        if ($input['action'] == 'Set Active' || $input['action'] == 'Set Inactive') {
            $new_is_active = ($input['action'] == 'Set Active') ? 1 : 0;
            $userIds = $userData->where('is_active','<>',$new_is_active)->pluck('id')->toArray();
            $args = array('is_active' => $new_is_active);
            User::whereIn('id',$userIds)->update($args);
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $userIds], 200);

        // Handle delete
        } else if ($input['action'] == 'Delete') {
            $userIds = $userData->pluck('id')->toArray();
            User::whereIn('id',$userIds)->delete();
            return response()->json(['result' => true, 'msg' => '', 'affectedIds' => $userIds], 200);

        // Unrecognized action
        } else {
            return response()->json(['result' => false, 'msg' => 'Unrecognized bulk action requested'], 200);
        }
    }

    /**
     * Import users from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        // Only Admins can import user data
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        // Handle and validate inputs
        $this->validate($request, ['csvfile' => 'required']);
        if (!$request->hasFile('csvfile')) {
            return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
        }

        // Get the CSV data
        $file = $request->file("csvfile")->getRealPath();
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        if (sizeof($rows) < 1) {
            return response()->json(['result' => false, 'msg' => 'Import file is empty, no changes applied.']);
        }

        // Get existing user data
        $users = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')->get();
        $institutions = Institution::get();
        $all_groups = InstitutionGroup::get();
        $all_roles = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        $viewRoleId = $all_roles->where('name', 'Viewer')->first()->id;
        // Process the input rows
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;
        foreach ($rows as $row) {
            // Ignore bad/missing/invalid IDs and/or headers
            if (!isset($row[0])) {
                continue;
            }
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 2) {
                continue;
            }
            $cur_user_id = intval($row[0]);

            // Skip row if email is empty or set to 'ServerAdmin'
            $_email = trim($row[1]);
            if ($_email == "ServerAdmin" || strlen($_email) < 1) {
                $num_skipped++;
                continue;
            }

            // Update/Add the user data/settings
            // Check ID and name columns for silliness or errors
            $current_user = $users->where("id", $cur_user_id)->first();
            if ($current_user) {                 // found existing ID
                // trap changing an email to one that already exists
                $existing_user = $users->where("name", $_email)->first();
                if (!is_null($existing_user)) {
                    $_email = trim($current_user->email);     // override, use current - no change
                }
            } else {        // existing ID not found, try to find by name
                $current_user = $users->where("email", $_email)->first();
                if (!is_null($current_user)) {
                    $_email = trim($current_user->email);
                }
            }

            // If we're creating a user, but the password field is empty, skip it
            if (is_null($current_user) && $row[2] == '') {
                $num_skipped++;
                continue;
            }

            // Enforce defaults
            $_name  = (strlen(trim($row[3])) > 0) ? trim($row[3]) : null;
            $_phone = (strlen(trim($row[4])) > 0) ? trim($row[4]) : null;
            $_active = (strtolower($row[5]) == 'inactive') ? 0 : 1;
            $_inst = (strlen(trim($row[6])) > 0) ? intval(trim($row[6])) : 1;
            $user_inst = $institutions->where('id', $_inst)->first();

            // Skip this record if institution not found or current user cannot manage it
            if (!$user_inst) {
                $num_skipped++;
                continue;
            }
            if (!$user_inst->canManage()) {
                $num_skipped++;
                continue;
            }

            // Put user data columns into an array
            $_user = array('id' => $cur_user_id, 'email' => $_email, 'name' => $_name, 'phone' => $_phone,
                           'is_active' => $_active, 'inst_id' => $_inst);

            // Only include password if it has a value
            if (strlen(trim($row[2])) > 0) {
                $_user['password'] = trim($row[2]);
            }

            // Update or create the User record
            if (is_null($current_user)) {      // Create
                $current_user = User::create($_user);
                $users->push($current_user);
                $cur_user_id = $current_user->id;
                $num_created++;
            } else {                            // Update
                $current_user->update($_user);
                $num_updated++;
            }

            // Turn inst-roles (col-7) and group-roles (col-8) into a collection of UserRoles,
            // contingent on the current user having sufficient rights to set the role(s)
            $inst_roles  = preg_split('/,/', preg_replace('/, /', ',',$row[7]));
            $import_roles = collect([]);
            foreach ($inst_roles as $inputRole) {
                $res = preg_split('/:/', preg_replace('/: /', ':',$inputRole));
                $roleName = ucwords(trim($res[0]));
                $role = $all_roles->where('name', $roleName)->first();
                if (!$role) continue;
                $_instID = intval(trim($res[1]));
                $_inst = $institutions->where('id',$_instID)->first();
                if (!$_inst) continue;
                if (!$_inst->canManage()) continue;
                $import_roles[] = array('user_id' => $current_user->id, 'role_id' => $role->id,
                                        'inst_id' => $_instID, 'group_id' => null);
            }
            $group_roles = preg_split('/,/', preg_replace('/, /', ',',$row[8]));
            foreach ($group_roles as $inputRole) {
                $res = preg_split('/:/', preg_replace('/: /', ':',$inputRole));
                $roleName = ucwords(trim($res[0]));
                $role = $all_roles->where('name', $roleName)->first();
                if (!$role) continue;
                $_groupID = intval(trim($res[1]));
                $group = $all_groups->where('id', $_groupID)->first();
                if (!$group) continue;
                if (!$group->canManage()) continue;
                $import_roles[] = array('user_id' => $current_user->id, 'role_id' => $role->id,
                                        'inst_id' => null, 'group_id' => $_groupID);
            }

            // Drop roles for current user not present in the import_roles
            foreach ($current_user->roles as $ur) {
                $keep_role = $import_roles->where('user_id',$ur->user_id)->where('role_id',$ur->role_id)
                                          ->where('inst_id',$ur->inst_id)->where('group_id',$ur->group_id)
                                          ->first();
                if (!$keep_role) {
                    UserRole::where('id',$ur->id)->delete();
                }
            }
            // Add roles for current user not currently set
            foreach ($import_roles as $ir) {
                $role_exists = $current_user->roles->where('user_id',$ir['user_id'])->where('role_id',$ir['role_id'])
                                                   ->where('inst_id',$ir['inst_id'])->where('group_id',$ir['group_id'])
                                                   ->first();
                if (!$role_exists) {
                    UserRole::create(['user_id'=>$ir['user_id'], 'role_id'=>$ir['role_id'], 'inst_id'=>$ir['inst_id'],
                                      'group_id'=>$ir['group_id']]);
                }
            }
        }

        // return the current full list of users with a success message
        $detail = "";
        $detail .= ($num_updated > 0) ? $num_updated . " updated" : "";
        if ($num_created > 0) {
            $detail .= ($detail != "") ? ", " . $num_created . " added" : $num_created . " added";
        }
        if ($num_skipped > 0) {
            $detail .= ($detail != "") ? ", " . $num_skipped . " skipped" : $num_skipped . " skipped";
        }
        $msg  = 'Import successful, Users : ' . $detail;

        return response()->json(['result' => true, 'msg' => $msg]);
    }
}
