<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\InstitutionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class InstitutionGroupController extends BaseController
{
    protected string $modelClass = InstitutionGroup::class;
    protected string $modelLabel = 'Group';

    /**
     * Return a JSON array of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        $filter_options = ['types' => [], 'institutions' => []];

        if ($thisUser->isConsoAdmin()) {
            $groups = InstitutionGroup::with('institutions:id,name,type_id', 'typeRestriction')
                ->whereNull('user_id')
                ->orderBy('name', 'ASC')
                ->get();
        } else {
            $groupIds = $thisUser->adminGroups();
            if (count($groupIds) > 0) {
                $groups = InstitutionGroup::with('institutions:id,name,type_id', 'typeRestriction')
                    ->where('user_id', $thisUser->id)
                    ->orWhereIn('id', $groupIds)
                    ->orderBy('name', 'ASC')
                    ->get();
            } else {
                return response()->json(['records' => [], 'options' => $filter_options, 'result' => true], 200);
            }
        }

        $limit_insts = [];
        if (!$thisUser->isConsoAdmin()) {
            $limit_insts = array_map('intval', (array) $thisUser->adminInsts());
            if ($limit_insts === array(1)) {
                $limit_insts = [];
            }
        }

        $all_institutions = Institution::when(count($limit_insts) > 0, fn ($qry) => $qry->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name', 'type_id']);

        $data = [];
        foreach ($groups as $group) {
            $memberIds = $group->institutions->pluck('id')->toArray();
            $data[] = [
                'id'           => $group->id,
                'name'         => $group->name,
                'type_id'      => $group->type_id,
                'type_string'  => $group->typeRestriction ? $group->typeRestriction->name : '',
                'institutions' => $group->institutions->sortBy('name')->values()->toArray(),
                'member_ids'   => $memberIds,
                'count'        => count($memberIds),
                'can_edit'     => true,
                'can_delete'   => true,
            ];
        }

        $filter_options['types'] = InstitutionType::orderBy('name', 'ASC')->get(['id', 'name'])->toArray();
        $filter_options['institutions'] = $all_institutions->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();

        if (!$thisUser->isConsoAdmin() && count($thisUser->adminGroups()) === 0) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $this->validate($request, [
            'name' => 'required|unique:consodb.institutiongroups,name',
        ]);
        $input = $request->all();

        $type_id = $input['type'] ?? null;
        $user_id = $thisUser->isConsoAdmin() ? null : $thisUser->id;

        if (InstitutionGroup::where('user_id', $user_id)->where('name', $input['name'])->exists()) {
            return $this->respondError(__('institutions.group_duplicate'));
        }

        $group = InstitutionGroup::create([
            'name'    => $input['name'],
            'type_id' => $type_id,
            'user_id' => $user_id,
        ]);
        $group->load('typeRestriction');

        $all_insts = Institution::orderBy('name', 'ASC')
            ->when(!is_null($type_id), fn ($q) => $q->where('type_id', $type_id))
            ->get(['id', 'name', 'type_id']);

        $new_members = [];
        if (isset($input['institutions'])) {
            foreach ($input['institutions'] as $inst) {
                $institution = $all_insts->where('id', $inst['id'])->first();
                if ($institution && $institution->canManage()) {
                    $group->institutions()->attach($inst['id']);
                    $new_members[] = $inst['id'];
                }
            }
            $group->load('institutions');
        } else {
            $group->setRelation('institutions', collect());
        }

        $group->count = count($new_members);

        if ($group->count > 0) {
            $newMembers = $all_insts->whereIn('id', $new_members);
            $newMembers->load('institutionGroups');

            foreach ($group->institutions as $key => $inst) {
                $instData = $newMembers->where('id', $inst->id)->first();
                if ($instData) {
                    $group->institutions[$key]->group_string = $instData->institutionGroups->pluck('name')->implode(', ');
                }
            }
        }

        $data = [
            'id'           => $group->id,
            'name'         => $group->name,
            'type'         => $group->typeRestriction ? $group->typeRestriction->name : '',
            'institutions' => $group->institutions,
            'count'        => $group->count,
            'can_edit'     => true,
            'can_delete'   => true,
        ];

        return $this->respondSuccess(__('institutions.group_created'), $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InstitutionGroup $group)
    {
        $thisUser = auth()->user();

        if (!$thisUser->isConsoAdmin() && !$group->canManage()) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $this->validate($request, ['name' => 'required']);
        $input = $request->all();

        $args = [];
        if ($group->name !== $input['name']) {
            $args['name'] = $input['name'];
        }
        if (!empty($input['reset_type'])) {
            $input['types'] = null;
        }
        if (array_key_exists('types', $input) && $group->type_id != $input['types']) {
            $args['type_id'] = $input['types'];
        }

        if (count($args) > 0) {
            try {
                $group->update($args);
            } catch (\Exception $ex) {
                return $this->respondError($ex->getMessage());
            }
        }

        $type_skip = 0;
        if (isset($request->institutions) && count($request->institutions) > 0) {
            $existing_inst_ids = $group->institutions()->pluck('institutions.id')->toArray();
            $input_ids = array_column($request->institutions, 'id');
            $detach_ids = array_diff($existing_inst_ids, $input_ids);
            $attach_ids = array_diff($input_ids, $existing_inst_ids);

            foreach ($detach_ids as $ii) {
                $group->institutions()->detach($ii);
            }

            foreach ($request->institutions as $inst) {
                if (in_array($inst['id'], $attach_ids)) {
                    if (!is_null($group->type_id) && $group->type_id != $inst['type_id']) {
                        $type_skip++;
                        continue;
                    }
                    $group->institutions()->attach($inst['id']);
                }
            }
        }

        $group->load('institutions:id,name,type_id', 'typeRestriction');
        $group->count = $group->institutions->count();
        $group->type_string = $group->typeRestriction ? $group->typeRestriction->name : '';
        $group->can_edit = true;
        $group->can_delete = true;

        $msg = ($type_skip > 0)
            ? __('institutions.group_updated_type_skip', ['count' => $type_skip])
            : __('institutions.group_updated');

        return $this->respondSuccess($msg, $group);
    }

    /**
     * Enforce authorization before BaseController deletes the group.
     */
    protected function canDeleteRecord(Model $group): bool|string
    {
        return (auth()->user()->isConsoAdmin() || $group->canManage())
            ? true
            : __('institutions.not_authorized');
    }
    /**
     * Import institution groups from a CSV to the database.
     */
    public function import(Request $request)
    {
        if (!auth()->user()->isConsoAdmin()) {
            return $this->respondError(__('institutions.not_authorized'));
        }

        $parsed = $this->parseCsvUpload($request, ['Additions & Updates', 'Full Replacement']);
        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }

        $rows = $parsed['rows'];
        $type = $parsed['type'];

        $num_deleted = 0;
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;

        $groups = InstitutionGroup::get();
        $institutions = Institution::get();
        $group_ids_to_keep = [];

        foreach ($rows as $row) {
            if (!isset($row[0]) || $row[0] === '' || !is_numeric($row[0])) {
                continue;
            }

            $_gid = intval($row[0]);
            $_name = isset($row[1]) ? trim($row[1]) : '';

            if ($type === 'Additions & Updates' && $groups->where('name', $_name)->first()) {
                $num_skipped++;
                continue;
            }

            if (strlen($_name) < 1) {
                $num_skipped++;
                continue;
            }

            $current_group = $groups->where('id', $_gid)->first();
            if ($current_group) {
                if ($groups->where('name', $_name)->first()) {
                    $_name = trim($current_group->name);
                }
            } else {
                $current_group = $groups->where('name', $_name)->first();
                if ($current_group) {
                    $_name = trim($current_group->name);
                }
            }

            $_data = ['id' => $_gid, 'name' => $_name];
            if (!$current_group) {
                $current_group = InstitutionGroup::create($_data);
                $groups->push($current_group);
                $num_created++;
            } else {
                $current_group->update($_data);
                $num_updated++;
            }
            $group_ids_to_keep[] = $current_group->id;

            if (isset($row[2]) && $row[2] !== '') {
                $_ids = preg_split('/\s*,\s*/', trim($row[2]));
                if (count($_ids) > 0) {
                    $current_group->institutions()->detach();
                    foreach ($_ids as $inst_id) {
                        if ($institutions->where('id', intval($inst_id))->first()) {
                            $current_group->institutions()->attach($inst_id);
                        }
                    }
                }
            }
        }

        if ($type === 'Full Replacement') {
            $num_deleted = InstitutionGroup::whereNotIn('id', $group_ids_to_keep)->delete();
        }

        $extra = ($num_skipped > 0) ? __('institutions.group_import_skipped', ['count' => $num_skipped]) : '';
        $msg = $this->formatImportSummary(
            __('institutions.group_import_success'),
            $num_updated,
            $num_created,
            $num_deleted,
            0,
            $extra
        );

        return $this->respondSuccess($msg);
    }
}
