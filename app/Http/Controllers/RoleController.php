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
            if ($limit_insts === [1]) {
                $limit_insts = [];
            }
        }

        $roleFilterOptions = [];
        $formRoleOptions = $thisUser->isServerAdmin() ? ['ServerAdmin', 'Admin', 'Viewer'] : ['Admin', 'Viewer'];
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())
            ->orderBy('id', 'DESC')
            ->get(['name', 'id'])
            ->toArray();

        foreach ($all_roles as $r) {
            if ($r['name'] === 'ServerAdmin' && !$thisUser->isServerAdmin()) {
                continue;
            }
            if ($r['name'] === 'Admin' && $thisUser->isConsoAdmin()) {
                $roleFilterOptions[] = 'Consortium Admin';
            }
            if ($r['name'] === 'Viewer' && $thisUser->isConsoAdmin()) {
                $roleFilterOptions[] = 'Consortium Viewer';
            }
            $roleFilterOptions[] = $r['name'];
        }

        $user_data = User::with('roles')
            ->where('email', '<>', $server_admin)
            ->when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get();

        $maxRole = $thisUser->maxRole();
        foreach ($user_data as $user) {
            $isBuiltInAdmin = !str_contains((string) $user->email, '@');
            $canManage = !$isBuiltInAdmin && $user->canManage();
            foreach ($user->allRoles() as $role) {
                $roleId = (int) ($role['role_id'] ?? $role['id']);
                $canAct = $canManage && $roleId <= $maxRole;
                $data[] = $this->formatRoleRow(
                    (int) $role['id'],
                    $roleId,
                    $role['name'],
                    $user,
                    is_null($role['inst_id']) ? null : (int) $role['inst_id'],
                    is_null($role['group_id']) ? null : (int) $role['group_id'],
                    $role['inst'] ?? '',
                    $role['group'] ?? '',
                    $canAct
                );
            }
        }


        $limit_groups = [];
        if (!$thisUser->isConsoAdmin()) {
            $limit_groups = array_map('intval', (array) $thisUser->adminGroups());
            if ($limit_groups === [1]) {
                $limit_groups = [];
            }
        }

        $groups = InstitutionGroup::when(count($limit_groups) > 0, fn($qry) => $qry->whereIn('id', $limit_groups))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $institutions = Institution::where('is_active', 1)
            ->when(count($limit_insts) > 0, fn($qry) => $qry->whereIn('id', $limit_insts))
            ->orderBy('name', 'ASC')
            ->get(['id', 'name']);

        $users = $user_data->map(fn($rec) => [
            'id'   => $rec['id'],
            'name' => $rec['name'],
        ])->values()->toArray();

        $filter_options = [
            'users'        => $users,
            'roles'        => $roleFilterOptions,
            'user_role'    => $formRoleOptions,
            'institutions' => $institutions,
            'groups'       => $groups,
        ];

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Store one or more newly created role assignments in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();

        $this->validate($request, ['user_role' => 'required']);
        $input = $request->all();

        $userIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($input['users'] ?? (!empty($input['user']) ? (array) $input['user'] : []))
        ))));
        if (count($userIds) === 0) {
            return $this->respondError(__('users.role_invalid_refs'));
        }

        $role = Role::where('name', $input['user_role'])->first();
        $users = User::whereIn('id', $userIds)->with('roles', 'institution:id,name')->get();

        if ($users->isEmpty() || !$role) {
            return $this->respondError(__('users.role_invalid_refs'));
        }
        if ($thisUser->maxRole() < $role->id) {
            return $this->respondError(__('users.role_not_authorized'));
        }

        // Build target (inst_id, group_id) pairs
        $targets = [];
        if ($role->name === 'ServerAdmin') {
            abort_unless($thisUser->isServerAdmin(), 403);
            $targets[] = ['inst_id' => 1, 'group_id' => null];
        } elseif (($input['conso'] ?? 'Inactive') === 'Active') {
            if (!$thisUser->isConsoAdmin()) {
                return $this->respondError(__('users.operation_forbidden'));
            }
            $targets[] = ['inst_id' => 1, 'group_id' => null];
        } else {
            $instIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['institutions'] ?? (!empty($input['institution']) ? (array) $input['institution'] : []))))));
            $groupIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['groups'] ?? (!empty($input['group']) ? (array) $input['group'] : []))))));

            foreach ($instIds as $iid) {
                if (!$thisUser->isAdmin($iid, null)) {
                    return $this->respondError(__('users.operation_forbidden'));
                }
                if (Institution::where('id', $iid)->exists()) {
                    $targets[] = ['inst_id' => $iid, 'group_id' => null];
                }
            }
            foreach ($groupIds as $gid) {
                if (!$thisUser->isAdmin(null, $gid)) {
                    return $this->respondError(__('users.operation_forbidden'));
                }
                if (InstitutionGroup::where('id', $gid)->exists()) {
                    $targets[] = ['inst_id' => null, 'group_id' => $gid];
                }
            }
        }

        if (count($targets) === 0) {
            return $this->respondError(__('users.role_invalid_refs'));
        }

        $createdRecords = [];
        foreach ($users as $user) {
            if (!str_contains((string) $user->email, '@') || !$user->canManage()) {
                continue;
            }
            foreach ($targets as $target) {
                $exactExists = UserRole::where('user_id', $user->id)
                    ->where('role_id', $role->id)
                    ->where('inst_id', $target['inst_id'])
                    ->where('group_id', $target['group_id'])
                    ->exists();

                if ($exactExists || $this->isCoveredByExistingRole($user, $role, $target['inst_id'], $target['group_id'])) {
                    continue;
                }
                try {
                    $newRole = UserRole::create([
                        'user_id'  => $user->id,
                        'role_id'  => $role->id,
                        'inst_id'  => $target['inst_id'],
                        'group_id' => $target['group_id'],
                    ]);
                    $this->pruneSubsumedRoles($user, $role, $target['inst_id'], $target['group_id'], (int) $newRole->id);
                    $createdRecords[] = $this->formatRoleRecord($newRole, $user, $role);
                } catch (\Exception $e) {
                    return $this->respondError(__('users.role_save_error', ['error' => $e->getMessage()]));
                }
            }
        }

        if (count($createdRecords) === 0) {
            return $this->respondError(__('users.role_already_assigned'));
        }

        return response()->json([
            'result'  => true,
            'msg'     => __('users.role_saved'),
            'record'  => $createdRecords[0],
            'records' => $createdRecords,
        ], 200);
    }


    /**
     * Update an existing role assignment (role level and/or scope).
     */
    public function update(Request $request, $id)
    {
        $thisUser = auth()->user();
        $userRole = UserRole::with('user', 'role')->findOrFail($id);

        if (!$userRole->user || !str_contains((string) $userRole->user->email, '@') || !$userRole->user->canManage()) {
            return $this->respondError(__('users.forbidden_request'));
        }

        $this->validate($request, ['user_role' => 'required']);
        $input = $request->all();

        $role = Role::where('name', $input['user_role'])->first();
        if (!$role || $thisUser->maxRole() < $role->id) {
            return $this->respondError(__('users.role_not_authorized'));
        }

        $instId = null;
        $groupId = null;
        if ($role->name === 'ServerAdmin') {
            abort_unless($thisUser->isServerAdmin(), 403);
            $instId = 1;
        } elseif (($input['conso'] ?? 'Inactive') === 'Active') {
            if (!$thisUser->isConsoAdmin()) {
                return $this->respondError(__('users.operation_forbidden'));
            }
            $instId = 1;
        } elseif (!empty($input['institution'])) {
            $rawInst = is_array($input['institution']) ? ($input['institution'][0] ?? null) : $input['institution'];
            $instId = $rawInst ? (int) $rawInst : null;
            if (!$instId || !$thisUser->isAdmin($instId, null)) {
                return $this->respondError(__('users.operation_forbidden'));
            }
        } elseif (!empty($input['group'])) {
            $rawGroup = is_array($input['group']) ? ($input['group'][0] ?? null) : $input['group'];
            $groupId = $rawGroup ? (int) $rawGroup : null;
            if (!$groupId || !$thisUser->isAdmin(null, $groupId)) {
                return $this->respondError(__('users.operation_forbidden'));
            }
        } else {
            return $this->respondError(__('users.role_invalid_refs'));
        }

        $duplicate = UserRole::where('user_id', $userRole->user_id)
            ->where('role_id', $role->id)
            ->where('inst_id', $instId)
            ->where('group_id', $groupId)
            ->where('id', '<>', $userRole->id)
            ->exists();
        if ($duplicate) {
            return $this->respondError(__('users.role_already_assigned'));
        }

        $userRole->role_id = $role->id;
        $userRole->inst_id = $instId;
        $userRole->group_id = $groupId;
        $userRole->save();

        $this->pruneSubsumedRoles($userRole->user, $role, $instId, $groupId, (int) $userRole->id);

        $record = $this->formatRoleRecord($userRole, $userRole->user, $role);
        return $this->respondSuccess(__('users.role_saved'), $record);
    }
    

    private function isCoveredByExistingRole(User $user, Role $role, ?int $instId, ?int $groupId): bool
    {
        $isViewer = ($role->name === 'Viewer');
        $isAdmin = ($role->name === 'Admin');

        $serverAdminId = (int) (Role::where('name', 'ServerAdmin')->value('id') ?? 0);
        $adminId = (int) (Role::where('name', 'Admin')->value('id') ?? 0);
        $viewerId = (int) (Role::where('name', 'Viewer')->value('id') ?? 0);

        $existing = UserRole::where('user_id', $user->id)->get(['role_id', 'inst_id', 'group_id']);

        // 1. ServerAdmin covers everything
        if ($serverAdminId > 0 && $existing->contains('role_id', $serverAdminId)) {
            return true;
        }

        // 2. Consortium Admin covers every Admin and Viewer scope
        $hasConsoAdmin = $existing->contains(fn($r) => (int) $r->role_id === $adminId && (int) $r->inst_id === 1);
        if ($hasConsoAdmin && ($isAdmin || $isViewer)) {
            return true;
        }

        // 3. Consortium Viewer covers every Viewer scope (but NOT Admin!)
        $hasConsoViewer = $existing->contains(fn($r) => (int) $r->role_id === $viewerId && (int) $r->inst_id === 1);
        if ($hasConsoViewer && $isViewer) {
            return true;
        }

        // 4. Group Admin covers Group Viewer and any Admin/Viewer on member institutions;
        //    Group Viewer covers ONLY Viewer on member institutions (NOT Admin!)
        if (!is_null($instId) && $instId !== 1) {
            $groupIds = Institution::find($instId)?->institutionGroups()->pluck('institutiongroups.id')->map(fn($id) => (int) $id)->all() ?? [];
            foreach ($groupIds as $gid) {
                $hasGroupAdmin = $existing->contains(fn($r) => (int) $r->role_id === $adminId && (int) $r->group_id === $gid);
                if ($hasGroupAdmin && ($isAdmin || $isViewer)) {
                    return true;
                }
                $hasGroupViewer = $existing->contains(fn($r) => (int) $r->role_id === $viewerId && (int) $r->group_id === $gid);
                if ($hasGroupViewer && $isViewer) {
                    return true;
                }
            }
        }

        // 5. Same-scope Admin covers same-scope Viewer
        if ($isViewer) {
            $hasSameScopeAdmin = $existing->contains(fn($r) =>
                (int) $r->role_id === $adminId
                && (is_null($instId) ? is_null($r->inst_id) : (int) $r->inst_id === $instId)
                && (is_null($groupId) ? is_null($r->group_id) : (int) $r->group_id === $groupId)
            );
            if ($hasSameScopeAdmin) {
                return true;
            }
        }

        return false;
    }


    private function pruneSubsumedRoles(User $user, Role $role, ?int $instId, ?int $groupId, ?int $keepId = null): void
    {
        $viewerRoleId = (int) (Role::where('name', 'Viewer')->value('id') ?? 0);
        $adminRoleId = (int) (Role::where('name', 'Admin')->value('id') ?? 0);
        $roleIdsToPrune = $role->name === 'Viewer'
            ? [$viewerRoleId]
            : array_filter([$viewerRoleId, $adminRoleId]);

        $baseQuery = UserRole::where('user_id', $user->id)
            ->when($keepId !== null, fn($q) => $q->where('id', '<>', $keepId));

        if ($role->name === 'ServerAdmin') {
            $baseQuery->delete();
            return;
        }

        if ($instId === 1) {
            // Consortium Admin prunes all Admin & Viewer roles; Consortium Viewer prunes all Viewer roles
            $baseQuery->whereIn('role_id', $roleIdsToPrune)->delete();
            return;
        }

        if (!is_null($groupId)) {
            $memberInstIds = InstitutionGroup::find($groupId)?->institutions()->pluck('institutions.id')->all() ?? [];
            $baseQuery->where(function ($q) use ($groupId, $memberInstIds, $role, $viewerRoleId, $roleIdsToPrune) {
                // If assigning Group Admin, remove Group Viewer on the same group
                if ($role->name === 'Admin' && $viewerRoleId > 0) {
                    $q->orWhere(function ($sub) use ($groupId, $viewerRoleId) {
                        $sub->where('group_id', $groupId)->where('role_id', $viewerRoleId);
                    });
                }
                // Prune subsumed institution-level roles for member institutions
                if (count($memberInstIds) > 0) {
                    $q->orWhere(function ($sub) use ($memberInstIds, $roleIdsToPrune) {
                        $sub->whereIn('inst_id', $memberInstIds)->whereIn('role_id', $roleIdsToPrune);
                    });
                }
            })->delete();
            return;
        }

        if (!is_null($instId) && $role->name === 'Admin' && $viewerRoleId > 0) {
            $baseQuery->where('inst_id', $instId)->where('role_id', $viewerRoleId)->delete();
        }
    }


    private function formatRoleRow(
        int $id,
        int $roleId,
        string $roleName,
        User $user,
        ?int $instId,
        ?int $groupId,
        ?string $instName = '',
        ?string $groupName = '',
        bool $canAct = true
    ): array {
        $isServerAdmin = ($roleName === 'ServerAdmin');
        $instLabel = (string) ($instName ?? '');
        $groupLabel = (string) ($groupName ?? '');

        return [
            'id'          => $id,
            'role_id'     => $roleId,
            'user_id'     => $user->id,
            'inst_id'     => $instId,
            'group_id'    => $groupId,
            'name'        => $user->name,
            'email'       => $user->email,
            'role_string' => ($instId === 1 && !$isServerAdmin) ? 'Consortium ' . $roleName : $roleName,
            'inst_name'   => $instLabel,
            'group_name'  => $groupLabel,
            'scope'       => $isServerAdmin
                ? 'Server'
                : (!is_null($instId) ? $instLabel : $groupLabel),
            'can_edit'    => $canAct,
            'can_delete'  => $canAct,
        ];
    }

    private function formatRoleRecord(UserRole $userRole, User $user, Role $role): array
    {
        $userRole->load('institution', 'institutiongroup');

        return $this->formatRoleRow(
            (int) $userRole->id,
            (int) $role->id,
            $role->name,
            $user,
            is_null($userRole->inst_id) ? null : (int) $userRole->inst_id,
            is_null($userRole->group_id) ? null : (int) $userRole->group_id,
            $userRole->institution ? $userRole->institution->name : '',
            $userRole->institutiongroup ? $userRole->institutiongroup->name : '',
            true
        );
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
        return ($role->user && str_contains((string) $role->user->email, '@') && $role->user->canManage())
            ? true
            : __('users.forbidden_request');
    }
}
