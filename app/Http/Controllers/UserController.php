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
     * Display a listing of the resource.
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
                          'institution' => $rec->institution, 'last_login' => $rec->last_login);
            $user['status'] = ($rec->is_active) ? "Active" : "Inactive";
            $user['user_role'] = $rec->maxRoleName();
            if ($rec->inst_id!=1 && $user['user_role'] == 'Admin') {
                $user['user_role'] = "Local Admin";
            } else if ($rec->inst_id==1 && $user['user_role'] != 'ServerAdmin') {
                $user['user_role'] = 'Consortium '.$user['user_role'];
            }    
            $user['fiscalYr'] = ($rec->fiscalYr) ? $rec->fiscalYr : config('ccplus.fiscalYr');
            $user['can_edit'] = $rec->canManage();
            $user['can_delete'] = $rec->canManage();
            $data[] = $user;
        }

        // Set filter options for user roles based on what we're returning
        $filter_options['uroles'] = array();
        foreach (array_unique(array_column($data,'user_role')) as $key => $role_string) {
            $filter_options['uroles'][] = array('role' => $role_string);
        }

        // Add filtering options for institutions
        $instIds = $user_data->pluck('inst_id')->toArray();
        $filter_options['institutions'] = Institution::whereIn('id',$instIds)->get(['id','name'])->toArray();

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
        if (!isset($input['is_active'])) {
            $input['is_active'] = 0;
        }

        // Make sure roles include at least "Viewer"
        $viewer_role_id = Role::where('name','Viewer')->value('id');
        $new_roles = isset($input['roles']) ? $input['roles'] : array();
        if (count($new_roles) == 0) {
            array_unshift($new_roles, $view_role_id);
        }

        // Create the user and attach roles (limited to current user's maxRole)
        $user = User::create($input);
        foreach ($new_roles as $r) {
            if ($thisUser->maxRole() >= $r) {
                $user->roles()->attach($r);
            }
        }
        $user->load(['institution:id,name']);

        // Set current consortium name if there are more than 1 active in this system
        $consortia = \App\Consortium::where('is_active',1)->get();
        $con_name = "";
        if ($consortia->count() > 1) {
            $current = $consortia->where('ccp_key',session('con_key'))->first();
            $con_name = ($current) ? $current->name : "";
        }

        // Send email to the user about their new account, but fail silently
        $data = array('name' => $user->name, 'password' => $input['password']);
        try {
            Mail::to($input['email'])->send(new \App\Mail\NewUser($con_name,$data));
        } catch (\Exception $e) { }

        // Setup array to hold new user to match index fields
        $_roles = "";
        $new_user = $user->toArray();
        $new_user['inst_name'] = $user->institution->name;
        $new_user['status'] = ($user->is_active) ? "Active" : "Inactive";
        $new_user['user_role'] = $user->maxRoleName();
        if ($user->inst_id!=1 && $new_user['user_role'] == 'Admin') {
            $new_user['user_role'] = "Local Admin";
        } else if ($user->inst_id==1 && $new_user['user_role'] != 'ServerAdmin') {
            $new_user['user_role'] = 'Consortium '.$new_user['user_role'];
        }
        $new_user['permission'] = $max_role;
        $new_user['roles'] = $user->allRoles();
        $new_user['fiscalYr'] = ($user->fiscalYr) ? $user->fiscalYr : config('ccplus.fiscalYr');
        $new_user['can_edit'] = $user->canManage();
        $new_user['can_delete'] = $user->canManage();

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
        // Set user_role to hold user's highest access right
        $updated_user['user_role'] = $user->maxRoleName();
        if ($user->inst_id!=1 && $updated_user['user_role'] == 'Admin') {
            $updated_user['user_role'] = "Local Admin";
        } else if ($user->inst_id==1 && $updated_user['user_role'] != 'ServerAdmin') {
            $updated_user['user_role'] = 'Consortium '.$updated_user['user_role'];
        }    
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
     * Export user records from the database.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function export(Request $request)
    {
        $thisUser = auth()->user();

        // Admins access all, managers only access their inst, eveyone else gets an error
        abort_unless($thisUser->isAdmin(), 403);

        // Handle and validate inputs
        $filters = null;
        if ($request->filters) {
            $filters = json_decode($request->filters, true);
        } else {
            $filters = array('inst' => [], 'roles' => [], 'stat' => null);
        }
        $status_filter = null;
        if ($filters['stat'] != 'ALL') {
            $status_filter = ($filters['stat'] == 'Inactive') ? 0 : 1;
        }
        foreach ($filters as $key => $filt) {
            if ($key != 'stat') {
                if (count($filt)==0) $filters[$key] = null;
            }
        }
        $all_insts = false;

        // Get User records
        if ($thisUser->isConsoAdmin()) {
            $data = User::with('roles', 'institution:id,name')
                         ->when($filters['inst'], function ($qry, $filters) {
                             return $qry->whereIn('inst_id', $filters['inst']);
                         })
                         ->when($status_filter, function ($qry, $status_filter) {
                             return $qry->where('is_active', '=', $status_filter);
                         })->get();

            // Check whether to include all institutions in the export (including those with no users)
            $all_insts = ($request->all_insts) ? json_decode($request->all_insts, true) : false;
            if ($all_insts) {
                $ids_with_users = $data->unique('inst_id')->pluck('inst_id')->toArray();
                $remaining_insts = Institution::whereNotIn('id',$ids_with_users)->get(['id','name']);
            }
        } else {    // is manager
            $data = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')
                         ->where('inst_id', '=', $thisUser->inst_id)->get();
        }

        // Apply roles filter if sent
        if ($filters['roles']) {
            $users = array();
            foreach ($data as $rec) {
                if (array_intersect($rec->roles->pluck('id')->toArray(), $filters['roles'])) {
                    $users[] = $rec;
                } 
            }
        } else {
            $users = $data;
        }

        // Setup some styles arrays
        $head_style = [
            'font' => ['bold' => true,],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,],
        ];
        $info_style = [
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                           ],
        ];

        // Setup the spreadsheet and build the static ReadMe sheet
        $spreadsheet = new Spreadsheet();
        $info_sheet = $spreadsheet->getActiveSheet();
        $info_sheet->setTitle('HowTo Import');
        $info_sheet->mergeCells('A1:E7');
        $info_sheet->getStyle('A1:E7')->applyFromArray($info_style);
        $info_sheet->getStyle('A1:E7')->getAlignment()->setWrapText(true);
        $top_txt  = "The Users tab represents a starting place for updating or importing settings. The table below\n";
        $top_txt .= "describes the datatype and order that the import expects. Any Import rows without an ID value\n";
        $top_txt .= "in column 'A' will be ignored. If values are missing/invalid for a column, but not required,\n";
        $top_txt .= "they will be set to the 'Default'. Any header row or columns beyond 'H' will be ignored.\n\n";
        $top_txt .= "Once the data sheet contains everything to be updated or inserted, save the sheet as a CSV\n";
        $top_txt .= "and import it into CC-Plus.";
        $info_sheet->setCellValue('A1', $top_txt);
        $info_sheet->getStyle('A9')->applyFromArray($head_style);
        $info_sheet->setCellValue('A9', "NOTE:");
        $info_sheet->mergeCells('B9:E11');
        $info_sheet->getStyle('B9:E11')->applyFromArray($info_style);
        $info_sheet->getStyle('B9:E11')->getAlignment()->setWrapText(true);
        $note_txt  = "When performing full-replacement imports, be VERY careful about changing or overwriting\n";
        $note_txt .= "existing ID value(s). The best approach is to add to, or modify, a full export to ensure\n";
        $note_txt .= "that existing user IDs are not accidentally overwritten.";
        $info_sheet->setCellValue('B9', $note_txt);
        $info_sheet->getStyle('A13:E13')->applyFromArray($head_style);
        $info_sheet->setCellValue('A13', 'Column Name');
        $info_sheet->setCellValue('B13', 'Data Type');
        $info_sheet->setCellValue('C13', 'Description');
        $info_sheet->setCellValue('D13', 'Required');
        $info_sheet->setCellValue('E13', 'Default');
        $info_sheet->setCellValue('A14', 'Id');
        $info_sheet->setCellValue('B14', 'Integer');
        $info_sheet->setCellValue('C14', 'Unique CC-Plus User ID');
        $info_sheet->setCellValue('D14', 'Yes');
        $info_sheet->setCellValue('A15', 'Email');
        $info_sheet->setCellValue('B15', 'String');
        $info_sheet->setCellValue('C15', 'Email address');
        $info_sheet->setCellValue('D15', 'Yes');
        $info_sheet->setCellValue('A16', 'Password');
        $info_sheet->setCellValue('B16', 'String');
        $info_sheet->setCellValue('C16', 'Password (will be encrypted) - REQUIRED for new users');
        $info_sheet->setCellValue('D16', 'Sometimes');
        $info_sheet->setCellValue('E16', 'NULL - no change');
        $info_sheet->setCellValue('A17', 'Name');
        $info_sheet->setCellValue('B17', 'String');
        $info_sheet->setCellValue('C17', 'Full name');
        $info_sheet->setCellValue('D17', 'No');
        $info_sheet->setCellValue('E17', 'NULL');
        $info_sheet->setCellValue('A18', 'Phone');
        $info_sheet->setCellValue('B18', 'String');
        $info_sheet->setCellValue('C18', 'Phone number');
        $info_sheet->setCellValue('D18', 'No');
        $info_sheet->setCellValue('E18', 'NULL');
        $info_sheet->setCellValue('A19', 'Active');
        $info_sheet->setCellValue('B19', 'String (Y or N)');
        $info_sheet->setCellValue('C19', 'Make the user active?');
        $info_sheet->setCellValue('D19', 'No');
        $info_sheet->setCellValue('E19', 'Y');
        $info_sheet->setCellValue('A20', 'Role(s)');
        $info_sheet->setCellValue('B20', 'Comma-separated strings');
        $info_sheet->setCellValue('C20', 'Consortium Admin, Local Admin, User, or Consortium Viewer');
        $info_sheet->setCellValue('D20', 'No');
        $info_sheet->setCellValue('E20', 'User');
        $info_sheet->setCellValue('A21', 'Institution ID');
        $info_sheet->setCellValue('B21', 'Integer');
        $info_sheet->setCellValue('C21', 'Unique CC-Plus Institution ID (1=Staff)');
        $info_sheet->setCellValue('D21', 'No');
        $info_sheet->setCellValue('E21', '1 (Staff)');

        // Set row height and auto-width columns for the sheet
        for ($r = 1; $r < 25; $r++) {
            $info_sheet->getRowDimension($r)->setRowHeight(15);
        }
        $info_columns = array('A','B','C','D','E');
        foreach ($info_columns as $col) {
            $info_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Load the user data into a new sheet
        $users_sheet = $spreadsheet->createSheet();
        $users_sheet->setTitle('Users');
        $users_sheet->getRowDimension('1')->setRowHeight(15);
        $users_sheet->setCellValue('A1', 'Id');
        $users_sheet->setCellValue('B1', 'Email');
        $users_sheet->setCellValue('C1', 'Password');
        $users_sheet->setCellValue('D1', 'Name');
        $users_sheet->setCellValue('E1', 'Phone');
        $users_sheet->setCellValue('F1', 'Active');
        $users_sheet->setCellValue('G1', 'Role(s)');
        if ($thisUser->isConsoAdmin()) {
            $users_sheet->setCellValue('H1', 'Institution ID');
            $users_sheet->setCellValue('I1', 'LEAVE BLANK');
            $users_sheet->setCellValue('J1', 'Institution');
        }
        $row = 2;
        foreach ($users as $user) {
            if ($user->email == "ServerAdmin") {
                continue;
            }
            $users_sheet->getRowDimension($row)->setRowHeight(15);
            $users_sheet->setCellValue('A' . $row, $user->id);
            $users_sheet->setCellValue('B' . $row, $user->email);
            $users_sheet->setCellValue('D' . $row, $user->name);
            $users_sheet->setCellValue('E' . $row, $user->phone);
            $_stat = ($user->is_active) ? "Y" : "N";
            $users_sheet->setCellValue('F' . $row, $_stat);
            $_roles = "";
            foreach ($user->roles as $role) {
                $_name = $role->name;
                if ($_name == "User") continue;
                if ($_name == "Manager") $_name = "Local Admin";
                if ($_name == 'Admin') $_name = "Consortium Admin";
                if ($_name == 'Viewer') $_name = "Consortium Viewer";
                $_roles .= $_name . ", ";
            }
            $_roles = rtrim(trim($_roles), ',');
            $users_sheet->setCellValue('G' . $row, $_roles);
            if ($thisUser->isConsoAdmin()) {
                $users_sheet->setCellValue('H' . $row, $user->inst_id);
                $_inst = ($user->inst_id == 1) ? "Staff" : $user->institution->name;
                $users_sheet->setCellValue('J' . $row, $_inst);
            }
            $row++;
        }

        // If we're including all insitutions, add them at the end 
        if ($all_insts) {
            foreach ($remaining_insts as $inst) {
                $users_sheet->getRowDimension($row)->setRowHeight(15);
                $users_sheet->setCellValue('H' . $row, $inst->id);
                $_name = ($user->inst_id == 1) ? "Staff" : $inst->name;
                $users_sheet->setCellValue('J' . $row, $_name);
                $row++;
            }
        }

        // Auto-size the columns
        $user_columns = array('A','B','C','D','E','F','G','H','I','J');
        foreach ($user_columns as $col) {
            $users_sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Give the file a meaningful filename
        if ($thisUser->isConsoAdmin()) {
            $fileName = "CCplus_" . session('ccp_con_key', '') . "_Users.xlsx";
        } else {
            $fileName = "CCplus_" . preg_replace('/ /', '', $thisUser->institution->name) . "_Users.xlsx";
        }

        // redirect output to client browser as .xslx
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename=' . $fileName);
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
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
        abort_unless(auth()->user()->isConsoAdmin(), 403);

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
            if ($row[0] == "" || !is_numeric($row[0]) || sizeof($row) < 7) {
                continue;
            }
            $cur_user_id = intval($row[0]);

            // Update/Add the user data/settings
            // Check ID and name columns for silliness or errors
            $_email = trim($row[1]);
            if ($_email == "ServerAdmin") {    // Disallow import of ServerAdmin
                continue;
            }
            $current_user = $users->where("id", "=", $cur_user_id)->first();
            if (!is_null($current_user)) {      // found existing ID
                if (strlen($_email) < 1) {       // If import email empty, use current value
                    $_name = trim($current_user->email);
                } else {                        // trap changing an email to one that already exists
                    $existing_user = $users->where("name", "=", $_email)->first();
                    if (!is_null($existing_user)) {
                        $_email = trim($current_user->email);     // override, use current - no change
                    }
                }
            } else {        // existing ID not found, try to find by name
                $current_user = $users->where("email", "=", $_email)->first();
                if (!is_null($current_user)) {
                    $_email = trim($current_user->email);
                }
            }

            // If we're creating a user, but the password field is empty, skip it
            if (is_null($current_user) && $row[2] == '') {
                $num_skipped++;
                continue;
            }

            // Dont store/create anything if email is still empty
            if (strlen($_email) < 1) {
                $num_skipped++;
                continue;
            }

            // Enforce defaults
            $_name = ($row[3] == '') ? $_email : $row[3];
            $_phone = ($row[4] == '') ? $_email : $row[4];
            $_active = ($row[5] == 'N') ? 0 : 1;
            $_inst = ($row[7] == '') ? 0 : intval($row[7]);
            $user_inst = $institutions->where('id', $_inst)->first();
            if (!$user_inst) {
                $num_skipped++;
                continue;
            }

            // Put user data columns into an array
            $_user = array('id' => $cur_user_id, 'email' => $_email, 'name' => $_name, 'phone' => $_phone,
                           'is_active' => $_active, 'inst_id' => $_inst);

            // Only include password if it has a value
            if ($row[2] != '') {
                $_user['password'] = $row[2];
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

            // Set roles
            $import_roles = preg_replace('/, /', ',',$row[6]);
            $_roles = preg_split('/,/', $import_roles);
            $role_ids = array();
            $sawUser = false;
            foreach ($_roles as $r) {
                $rstr = ucwords(trim($r));
                if ($rstr == "ServerAdmin") continue;
                if ($rstr == 'User') $sawUser = true;
                if ($rstr == "Local Admin") $rstr = "Manager";
                if ($rstr == "Consortium Admin") $rstr = "Admin";
                if ($rstr == "Consortium Viewer") $rstr = "Viewer";
                $role = $all_roles->where('name', '=', $rstr)->first();
                if ($role) {
                    $role_ids[] = $role->id;
                }
            }
            if (!$sawUser) {
                $role_ids[] = $all_roles->where('name', '=', 'User')->value('id');
            }
            $current_user->roles()->detach();
            foreach ($role_ids as $_r) {
                $current_user->roles()->attach($_r);
            }
        }

        // Recreate the users list (like index does) to be returned to the caller
        $server_admin = config('ccplus.server_admin');
        $user_data = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')
                         ->where('email', '<>', $server_admin)->get();
        $users = $user_data->map( function($user) use ($all_roles, $viewRoleId) {
            $access_role_ids = $user->roles->where('id','<>',$viewRoleId)->pluck('id')->toArray();
            $user['role_string'] = $all_roles->where('id', max($access_role_ids))->first()->name;
            if ($user->inst_id!=1 && $user['role_string'] == 'Admin') {
                $user['role_string'] = "Local Admin";
            } else if ($user->inst_id==1 && $user['role_string'] != 'ServerAdmin') {
                $user['role_string'] = 'Consortium '.$user['role_string'];
            }

//NOTE:: THiS still needs work - howTo layout in the exportsheet?
            // Set user's roles as array of Role::Institution pairs
            $user['roles'] = $user->allRoles();
            
            // Set user's roles as array of IDs; exclude "User" for admins
            $user['roles'] = ($user_is_admin) ? $user->roles->where('id','>',1)->pluck('id')->toArray()
                                              : $user->roles->pluck('id')->toArray();
            return $user;
        });

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

        return response()->json(['result' => true, 'msg' => $msg, 'users' => $users]);
    }
}
