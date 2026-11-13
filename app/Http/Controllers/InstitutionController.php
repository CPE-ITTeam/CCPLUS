<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\Credential;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\InstitutionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstitutionController extends BaseController
{
    private $thisUser;

    /**
     * Display a listing of the resource.
     *
     * @param  string  $role  // 'admin' or 'viewer'
     */
    public function index($role)
    {
        $this->thisUser = auth()->user();

        $type = ($role === 'admin') ? 'admin' : 'viewer';
        abort_unless($type === 'viewer' || $this->thisUser->hasAnyRole(['Admin']), 403);

        $data = [];
        $limit_to_insts = [];

        $filter_options = [
            'statuses' => ['ALL', 'Active', 'Inactive'],
            'type'     => InstitutionType::orderBy('name', 'ASC')->get(['id', 'name'])->toArray(),
            'groups'   => [],
        ];

        if (!$this->thisUser->isConsoAdmin()) {
            $rawInsts = ($type === 'admin') ? $this->thisUser->adminInsts() : $this->thisUser->viewerInsts();
            $limit_to_insts = array_map('intval', (array) $rawInsts);
            if ($limit_to_insts === array(1)) {
                $limit_to_insts = [];
            }
        }

        $inst_data = Institution::with('institutionGroups:id,name', 'credentials', 'institutionType')
            ->when(count($limit_to_insts) > 0, fn($qry) => $qry->whereIn('id', $limit_to_insts))
            ->orderBy('name', 'ASC')
            ->get();

        $seen_group_ids = [];
        foreach ($inst_data as $rec) {
            $inst = [
                'id'           => $rec->id,
                'name'         => $rec->name,
                'is_active'    => $rec->is_active,
                'local_id'     => $rec->local_id,
                'fte'          => $rec->fte,
                'notes'        => $rec->notes,
                'type_id'      => $rec->type_id,
                'group_string' => '',
                'status'       => $rec->is_active ? 'Active' : 'Inactive',
                'group_ids'    => $rec->institutionGroups()->pluck('institution_group_id')->all(),
                'groups'       => $rec->institutionGroups->map(fn($group) => [
                    'id'   => $group->id,
                    'name' => $group->name,
                ]),
            ];

            foreach ($rec->institutionGroups as $group) {
                $inst['group_string'] .= ($inst['group_string'] === '') ? '' : ', ';
                $inst['group_string'] .= $group->name;
                if (!in_array($group->id, $seen_group_ids)) {
                    $seen_group_ids[] = $group->id;
                    $filter_options['groups'][] = ['id' => $group->id, 'name' => $group->name];
                }
            }

            $harvest_count = $rec->credentials->whereNotNull('last_harvest')->count();
            $inst['type'] = $rec->institutionType ? $rec->institutionType->name : '(Not classified)';
            $inst['can_edit'] = $rec->canManage();
            $inst['can_delete'] = !($harvest_count > 0 || !$rec->canManage());
            $inst['role'] = $this->userRole($rec->id);
            $data[] = $inst;
        }

        array_multisort(array_column($filter_options['groups'], 'name'), SORT_ASC, $filter_options['groups']);

        return response()->json(['records' => $data, 'options' => $filter_options], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->thisUser = auth()->user();

        if (!$this->thisUser->isConsoAdmin()) {
            return $this->respondError(__('institutions.create_forbidden'));
        }

        $this->validate($request, ['name' => 'required']);
        $input = $request->all();

        $this->normalizeStatus($input, 0);
        $create_fields = $this->cleanPayload($input, ['creds', 'groups', 'group_ids', 'group_string', 'type']);
        $create_fields['fte'] = (isset($input['fte']) && is_numeric($input['fte'])) ? (int) $input['fte'] : null;
        $create_fields['type_id'] = (isset($input['type']) && is_numeric($input['type'])) ? (int) $input['type'] : 1;

        $localID = isset($input['local_id']) ? trim($input['local_id']) : null;
        if ($localID && strlen($localID) > 0) {
            if (Institution::where('local_id', $localID)->exists()) {
                return $this->respondError(__('institutions.local_id_exists'));
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

        $stub = isset($input['creds']) && ($input['creds'] === 'Active' || $input['creds'] == 1 || $input['creds'] === true);
        if ($stub) {
            $connections = Connection::with('globalProv')->where('inst_id', 1)->where('is_active', 1)->get();
            foreach ($connections as $cnx) {
                if (!$cnx->globalProv || $cnx->globalProv->is_active == 0) {
                    continue;
                }
                $credential = new Credential();
                $credential->inst_id = $new_id;
                $credential->prov_id = $cnx->prov_id;
                foreach ($cnx->globalProv->connectionFields() as $fld) {
                    $credential->{$fld['name']} = $fld['required'] ? '-required-' : null;
                }
                $credential->status = 'Incomplete';
                $credential->save();
            }
        }

        $new_inst->load('institutionGroups:id,name', 'credentials', 'institutionType');
        $data = [
            'id'           => $new_id,
            'name'         => $new_inst->name,
            'is_active'    => $new_inst->is_active,
            'local_id'     => $new_inst->local_id,
            'fte'          => $new_inst->fte,
            'notes'        => $new_inst->notes,
            'type_id'      => $new_inst->type_id,
            'status'       => $new_inst->is_active ? 'Active' : 'Inactive',
            'groups'       => $new_inst->institutionGroups->pluck('id')->all(),
            'group_string' => $new_inst->institutionGroups->pluck('name')->implode(', '),
            'type'         => $new_inst->institutionType ? $new_inst->institutionType->name : '(Not classified)',
            'can_edit'     => true,
            'can_delete'   => true,
            'role'         => $this->userRole($new_inst->id),
        ];

        return $this->respondSuccess(__('institutions.created'), $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Institution $institution)
    {
        $this->thisUser = auth()->user();

        if (!$institution->canManage()) {
            return $this->respondError(__('institutions.update_forbidden'));
        }
        $was_active = $institution->is_active;

        $input = $request->all();
        $this->normalizeStatus($input);

        if (!isset($input['is_active'])) {
            return $this->respondError(__('institutions.status_required'));
        }

        if (count($input) === 2 && isset($input['is_active'])) {
            $institution->update(['is_active' => $input['is_active']]);
            return response()->json(['result' => true]);
        }

        if (isset($input['local_id'])) {
            $newID = trim($input['local_id']);
            if ($institution->local_id != $newID) {
                if (strlen($newID) > 0) {
                    if (Institution::where('local_id', $newID)->exists()) {
                        return $this->respondError(__('institutions.local_id_exists'));
                    }
                    $input['local_id'] = $newID;
                } else {
                    $input['local_id'] = null;
                }
            }
        }

        if (!$this->thisUser->isConsoAdmin()) {
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
            'status'       => $institution->is_active ? 'Active' : 'Inactive',
            'groups'       => $institution->institutionGroups->map(fn($g) => ['id' => $g->id, 'name' => $g->name]),
            'group_string' => $institution->institutionGroups->pluck('name')->implode(', '),
            'type'         => $institution->institutionType ? $institution->institutionType->name : '(Not classified)',
            'can_edit'     => $institution->canManage(),
            'can_delete'   => !($harvest_count > 0 || !$institution->canManage()),
            'role'         => $this->userRole($institution->id),
        ];
        return $this->respondSuccess(__('institutions.settings_updated'), $inst);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!auth()->user()->hasRole('Admin')) {
            return $this->respondError(__('institutions.update_forbidden'));
        }

        try {
            DB::beginTransaction();

            $exists = DB::table('institutions')->where('id', $id)->exists();
            if (!$exists) {
                DB::rollBack();
                return $this->respondError(__('institutions.not_found_active'));
            }

            DB::table('institution_institution_group')->where('institution_id', $id)->delete();
            DB::table('institutions')->where('id', $id)->delete();

            DB::commit();

            $inst_data = $this->buildTenantInstitutionList();

            return response()->json([
                'result'    => true,
                'msg'       => __('institutions.deleted'),
                'inst_data' => $inst_data,
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            return $this->respondError(__('institutions.delete_server_error', ['error' => $ex->getMessage()]));
        }
    }

    /**
     * Bulk operations from the UI.
     */
    public function bulk(Request $request)
    {
        try {
            $this->thisUser = auth()->user();
            $consoAdmin = $this->thisUser->isConsoAdmin();
            $this->validate($request, ['ids' => 'required', 'action' => 'required']);
            $input = $request->all();
            $isAssignType = ($input['action'] === 'Set Institution Type' || $input['action'] === 'Assign Type');
            if (
                ($input['action'] === 'Create New Group' && !isset($input['name'])) ||
                ($input['action'] === 'Add to Existing Group' && !isset($input['group_id'])) ||
                ($isAssignType && !isset($input['type_id']))
            ) {
                return $this->respondError(__('institutions.missing_input'));
            }
            $adminInsts = $this->thisUser->adminInsts();
            $instIDs = $consoAdmin ? $input['ids'] : array_intersect($adminInsts, $input['ids']);
            if (!$consoAdmin && in_array($input['action'], ['Set Active', 'Set Inactive', 'Delete'], true)) {
                return $this->respondError(__('institutions.no_authorization'));
            }
            DB::beginTransaction();
            $msg = '';
            $extraData = [];
            if ($input['action'] === 'Set Active' || $input['action'] === 'Set Inactive') {
                $new_is_active = ($input['action'] === 'Set Active') ? 1 : 0;
                DB::table('institutions')->whereIn('id', $instIDs)->update([
                    'is_active'  => $new_is_active,
                    'updated_at' => now(),
                ]);
                foreach ($instIDs as $id) {
                    $this->updateCredentials($id, $new_is_active);
                }
                DB::commit();
                $msg = __('institutions.bulk_status_updated');
            } elseif ($input['action'] === 'Create New Group' || $input['action'] === 'Add to Existing Group') {
                $user_id = $consoAdmin ? null : $this->thisUser->id;
                if ($input['action'] === 'Create New Group') {
                    $exists = DB::table('institutiongroups')
                        ->where('user_id', $user_id)
                        ->where('name', $input['name'])
                        ->exists();
                    if ($exists) {
                        DB::rollBack();
                        return $this->respondError(__('institutions.bulk_group_exists'));
                    }
                    $groupId = DB::table('institutiongroups')->insertGetId([
                        'name'       => $input['name'],
                        'user_id'    => $user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $groupName = $input['name'];
                } else {
                    $group = DB::table('institutiongroups')
                        ->where('user_id', $user_id)
                        ->where('id', $input['group_id'])
                        ->first();
                    if (!$group) {
                        DB::rollBack();
                        return $this->respondError(__('institutions.bulk_group_not_found'));
                    }
                    $groupId = $group->id;
                    $groupName = $group->name;
                }
                foreach ($instIDs as $inst_id) {
                    $already = DB::table('institution_institution_group')
                        ->where('institution_id', $inst_id)
                        ->where('institution_group_id', $groupId)
                        ->exists();
                    if (!$already) {
                        DB::table('institution_institution_group')->insert([
                            'institution_id'       => $inst_id,
                            'institution_group_id' => $groupId,
                        ]);
                    }
                }
                DB::commit();
                $msg = __('institutions.bulk_group_assigned');
                $extraData['group'] = ['id' => $groupId, 'name' => $groupName];
            } elseif ($isAssignType) {
                DB::table('institutions')->whereIn('id', $instIDs)->update([
                    'type_id'    => $input['type_id'],
                    'updated_at' => now(),
                ]);
                $typeName = DB::table('institutiontypes')->where('id', $input['type_id'])->value('name') ?: 'Unknown';
                DB::commit();
                $msg = __('institutions.bulk_type_assigned');
                $extraData['newType'] = ['id' => $input['type_id'], 'name' => $typeName];
            } elseif ($input['action'] === 'Delete') {
                $institutions = Institution::whereIn('id', $instIDs)->get();
                foreach ($institutions as $institution) {
                    $institution->institutionGroups()->detach();
                    $institution->delete();
                }
                DB::commit();
                $msg = ('institutions.bulk_deleted');
            } else {
                DB::rollBack();
                return $this->respondError(__('institutions.unrecognized_action'));
            }
            $inst_data = $this->buildTenantInstitutionList(true);
            return response()->json(array_merge([
                'result'       => true,
                'msg'          => $msg,
                'affectedIds'  => $instIDs,
                'inst_data'    => $inst_data,
                'institutions' => $inst_data,
            ], $extraData), 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->respondError(__('institutions.server_error', ['error' => $e->getMessage()]));
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

            $validGroupIds = DB::table('institutiongroups')->pluck('id')->all();
            $skipped = 0;
            $updated = 0;
            $created = 0;

            DB::beginTransaction();

            foreach ($rows as $row) {
                if (empty($row) || count($row) < count($headers)) {
                    continue;
                }

                $mappedRow = array_combine($headers, array_slice($row, 0, count($headers)));
                $parsedRow = $this->extractImportRow($mappedRow);

                if (!$parsedRow) {
                    $skipped++;
                    continue;
                }

                $targetId = $parsedRow['id'];
                $localId = $parsedRow['local_id'];

                // Match by CC+ System ID first, then fall back to Local ID
                $existingId = null;
                if ($targetId) {
                    $existingId = DB::table('institutions')->where('id', $targetId)->value('id');
                } elseif ($localId) {
                    $existingId = DB::table('institutions')->where('local_id', $localId)->value('id');
                }

                if ($existingId) {
                    DB::table('institutions')->where('id', $existingId)->update([
                        'name'       => $parsedRow['name'],
                        'is_active'  => $parsedRow['is_active'],
                        'local_id'   => $localId,
                        'fte'        => $parsedRow['fte'],
                        'notes'      => $parsedRow['notes'],
                        'updated_at' => now(),
                    ]);
                    $updated++;
                    $resolvedId = $existingId;
                } else {
                    $insertData = [
                        'name'       => $parsedRow['name'],
                        'is_active'  => $parsedRow['is_active'],
                        'local_id'   => $localId,
                        'fte'        => $parsedRow['fte'],
                        'notes'      => $parsedRow['notes'],
                        'type_id'    => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if ($targetId) {
                        $insertData['id'] = $targetId;
                        DB::table('institutions')->insert($insertData);
                        $resolvedId = $targetId;
                    } else {
                        $resolvedId = DB::table('institutions')->insertGetId($insertData);
                    }
                    $created++;
                }

                $this->syncInstitutionGroups($resolvedId, $parsedRow['raw_groups'], $validGroupIds);
            }

            DB::commit();

            $inst_data = $this->buildTenantInstitutionList(true);
            $msg = $this->formatImportSummary(__('institutions.import_success'), $updated, $created, 0, $skipped);

            return response()->json([
                'result'    => true,
                'msg'       => $msg,
                'inst_data' => $inst_data,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->respondError(__('institutions.server_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Normalize and validate a single CSV row for institution import.
     */
    private function extractImportRow(array $mappedRow): ?array
    {
        $rawId     = trim($mappedRow['CC+ System ID'] ?? '');
        $rawLocal  = trim($mappedRow['Local ID'] ?? '');
        $rawName   = trim($mappedRow['Institution Name'] ?? '');
        $rawStatus = trim($mappedRow['Status'] ?? '');
        $rawFte    = trim($mappedRow['FTE'] ?? '');
        $rawGroups = trim($mappedRow['Group Assignment(s)'] ?? '');
        $rawNotes  = trim($mappedRow['Notes'] ?? '');

        $targetId = ($rawId !== '') ? intval($rawId) : null;
        $localId  = ($rawLocal !== '') ? $rawLocal : null;

        if ($targetId === 1 || (!$targetId && !$localId) || strlen($rawName) < 1) {
            return null;
        }

        return [
            'id'         => $targetId,
            'local_id'   => $localId,
            'name'       => $rawName,
            'is_active'  => ($rawStatus === 'Inactive' || $rawStatus === 'N') ? 0 : 1,
            'fte'        => ($rawFte === '') ? null : intval($rawFte),
            'notes'      => ($rawNotes === '') ? null : $rawNotes,
            'raw_groups' => $rawGroups,
        ];
    }

    /**
     * Replace group pivot assignments for an imported institution.
     */
    private function syncInstitutionGroups(int $institutionId, string $rawGroups, array $validGroupIds): void
    {
        DB::table('institution_institution_group')->where('institution_id', $institutionId)->delete();

        if ($rawGroups === '') {
            return;
        }

        $groupIds = array_unique(array_filter(
            array_map(fn($val) => intval(trim($val)), explode(',', $rawGroups)),
            fn($id) => $id > 0 && in_array($id, $validGroupIds, true)
        ));

        if (!empty($groupIds)) {
            $pivotRows = array_map(fn($groupId) => [
                'institution_id'       => $institutionId,
                'institution_group_id' => $groupId,
            ], $groupIds);

            DB::table('institution_institution_group')->insert($pivotRows);
        }
    }

    /**
     * Hydrate fresh institution dataset from the active tenant connection.
     */
    private function buildTenantInstitutionList(bool $includeTypeName = false): array
    {
        $inst_data = [];
        $updated_institutions = DB::table('institutions')->orderBy('name', 'ASC')->get();
        $typesMap = $includeTypeName ? DB::table('institutiontypes')->pluck('name', 'id')->all() : [];
        foreach ($updated_institutions as $inst) {
            $instGroups = DB::table('institution_institution_group')
                ->join('institutiongroups', 'institution_institution_group.institution_group_id', '=', 'institutiongroups.id')
                ->where('institution_id', $inst->id)
                ->select('institutiongroups.id', 'institutiongroups.name')
                ->get();
            $inst->group_string = $instGroups->pluck('name')->implode(', ');
            $inst->groups = $instGroups->pluck('id')->all();
            if ($includeTypeName) {
                $inst->type = $typesMap[$inst->type_id] ?? 'Unknown';
            }
            $inst_data[] = (array) $inst;
        }
        return $inst_data;
    }
    /**
     * Update credentials when an institution is_active setting is changed.
     */
    private function updateCredentials($instId, $active): void
    {
        $credentials = Credential::with('provider')->where('inst_id', $instId)->get();
        foreach ($credentials as $credential) {
            if ($active == 1) {
                if ($credential->status !== 'Disabled') {
                    $credential->update(['status' => 'Suspended']);
                }
            } else {
                $credential->resetStatus();
            }
        }
    }
    /**
     * Return a string for the current user's role(s).
     */
    private function userRole($instId): string
    {
        if ($this->thisUser->isServerAdmin()) {
            return 'ServerAdmin';
        } elseif ($this->thisUser->hasRole('Admin', 1)) {
            return 'Consortium Admin';
        } elseif ($this->thisUser->hasRole('Admin', $instId)) {
            return 'Admin';
        } elseif ($this->thisUser->hasRole('Viewer', 1)) {
            return 'Consortium Viewer';
        } elseif ($this->thisUser->hasRole('Viewer', $instId)) {
            return 'Viewer';
        }
        return 'None';
    }
}
