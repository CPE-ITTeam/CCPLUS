<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RoleController extends BaseController
{
    protected string $modelClass = UserRole::class;
    protected string $modelLabel = 'Role';
    protected array $deleteWith = ['user'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);

        $data = [];
        $limit_insts = [];
        $server_admin = config('ccplus.server_admin');

        if (!$thisUser->isConsoAdmin()) {
            $limit_insts = array_map('intval', (array) $thisUser->adminInsts());
            if ($limit_insts === array(1)) {
                $limit_insts = [];
            }
        }

        $filter_options = ['role' => [], 'user_role' => ['Admin', 'Viewer']];
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())
            ->orderBy('id', 'DESC')
            ->where('name', '<>', 'ServerAdmin')
            ->get(['name', 'id'])
            ->toArray();

        foreach ($all_roles as $r) {
            if ($r['name'] === 'Admin' && $thisUser->isConsoAdmin()) {
                $filter_options['role'][] = 'Consortium Admin';
            }
            if ($r['name'] === 'Viewer' && $thisUser->isConsoAdmin()) {
                $filter_options['role'][] = 'Consortium Viewer';
            }
            $filter_options['role'][] = $r['name'];
        }

        $user_data = User::with('roles')
            ->where('email', '<>', $server_admin)
            ->when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get();

        $maxRole = $thisUser->maxRole();
        foreach ($user_data as $user) {
            $canManage = $user->canManage();
            foreach ($user->allRoles() as $role) {
                $data[] = [
                    'id'          => $role['id'],
                    'role_id'     => $role['role_id'],
                    'user_id'     => $user->id,
                    'inst_id'     => $role['inst_id'],
                    'group_id'    => $role['group_id'],
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'role_string' => ($role['inst_id'] == 1) ? 'Consortium ' . $role['name'] : $role['name'],
                    'inst_name'   => $role['inst'],
                    'group_name'  => $role['group'],
                    'scope'       => !is_null($role['inst_id']) ? $role['inst'] : $role['group'],
                    'can_edit'    => false,
                    'can_delete'  => ($canManage && $role['id'] <= $maxRole),
                ];
            }
        }

        $limit_groups = [];
        if (!$thisUser->isConsoAdmin()) {
            $limit_groups = array_map('intval', (array) $thisUser->adminGroups());
            if ($limit_groups === array(1)) {
                $limit_groups = [];
            }
        }

        $filter_options['group'] = InstitutionGroup::when(count($limit_groups) > 0, fn($qry) => $qry->whereIn('id', $limit_groups))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $insts_with_roles = $user_data->unique('inst_id')->pluck('inst_id')->toArray();
        $filter_options['institution'] = Institution::whereIn('id', $insts_with_roles)->orderBy('name', 'ASC')->get();
        $filter_options['user'] = $user_data->map(fn($rec) => [
            'id'   => $rec['id'],
            'name' => $rec['name'],
        ])->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();

        $this->validate($request, ['user' => 'required', 'user_role' => 'required']);
        $input = $request->all();

        if (isset($input['conso']) && $thisUser->isConsoAdmin()) {
            $inst_id = ($input['conso'] === 'Active') ? 1 : ($input['institution'] ?? null);
        } else {
            $inst_id = $input['institution'] ?? null;
        }
        $group_id = $input['group'] ?? null;

        if (($inst_id && !$thisUser->isAdmin($inst_id, null)) || ($group_id && !$thisUser->isAdmin(null, $group_id))) {
            return $this->respondError(__('users.operation_forbidden'));
        }

        $role_inst = $inst_id ? Institution::find($inst_id) : null;
        $role_group = $group_id ? InstitutionGroup::find($group_id) : null;

        $role = Role::where('name', $input['user_role'])->first();
        $user = User::where('id', $input['user'])->with('roles', 'institution:id,name')->first();

        if (!$user || !$role || (!$role_inst && !$role_group)) {
            return $this->respondError(__('users.role_invalid_refs'));
        }

        if ($thisUser->maxRole() < $role->id) {
            return $this->respondError(__('users.role_not_authorized'));
        }

        if ($user->hasRole($role->name, $inst_id, $group_id)) {
            return $this->respondError(__('users.role_already_assigned'));
        }

        try {
            $new_role = UserRole::create([
                'user_id'  => $user->id,
                'role_id'  => $role->id,
                'inst_id'  => $inst_id,
                'group_id' => $group_id,
            ]);
        } catch (\Exception $e) {
            return $this->respondError(__('users.role_save_error', ['error' => $e->getMessage()]));
        }

        $new_role->load('user', 'institution', 'institutiongroup', 'role');
        $record = [
            'id'          => $new_role->id,
            'role_id'     => $role->id,
            'user_id'     => $user->id,
            'inst_id'     => $inst_id,
            'group_id'    => $group_id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role_string' => ($inst_id == 1) ? 'Consortium ' . $role['name'] : $role['name'],
            'inst_name'   => $new_role->institution ? $new_role->institution->name : '',
            'group_name'  => $new_role->institutiongroup ? $new_role->institutiongroup->name : '',
            'scope'       => !is_null($inst_id)
                ? ($new_role->institution ? $new_role->institution->name : '')
                : ($new_role->institutiongroup ? $new_role->institutiongroup->name : ''),
            'can_edit'    => false,
            'can_delete'  => true,
        ];

        return $this->respondSuccess(__('users.role_saved'), $record);
    }

    /**
     * Restrict bulk role deletion to roles belonging to manageable users in this admin's scope.
     */
    protected function scopeBulkQuery($query, $user): void
    {
        $limitInsts = $user->isConsoAdmin() ? [] : $user->adminInsts();
        $allowedUserIds = User::where('email', '<>', config('ccplus.server_admin'))
            ->when(count($limitInsts) > 0, fn($q) => $q->whereIn('inst_id', $limitInsts))
            ->pluck('id');

        $query->whereIn('user_id', $allowedUserIds);
    }

    /**
     * Enforce authorization before BaseController deletes the user role.
     */
    protected function canDeleteRecord(Model $role): bool|string
    {
        return $role->user->canManage()
            ? true
            : __('users.forbidden_request');
    }
}
