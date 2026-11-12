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

class InstitutionController extends BaseController

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
        $type = ($role == 'admin') ? 'admin' : 'viewer';
        abort_unless($type == 'viewer' || $thisUser->hasAnyRole(['Admin']), 403);

        // Initialize some arrays/values
        $data = array();
        $limit_to_insts = [];
        $server_admin = config('ccplus.server_admin');

        // Setup options for filters
        $filter_options = array('statuses' => array('ALL', 'Active', 'Inactive'));

        // Include institution types
        $filter_options['type'] = InstitutionType::orderBy('name', 'ASC')->get(['id', 'name'])->toArray();
        $filter_options['groups'] = array();

        // Limit by institution based on users's role(s)
        if (!$thisUser->isConsoAdmin()) {
            $limit_to_insts = ($type == 'admin') ? $thisUser->adminInsts() : $thisUser->viewerInsts();
            if ($limit_to_insts === [1]) $limit_to_insts = [];
        }

        // Get institution records
        $inst_data = Institution::with('institutionGroups:id,name', 'credentials', 'institutionType')
            ->when(count($limit_to_insts) > 0, function ($qry) use ($limit_to_insts) {
                return $qry->whereIn('id', $limit_to_insts);
            })->orderBy('name', 'ASC')->get();

        // Add group memberships
        $seen_group_ids = array();
        foreach ($inst_data as $rec) {
            $inst = array(
                'id' => $rec->id,
                'name' => $rec->name,
                'is_active' => $rec->is_active,
                'local_id' => $rec->local_id,
                'fte' => $rec->fte,
                'notes' => $rec->notes,
                'type_id' => $rec->type_id,
                'group_string' => ''
            );
            $inst['status'] = ($rec->is_active) ? "Active" : "Inactive";
            $inst['group_ids'] = $rec->institutionGroups()->pluck('institution_group_id')->all();
            $inst['groups'] = $rec->institutionGroups->map(function ($group) {
                return ['id' => $group->id, 'name' => $group->name];
            });
            foreach ($rec->institutionGroups as $group) {
                $inst['group_string'] .= ($inst['group_string'] == "") ? "" : ", ";
                $inst['group_string'] .= $group->name;
                if (!in_array($group->id, $seen_group_ids)) {
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
     */
    public function store(Request $request)
    {
        global $thisUser;
        $thisUser = auth()->user();

        if (!$thisUser->isConsoAdmin()) {
            return $this->respondError('Create failed (403) - Forbidden');
        }

        $this->validate($request, ['name' => 'required']);
        $input = $request->all();

        $this->normalizeStatus($input, 0);
        $create_fields = $this->cleanPayload($input, ['creds', 'groups', 'group_ids', 'group_string', 'type']);
        $create_fields['fte'] = (isset($input['fte']) && is_numeric($input['fte'])) ? (int) $input['fte'] : null;
        $create_fields['type_id'] = (isset($input['type']) && is_numeric($input['type'])) ? (int) $input['type'] : 1;

        // Make sure that local ID is unique if not set null
        $localID = isset($input['local_id']) ? trim($input['local_id']) : null;
        if ($localID && strlen($localID) > 0) {
            if (Institution::where('local_id', $localID)->exists()) {
                return $this->respondError('Local ID already assigned to another institution.');
            }
            $create_fields['local_id'] = $localID;
        } else {
            $create_fields['local_id'] = null;
        }

        $new_inst = Institution::create($create_fields);
        $new_id = $new_inst->id;

        if (!empty($input['groups'])) {
            $this->syncRelationIds($new_inst->institutionGroups(), $input['groups']);
        }

        // If requested, create credentials to give a "starting point" for connecting it later
        $stub = isset($input['creds']) && ($input['creds'] === 'Active' || $input['creds'] == 1 || $input['creds'] === true);
        if ($stub) {
            $connections = Connection::with('globalProv')->where('inst_id', 1)->where('is_active', 1)->get();
            foreach ($connections as $cnx) {
                if (!$cnx->globalProv || $cnx->globalProv->is_active == 0) continue;
                $credential = new Credential;
                $credential->inst_id = $new_id;
                $credential->prov_id = $cnx->prov_id;
                foreach ($cnx->globalProv->connectionFields() as $fld) {
                    $credential->{$fld['name']} = ($fld['required']) ? "-required-" : null;
                }
                $credential->status = "Incomplete";
                $credential->save();
            }
        }

        // Setup a return object that matches what index does
        $new_inst->load('institutionGroups:id,name', 'credentials', 'institutionType');
        $data = [
            'id'           => $new_id,
            'name'         => $new_inst->name,
            'is_active'    => $new_inst->is_active,
            'local_id'     => $new_inst->local_id,
            'fte'          => $new_inst->fte,
            'notes'        => $new_inst->notes,
            'type_id'      => $new_inst->type_id,
            'status'       => $new_inst->is_active ? "Active" : "Inactive",
            'groups'       => $new_inst->institutionGroups->pluck('id')->all(),
            'group_string' => $new_inst->institutionGroups->pluck('name')->implode(', '),
            'type'         => $new_inst->institutionType ? $new_inst->institutionType->name : "(Not classified)",
            'can_edit'     => true,
            'can_delete'   => true,
            'role'         => $this->userRole($new_inst->id),
        ];

        return $this->respondSuccess('Institution successfully created', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Institution $institution)
    {
        global $thisUser;
        $thisUser = auth()->user();

        if (!$institution->canManage()) {
            return $this->respondError('Update failed (403) - Forbidden');
        }
        $was_active = $institution->is_active;

        $input = $request->all();
        $this->normalizeStatus($input);

        if (!isset($input['is_active'])) {
            return $this->respondError('Status argument is required for update');
        }

        // Quick inline status toggle
        if (count($input) == 2 && isset($input['is_active'])) {
            $institution->update(['is_active' => $input['is_active']]);
            return response()->json(['result' => true]);
        }

        // Make sure that local ID is unique if not set null
        if (isset($input['local_id'])) {
            $newID = trim($input['local_id']);
            if ($institution->local_id != $newID) {
                if (strlen($newID) > 0) {
                    if (Institution::where('local_id', $newID)->exists()) {
                        return $this->respondError('Local ID already assigned to another institution.');
                    }
                    $input['local_id'] = $newID;
                } else {
                    $input['local_id'] = null;
                }
            }
        }

        // Local admins only update name, FTE, and notes
        if (!$thisUser->isConsoAdmin()) {
            $limitedFields = array_intersect_key($input, array_flip(['name', 'fte', 'notes']));
            if (!empty($limitedFields)) {
                $institution->update($limitedFields);
            }
        } else {
            $update_fields = $this->cleanPayload($input, ['creds', 'groups', 'group_ids', 'group_string', 'type']);
            if (isset($input['type_id']) && is_numeric($input['type_id'])) {
                $update_fields['type_id'] = (int) $input['type_id'];
            } elseif (isset($input['type'])) {
                $rawType = is_array($input['type']) ? ($input['type']['id'] ?? null) : $input['type'];
                if (is_numeric($rawType)) {
                    $update_fields['type_id'] = (int) $rawType;
                }
            }

            $institution->update($update_fields);
            $institution->refresh();

            if (isset($input['groups'])) {
                $this->syncRelationIds($institution->institutionGroups(), $input['groups']);
            }
            $institution->unsetRelation('institutionGroups');

            if ($was_active != $institution->is_active) {
                $this->updateCredentials($institution->id, $institution->is_active);
            }
        }

        // Load related models
        $institution->load('institutionGroups:id,name', 'institutionType', 'credentials');
        $harvest_count = $institution->credentials->whereNotNull('last_harvest')->count();

        $inst = [
            'id'           => $institution->id,
            'name'         => $institution->name,
            'is_active'    => $institution->is_active,
            'local_id'     => $institution->local_id,
            'fte'          => $institution->fte,
            'notes'        => $institution->notes,
            'type_id'      => $institution->type_id,
            'status'       => $institution->is_active ? "Active" : "Inactive",
            'groups'       => $institution->institutionGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name]),
            'group_string' => $institution->institutionGroups->pluck('name')->implode(', '),
            'type'         => $institution->institutionType ? $institution->institutionType->name : "(Not classified)",
            'can_edit'     => $institution->canManage(),
            'can_delete'   => !($harvest_count > 0 || !$institution->canManage()),
            'role'         => $this->userRole($institution->id),
        ];

        return $this->respondSuccess('Settings successfully updated', $inst);
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

            if (($input['action'] == 'Create New Group' && !isset($input['name'])) ||
                ($input['action'] == 'Add to Existing Group' && !isset($input['group_id'])) ||
                ($isAssignType && !isset($input['type_id']))
            ) {
                return response()->json(['result' => false, 'msg' => 'Missing input values'], 200);
            }

            $adminInsts = $thisUser->adminInsts();
            $instIDs = ($consoAdmin) ? $input['ids'] : array_intersect($adminInsts, $input['ids']);

            if (!$consoAdmin && ($input['action'] == 'Set Active' || $input['action'] == 'Set Inactive' || $input['action'] == 'Delete')) {
                return response()->json(['result' => false, 'msg' => 'No Authorization'], 200);
            }

            \DB::beginTransaction();

            $msg = '';
            $extraData = array();

            if ($input['action'] == 'Set Active' || $input['action'] == 'Set Inactive') {
                $new_is_active = ($input['action'] == 'Set Active') ? 1 : 0;
                \DB::table('institutions')->whereIn('id', $instIDs)->update(['is_active' => $new_is_active, 'updated_at' => now()]);
                foreach ($instIDs as $id) {
                    $this->updateCredentials($id, $new_is_active);
                }

                \DB::commit();
                $msg = 'Status Updated';
            } else if ($input['action'] == 'Create New Group' || $input['action'] == 'Add to Existing Group') {
                $user_id = ($consoAdmin) ? null : $thisUser->id;
                $groupId = null;
                $groupName = '';

                if ($input['action'] == 'Create New Group') {
                    $exists = \DB::table('institutiongroups')->where('user_id', $user_id)->where('name', $input['name'])->first();
                    if ($exists) {
                        \DB::rollBack();
                        return response()->json(['result' => false, 'msg' => 'Group exists'], 200);
                    }

                    $groupId = \DB::table('institutiongroups')->insertGetId(['name' => $input['name'], 'user_id' => $user_id, 'created_at' => now(), 'updated_at' => now()]);
                    $groupName = $input['name'];
                } else {
                    $group = \DB::table('institutiongroups')->where('user_id', $user_id)->where('id', $input['group_id'])->first();
                    if (!$group) {
                        \DB::rollBack();
                        return response()->json(['result' => false, 'msg' => 'Group not found'], 200);
                    }
                    $groupId = $group->id;
                    $groupName = $group->name;
                }

                foreach ($instIDs as $inst_id) {
                    $already = \DB::table('institution_institution_group')->where('institution_id', $inst_id)->where('institution_group_id', $groupId)->exists();
                    if (!$already) {
                        \DB::table('institution_institution_group')->insert(['institution_id' => $inst_id, 'institution_group_id' => $groupId]);
                    }
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
            } else if ($input['action'] == 'Delete') {
                $institutions = \App\Models\Institution::whereIn('id', $instIDs)->get();

                foreach ($institutions as $institution) {
                    $institution->institutionGroups()->detach();
                    $institution->delete();
                }

                \DB::commit();
                $msg = 'Institutions deleted successfully';
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
     * Import institutions from a CSV file to the database.
     */
    public function import(Request $request)
    {
        try {
            abort_unless(auth()->user()->hasRole('Admin'), 403);

            $parsed = $this->parseCsvUpload($request);
            if (isset($parsed['error'])) {
                return $this->respondError($parsed['error']);
            }

            $rows = $parsed['rows'];
            $rawHeaders = array_shift($rows);
            $headers = array_map(fn($header) => trim(preg_replace('/\s+/', ' ', $header)), $rawHeaders);

            $groups = \DB::table('institutiongroups')->get();
            $inst_skipped = 0;
            $inst_updated = 0;
            $inst_created = 0;

            \DB::beginTransaction();

            foreach ($rows as $row) {
                if (empty($row) || count($row) < count($headers)) {
                    continue;
                }
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
                    $inst_skipped++;
                    continue;
                }

                $_active = ($raw_status === 'Inactive' || $raw_status === 'N') ? 0 : 1;
                $_fte    = ($raw_fte === '') ? null : intval($raw_fte);
                $_notes  = ($raw_notes === '') ? null : $raw_notes;

                $exists = $targetId ? \DB::table('institutions')->where('id', $targetId)->exists() : false;

                if ($exists) {
                    \DB::table('institutions')->where('id', $targetId)->update([
                        'name'       => $raw_name,
                        'is_active'  => $_active,
                        'local_id'   => $localID,
                        'fte'        => $_fte,
                        'notes'      => $_notes,
                        'updated_at' => now(),
                    ]);
                    $inst_updated++;
                } else {
                    $insertData = [
                        'name'       => $raw_name,
                        'is_active'  => $_active,
                        'local_id'   => $localID,
                        'fte'        => $_fte,
                        'notes'      => $_notes,
                        'type_id'    => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if ($targetId) {
                        $insertData['id'] = $targetId;
                    }
                    \DB::table('institutions')->insert($insertData);
                    $inst_created++;
                }

                if ($targetId) {
                    \DB::table('institution_institution_group')->where('institution_id', $targetId)->delete();
                    if ($raw_group !== '') {
                        foreach (preg_split('/,/', $raw_group) as $csv_value) {
                            $_id = intval(trim($csv_value));
                            if ($_id > 0 && $groups->where('id', $_id)->first()) {
                                \DB::table('institution_institution_group')->insert([
                                    'institution_id'       => $targetId,
                                    'institution_group_id' => $_id,
                                ]);
                            }
                        }
                    }
                }
            }

            \DB::commit();

            $inst_data = [];
            $updated_institutions = \DB::table('institutions')->orderBy('name', 'ASC')->get();
            foreach ($updated_institutions as $inst) {
                $instGroups = \DB::table('institution_institution_group')
                    ->join('institutiongroups', 'institution_institution_group.institution_group_id', '=', 'institutiongroups.id')
                    ->where('institution_id', $inst->id)
                    ->select('institutiongroups.id', 'institutiongroups.name')
                    ->get();

                $inst->group_string = $instGroups->pluck('name')->implode(', ');
                $inst->groups = $instGroups->pluck('id')->all();
                $inst_data[] = (array) $inst;
            }

            $msg = $this->formatImportSummary('Import successful', $inst_updated, $inst_created, 0, $inst_skipped);

            return response()->json([
                'result'    => true,
                'msg'       => $msg,
                'inst_data' => $inst_data,
            ]);
        } catch (\Throwable $e) {
            \DB::rollBack();
            return $this->respondError('Server Error: ' . $e->getMessage());
        }
    }

    /**
     * Update credentials when an institution is_active setting is changed
     *
     * @param  Integer  $instId
     * @param  Integer  $active (0/1)
     * @return String
     */
    private function updateCredentials($instId, $active)
    {
        // If is_active is changing, check and update related credentials
        $credentials = Credential::with('provider')->where('inst_id', $instId)->get();
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
    private function userRole($instId)
    {

        global $thisUser;
        if ($thisUser->isServerAdmin()) {
            return "ServerAdmin";
        } else if ($thisUser->hasRole('Admin', 1)) {
            return "Consortium Admin";
        } else if ($thisUser->hasRole('Admin', $instId)) {
            return "Admin";
        } else if ($thisUser->hasRole('Viewer', 1)) {
            return "Consortium Viewer";
        } else if ($thisUser->hasRole('Viewer', $instId)) {
            return "Viewer";
        }
        return "None";
    }
}
