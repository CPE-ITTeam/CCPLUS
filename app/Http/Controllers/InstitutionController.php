<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\InstitutionGroup;
use App\Models\Connection;
use App\Models\Role;
use App\Models\Report;
use App\Models\Credential;
use App\Models\HarvestLog;
use App\Models\GlobalProvider;
use App\Models\ConnectionField;
use App\Models\Consortium;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InstitutionController extends Controller
{
    private $thisUser;

    /**
     * Display a listing of the resource.
     *
     * @param  String $role    // 'admin' or 'viewer'
     * @return JSON
     */
    public function index($role)
    {
        global $thisUser;
        $thisUser = auth()->user();

        // Set and confirm the role returning data for
        $type = ($role=='admin') ? 'admin' : 'viewer';
        abort_unless($type=='viewer' || $thisUser->hasAnyRole(['Admin']), 403);
    
        // Initialize some arrays/values
        $data = array();
        $limit_to_insts = [];
        $server_admin = config('ccplus.server_admin');

        // Setup options for filters
        $filter_options = array('statuses' => array('ALL','Active','Inactive'));

        // Include institution types
        $filter_options['type'] = InstitutionType::orderBy('name', 'ASC')->get(['id','name'])->toArray();
        $filter_options['groups'] = array();

        // Limit by institution based on users's role(s)
        if (!$thisUser->isConsoAdmin()) {
            $limit_to_insts = ($type == 'admin') ? $thisUser->adminInsts() : $thisUser->viewerInsts();
            if ($limit_to_insts === [1]) $limit_to_insts = [];
        }

        // Get institution records
        $inst_data = Institution::with('institutionGroups:id,name','credentials','institutionType')
                                ->when(count($limit_to_insts) > 0 , function ($qry) use ($limit_to_insts) {
                                    return $qry->whereIn('id', $limit_to_insts);
                                })->orderBy('name', 'ASC')->get();

        // Add group memberships
        $seen_group_ids = array();
        foreach ($inst_data as $rec) {
            $inst = array('id' => $rec->id, 'name' => $rec->name, 'is_active' => $rec->is_active,
                          'local_id' => $rec->local_id, 'fte' => $rec->fte, 'notes' => $rec->notes,
                          'type_id' => $rec->type_id, 'group_string' => '');
            $inst['status'] = ($rec->is_active) ? "Active" : "Inactive";
            $inst['group_ids'] = $rec->institutionGroups()->pluck('institution_group_id')->all();
            $inst['groups'] = $rec->institutionGroups->map(function ($group) {
                return [ 'id' => $group->id, 'name' => $group->name ];
            });
            foreach ($rec->institutionGroups as $group) {
                $inst['group_string'] .= ($inst['group_string'] == "") ? "" : ", ";
                $inst['group_string'] .= $group->name;
                if (!in_array($group->id,$seen_group_ids)) {
                    array_push($seen_group_ids, $group->id);
                    $filter_options['groups'][] = array('id' => $group->id, 'name' => $group->name);
                }
            }
            $harvest_count = $rec->credentials->whereNotNull('last_harvest')->count();
            $inst['type'] = ($rec->institutionType) ? $rec->institutionType->name : "(Not classified)";
            $inst['can_edit'] = $rec->canManage();
            $inst['can_delete'] = ($harvest_count > 0 || !$rec->canManage()) ? false : true;
            $inst['role'] = $this->userRole($rec->id);
            $data[] = $inst;
        }
        // sort group options by name
        array_multisort(array_column($filter_options['groups'], 'name'), SORT_ASC, $filter_options['groups']);

        return response()->json(['records' => $data, 'options' => $filter_options], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        global $thisUser;

        $thisUser = auth()->user();
        if (!$thisUser->isConsoAdmin()) {
            return response()->json(['result' => false, 'msg' => 'Create failed (403) - Forbidden']);
        }

        $this->validate($request, [ 'name' => 'required' ]);
        $input = $request->all();
        $create_fields = Arr::except($input, array('status','creds','groups','type'));
        $create_fields['type_id'] = (isset($input['type'])) ? $input['type'] : 1;
        $create_fields['is_active'] = (isset($input['is_active'])) ? $input['is_active'] : 0;
        if (isset($input['status'])) {
            $create_fields['is_active'] = ($input['status'] == 'Active') ? 1 : 0;
        }
        
        // Make sure that local ID is unique if not set null
        $localID = (isset($input['local_id'])) ? trim($input['local_id']) : null;
        if ($localID && strlen($localID) > 0) {
            $existing_inst = Institution::where('local_id',$localID)->first();
            if ($existing_inst) {
                return response()->json(['result' => false,
                                         'msg' => 'Local ID already assigned to another institution.']);
            }
            $create_fields['local_id'] = $localID;
        } else {
            $create_fields['local_id'] = null;
        }
        $new_inst = Institution::create($create_fields);
        $new_id = $new_inst->id;

        // Attach groups and build a string of the names
        if (isset($input['groups'])) {
            $input_ids = array_column($input['groups'],'id');
            foreach ($input_ids as $g) {
                $new_inst->institutionGroups()->attach($g);
            }
        }

        // If requested, create a credentials to give a "starting point" for connecting it later
        $stub = (isset($input['creds'])) ? $input['creds'] : 0;
        if ($stub) {
            $connections = Connection::with('globalProv')->where('inst_id',1)->where('is_active',1)->get();
            foreach ($connections as $cnx) {
                if ($cnx->globalProv->is_active == 0) continue;
                $credential = new Credential;
                $credential->inst_id = $new_id;
                $credential->prov_id = $provider->global_id;
                // Mark required conenction fields
                foreach ($cnx->globalProv->connectionFields() as $fld) {
                    $credential->{$fld['name']} = ($fld['required']) ? "-required-" : null;
                }
                $credential->status = "Incomplete";
                $credential->save();
            }
        }

        // Setup a return object that matches what index does (above)
        $new_inst->load('institutionGroups:id,name','credentials','institutionType');
        $data = array('id' => $new_id, 'name' => $new_inst->name, 'is_active' => $new_inst->is_active,
                      'local_id' => $new_inst->local_id, 'fte' => $new_inst->fte, 'notes' => $new_inst->notes,
                      'type_id' => $new_inst->type_id, 'group_string' => '');
        $data['status'] = ($new_inst->is_active) ? "Active" : "Inactive";
        $data['groups'] = $new_inst->institutionGroups()->pluck('institution_group_id')->all();
        foreach ($new_inst->institutionGroups as $group) {
            $data['group_string'] .= ($data['group_string'] == "") ? "" : ", ";
            $data['group_string'] .= $group->name;
        }
        $data['type'] = ($new_inst->institutionType) ? $new_inst->institutionType->name : "(Not classified)";
        $data['can_edit'] = true;
        $data['can_delete'] = true;
        $data['role'] = $this->userRole($new_inst->id);

        return response()->json(['result' => true, 'msg' => 'Institution successfully created',
                                 'record' => $data]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Institution $institution)
    {
        global $thisUser;
        $thisUser = auth()->user();

        if (!$institution->canManage()) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }
        $was_active = $institution->is_active;

        // Validate form inputs
        $input = $request->all();
        // Status can be set with 'is_active' (0/1) or 'status' (string)
        if (isset($input['status'])) {
            $input['is_active'] = ($input['status'] == 'Active') ? 1 : 0;
        }
        if (!isset($input['is_active'])) {
            return response()->json(['result' => false, 'msg' => 'Status argument is required for update']);
        }

        // if only updating is_active, do it now
        if (count($input) == 2 && isset($input['is_active'])) {
            $institution->update([ 'is_active' => $input['is_active'] ]);
            return response()->json(['result' => true]);
        }

        // Make sure that local ID is unique if not set null
        if (isset($input['local_id'])) {
            $newID = trim($input['local_id']);
            if ($institution->local_id != $newID) {
                if (strlen($newID) > 0) {
                    $existing_inst = Institution::where('local_id',$newID)->first();
                    if ($existing_inst) {
                        return response()->json(['result' => false,
                                                 'msg' => 'Local ID already assigned to another institution.']);
                    }
                    $input['local_id'] = $newID;
                } else {
                    $input['local_id'] = null;
                }
            }
        }

       // if not consoAdmin (user is a local-admin for their institution), only update name, FTE and notes
        if (!$thisUser->isConsoAdmin()) {
            if ( isset($input['name']) || isset($input['fte']) || isset($input['notes']) ) {
                $limitedFields = array();
                if (isset($input['name'])) {
                    $limitedFields['name'] = $input['name'];
                }
                if (isset($input['fte'])) {
                    $limitedFields['fte'] = $input['fte'];
                }
                if (isset($input['notes'])) {
                    $limitedFields['notes'] = $input['notes'];
                }
                $institution->update($limitedFields);
            }

       // Admins update everything from $input
        } else {
            // Update the record and assign groups
            $institution->update($input);
            $institution->load('institutionGroups');

            if (isset($input['groups'])) {
                $existing_group_ids = $institution->institutionGroups->pluck('id')->toArray();
                $input_ids = array_column($input['groups'],'id');
                $detach_ids = array_diff($existing_group_ids,$input_ids);
                $attach_ids = array_diff($input_ids,$existing_group_ids);
                // Add new groups
                foreach ($attach_ids as $g) {
                    $institution->institutionGroups()->attach($g);
                }
                // Detach cleared groups
                foreach ($detach_ids as $g) {
                    $institution->institutionGroups()->detach($g);
                }
            }
            $institution->unsetRelation('institutionGroups'); // we'll add it back below

            // If is_active is changing, check and update related credentials
            if ($was_active != $institution->is_active) {
                $this->updateCredentials($institution->id, $institution->is_active);
            }
        }

        // Load related models
        $institution->load('institutionGroups:id,name','institutionType', 'credentials');

        // Build return record to match what index() passed
        $inst = array('id' => $institution->id, 'name' => $institution->name, 'is_active' => $institution->is_active,
                      'local_id' => $institution->local_id, 'fte' => $institution->fte, 'notes' => $institution->notes,
                      'type_id' => $institution->type_id, 'group_string' => '');
        $inst['status'] = ($institution->is_active) ? "Active" : "Inactive";
        $inst['groups'] = $institution->institutionGroups()->pluck('institution_group_id')->all();
        foreach ($institution->institutionGroups as $group) {
            $inst['group_string'] .= ($inst['group_string'] == "") ? "" : ", ";
            $inst['group_string'] .= $group->name;
        }
        $harvest_count = $institution->credentials->whereNotNull('last_harvest')->count();
        // $inst['type_string'] = ($institution->institutionType) ? $institution->institutionType->name : "(Not classified)";
        $inst['type'] = ($institution->institutionType) ? $institution->institutionType->name : "(Not classified)";
        $inst['can_edit'] = $institution->canManage();
        $inst['can_delete'] = ($harvest_count > 0 || !$institution->canManage()) ? false : true;
        $inst['role'] = $this->userRole($institution->id);

        return response()->json(['result' => true, 'msg' => 'Settings successfully updated', 'record' => $inst]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->hasRole("Admin")) {
            return response()->json(['result' => false, 'msg' => 'Update failed (403) - Forbidden']);
        }

        try {
            \DB::beginTransaction();

            // 💡 REFACTOR 1: Securely delete the institution row directly out of the active tenant connection schema
            $exists = \DB::table('institutions')->where('id', $id)->exists();
            if (!$exists) {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Institution record not found in the active environment.'], 200);
            }

            // Remove attached pivot dependencies safely first
            \DB::table('institution_institution_group')->where('institution_id', $id)->delete();
            
            // Execute true tenant-bound row deletion
            \DB::table('institutions')->where('id', $id)->delete();

            \DB::commit();

            // 💡 REFACTOR 2: Hydrate fresh dataset collection exactly like your import/bulk actions to repaint Vue instantly
            $inst_data = array();
            $updated_institutions = \DB::table('institutions')->orderBy('name', 'ASC')->get();
            
            foreach ($updated_institutions as $inst) {
                $instGroups = \DB::table('institution_institution_group')
                    ->join('institutiongroups', 'institution_institution_group.institution_group_id', '=', 'institutiongroups.id')
                    ->where('institution_id', $inst->id)
                    ->select('institutiongroups.id', 'institutiongroups.name')
                    ->get();

                $inst->group_string = ""; 
                $inst->groups = $instGroups->pluck('id')->all();
                foreach ($instGroups as $group) {
                    $inst->group_string .= ($inst->group_string == "") ? "" : ", ";
                    $inst->group_string .= $group->name;
                }
                
                // Keep the array brackets appended correctly!
                $inst_data[] = (array) $inst; 
            }

            return response()->json([
                'result'    => true, 
                'msg'       => 'Institution successfully deleted', 
                'inst_data' => $inst_data
            ], 200);

        } catch (\Exception $ex) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => 'Server Deletion Error: ' . $ex->getMessage()], 200);
        }
    }


    /**
     * Bulk operations from the U/I.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function bulk(Request $request)
    {
        try {
            global $thisUser;
            $thisUser = auth()->user();
            $consoAdmin = $thisUser->isConsoAdmin();

            $this->validate($request, ['ids' => 'required', 'action' => 'required']);
            $input = $request->all();

            // 💡 REFACTOR: Allow 'Assign Type' alias passed from the Vue UI block
            $isAssignType = ($input['action'] == 'Set Institution Type' || $input['action'] == 'Assign Type');

            if ( ($input['action'] == 'Create New Group' && !isset($input['name'])) ||
                 ($input['action'] == 'Add to Existing Group' && !isset($input['group_id'])) ||
                 ($isAssignType && !isset($input['type_id'])) ) {
                return response()->json(['result' => false, 'msg' => 'Missing input values'], 200);
            }

            $adminInsts = $thisUser->adminInsts();
            $instIDs = ($consoAdmin) ? $input['ids'] : array_intersect($adminInsts, $input['ids']);

            if (!$consoAdmin && ($input['action']=='Set Active' || $input['action']=='Set Inactive' || $input['action']=='Delete')) {
                return response()->json(['result' => false, 'msg' => 'No Authorization'], 200);
            }

            \DB::beginTransaction();

            $msg = '';
            $extraData = array(); 

            if ($input['action'] == 'Set Active' || $input['action'] == 'Set Inactive') {
                $new_is_active = ($input['action'] == 'Set Active') ? 1 : 0;
                \DB::table('institutions')->whereIn('id', $instIDs)->update(['is_active' => $new_is_active, 'updated_at' => now()]);
                foreach ($instIDs as $id) { $this->updateCredentials($id, $new_is_active); }
                
                \DB::commit();
                $msg = 'Status Updated';

            } else if ($input['action'] == 'Create New Group' || $input['action'] == 'Add to Existing Group') {
                $user_id = ($consoAdmin) ? null : $thisUser->id;
                $groupId = null; $groupName = '';

                if ($input['action'] == 'Create New Group') {
                    $exists = \DB::table('institutiongroups')->where('user_id', $user_id)->where('name', $input['name'])->first();
                    if ($exists) { \DB::rollBack(); return response()->json(['result' => false, 'msg' => 'Group exists'], 200); }
                    
                    $groupId = \DB::table('institutiongroups')->insertGetId(['name' => $input['name'], 'user_id' => $user_id, 'created_at' => now(), 'updated_at' => now()]);
                    $groupName = $input['name'];
                } else {
                    $group = \DB::table('institutiongroups')->where('user_id', $user_id)->where('id', $input['group_id'])->first();
                    if (!$group) { \DB::rollBack(); return response()->json(['result' => false, 'msg' => 'Group not found'], 200); }
                    $groupId = $group->id; $groupName = $group->name;
                }

                foreach ($instIDs as $inst_id) {
                    $already = \DB::table('institution_institution_group')->where('institution_id', $inst_id)->where('institution_group_id', $groupId)->exists();
                    if (!$already) { \DB::table('institution_institution_group')->insert(['institution_id' => $inst_id, 'institution_group_id' => $groupId]); }
                }
                
                \DB::commit();
                $msg = 'Group Assigned';
                $extraData['group'] = ['id' => $groupId, 'name' => $groupName];

            } else if ($isAssignType) {
                // 💡 REFACTOR: Force update on type configurations
                \DB::table('institutions')->whereIn('id', $instIDs)->update(['type_id' => $input['type_id'], 'updated_at' => now()]);
                
                // Fetch the name of the assigned type so the UI can draw the label cell immediately
                $typeName = \DB::table('institutiontypes')->where('id', $input['type_id'])->value('name') ?: 'Unknown';
                
                \DB::commit();
                $msg = 'Type Assignment Successful';
                
                // Inject metadata keys that composable blocks expect to clear dialog hooks
                $extraData['newType'] = ['id' => $input['type_id'], 'name' => $typeName];
            
            } else {
                \DB::rollBack();
                return response()->json(['result' => false, 'msg' => 'Unrecognized action'], 200);
            }

            // Generate fresh datasets exactly like the import process to repaint the UI grid automatically
            // 💡 REFACTOR: Generate fresh datasets with both group and type strings to repaint the UI grid immediately
            $inst_data = array();
            $updated_institutions = \DB::table('institutions')->orderBy('name', 'ASC')->get();
            
            // Pre-fetch types map to save database query amplification cycles in the loop
            $typesMap = \DB::table('institutiontypes')->pluck('name', 'id')->all();

            foreach ($updated_institutions as $inst) {
                $instGroups = \DB::table('institution_institution_group')
                    ->join('institutiongroups', 'institution_institution_group.institution_group_id', '=', 'institutiongroups.id')
                    ->where('institution_id', $inst->id)
                    ->select('institutiongroups.id', 'institutiongroups.name')
                    ->get();

                $inst->group_string = ""; 
                $inst->groups = $instGroups->pluck('id')->all();
                foreach ($instGroups as $group) {
                    $inst->group_string .= ($inst->group_string == "") ? "" : ", ";
                    $inst->group_string .= $group->name;
                }

                // 💡 FIX: Inject the missing type name string mapping onto the object so Vue can read and paint it!
                $inst->type = isset($typesMap[$inst->type_id]) ? $typesMap[$inst->type_id] : 'Unknown';
                
                // 💡 FIX: Keep the brackets to append to the list array collection properly
                $inst_data[] = (array) $inst; 
            }

            // Merge metadata properties dynamically into response payload
            $responsePayload = array_merge([
                'result'       => true,
                'msg'          => $msg,
                'affectedIds'  => $instIDs,
                'inst_data'    => $inst_data,
                'institutions' => $inst_data 
            ], $extraData);

            return response()->json($responsePayload, 200);


        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => 'Server Error: ' . $e->getMessage()], 200);
        }
    }


    
        /**
     * Import institutions (including credentials) from a CSV file to the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        try {
            abort_unless(auth()->user()->hasRole('Admin'), 403);
            $this->validate($request, ['csvfile' => 'required']);
            
            if (!$request->hasFile('csvfile')) {
                return response()->json(['result' => false, 'msg' => 'Error accessing CSV import file']);
            }

            $path = $request->file("csvfile")->getRealPath();
            $handle = fopen($path, 'r');
            if (!$handle) {
                return response()->json(['result' => false, 'msg' => 'Unable to read file stream.']);
            }

            $rawHeaders = fgetcsv($handle);
            $headers = array_map(function($header) {
                $cleaned = preg_replace('/^[\x{FEFF}\x{200B}]+/u', '', $header);
                return trim(preg_replace('/\s+/', ' ', $cleaned));
            }, $rawHeaders);

            // 💡 Look how clean! The middleware already set the active DB connection globally
            $groups = \DB::table('institutiongroups')->get();
            $inst_skipped = 0; $inst_updated = 0; $inst_created = 0;

            \DB::beginTransaction();

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row) || count($row) < count($headers)) continue;
                $mappedRow = array_combine($headers, array_slice($row, 0, count($headers)));

                $raw_id     = isset($mappedRow['CC+ System ID']) ? trim($mappedRow['CC+ System ID']) : '';
                $raw_local  = isset($mappedRow['Local ID']) ? trim($mappedRow['Local ID']) : '';
                $raw_name   = isset($mappedRow['Institution Name']) ? trim($mappedRow['Institution Name']) : '';
                $raw_status = isset($mappedRow['Status']) ? trim($mappedRow['Status']) : '';
                $raw_fte    = isset($mappedRow['FTE']) ? trim($mappedRow['FTE']) : '';
                $raw_group  = isset($mappedRow['Group Assignment(s)']) ? trim($mappedRow['Group Assignment(s)']) : '';
                $raw_notes  = isset($mappedRow['Notes']) ? trim($mappedRow['Notes']) : '';

                $targetId = ($raw_id !== '') ? intval($raw_id) : null;
                $localID  = ($raw_local !== '') ? $raw_local : null;

                if ($targetId === 1 || (!$targetId && !$localID) || strlen($raw_name) < 1) {
                    $inst_skipped++; continue;
                }

                $_active = ($raw_status === 'Inactive' || $raw_status === 'N') ? 0 : 1;
                $_fte    = ($raw_fte === '') ? null : intval($raw_fte);
                $_notes  = ($raw_notes === '') ? null : $raw_notes;

                $exists = $targetId ? \DB::table('institutions')->where('id', $targetId)->exists() : false;

                if ($exists) {
                    \DB::table('institutions')->where('id', $targetId)->update([
                        'name' => $raw_name, 'is_active' => $_active, 'local_id' => $localID, 'fte' => $_fte, 'notes' => $_notes, 'updated_at' => now()
                    ]);
                    $inst_updated++;
                } else {
                    $insertData = [
                        'name' => $raw_name, 'is_active' => $_active, 'local_id' => $localID, 'fte' => $_fte, 'notes' => $_notes, 'type_id' => 1, 'created_at' => now(), 'updated_at' => now()
                    ];
                    if ($targetId) $insertData['id'] = $targetId;
                    \DB::table('institutions')->insert($insertData);
                    $inst_created++;
                }

                if ($targetId) {
                    \DB::table('institution_institution_group')->where('institution_id', $targetId)->delete();
                    if ($raw_group !== '') {
                        $_group_list = preg_split('/,/', $raw_group);
                        foreach ($_group_list as $csv_value) {
                            $_id = intval(trim($csv_value));
                            if ($_id > 0 && $groups->where('id', $_id)->first()) {
                                \DB::table('institution_institution_group')->insert(['institution_id' => $targetId, 'institution_group_id' => $_id]);
                            }
                        }
                    }
                }
            }
            fclose($handle);
            \DB::commit();

            $inst_data = array();
            $updated_institutions = \DB::table('institutions')->orderBy('name', 'ASC')->get();
            foreach ($updated_institutions as $inst) {
                $instGroups = \DB::table('institution_institution_group')
                    ->join('institutiongroups', 'institution_institution_group.institution_group_id', '=', 'institutiongroups.id')
                    ->where('institution_id', $inst->id)->select('institutiongroups.id', 'institutiongroups.name')->get();

                $inst->group_string = ""; $inst->groups = $instGroups->pluck('id')->all();
                foreach ($instGroups as $group) {
                    $inst->group_string .= ($inst->group_string == "") ? "" : ", ";
                    $inst->group_string .= $group->name;
                }
                $inst_data[] = (array) $inst; // Fixed appending array logic safely!
            }

            return response()->json(['result' => true, 'msg' => "Import successful: {$inst_updated} updated, {$inst_created} added", 'inst_data' => $inst_data]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['result' => false, 'msg' => 'Server Error: ' . $e->getMessage()]);
        }
    }




    /**
     * Update credentials when an institution is_active setting is changed
     *
     * @param  Integer  $instId
     * @param  Integer  $active (0/1)
     * @return String
     */
    private function updateCredentials($instId, $active) {
        // If is_active is changing, check and update related credentials
        $credentials = Credential::with('provider')->where('inst_id',$instId)->get();
        foreach ($credentials as $credential) {
            if ($active == 1) {
                if ($credential->status != 'Disabled') {
                    $credential->update(['status' => 'Suspended']);
                }
            // Went from Inactive to Active
            } else {
                $credential->resetStatus();
            }
        }
    }

    /**
     * Return an string for the current user's role(s)
     */
    private function userRole($instId) {

        global $thisUser;
        if ( $thisUser->isServerAdmin() ) {
            return "ServerAdmin";
        } else if ( $thisUser->hasRole('Admin',1) ) {
            return "Consortium Admin";
        } else if ( $thisUser->hasRole('Admin',$instId) ) {
            return "Admin";
        } else if ( $thisUser->hasRole('Viewer',1) ) {
            return "Consortium Viewer";
        } else if ( $thisUser->hasRole('Viewer',$instId) ) {
            return "Viewer";
        }
        return "None";
    }
}