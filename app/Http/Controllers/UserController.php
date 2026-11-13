<?php

namespace App\Http\Controllers;

use App\Models\Consortium;
use App\Models\Institution;
use App\Models\InstitutionGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class UserController extends BaseController
{
    protected string $modelClass = User::class;
    protected string $modelLabel = 'User';

    /**
     * Return listing of the resource.
     */
    public function index(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return $this->respondError(__('users.forbidden_request'));
        }

        $data = [];
        $limit_to_insts = [];
        $server_admin = config('ccplus.server_admin');

        $filter_options = [
            'statuses' => ['ALL', 'Active', 'Inactive'],
            'roles'    => Role::where('name', '<>', 'ServerAdmin')->get(['id', 'name'])->toArray(),
        ];

        if (!$thisUser->isServerAdmin()) {
            $limit_to_insts = array_map('intval', (array) $thisUser->adminInsts());
            if ($limit_to_insts === array(1)) {
                $limit_to_insts = [];
            }
        }

        $user_data = User::with('roles', 'institution:id,name')
            ->when(count($limit_to_insts) > 0, fn($qry) => $qry->whereIn('inst_id', $limit_to_insts))
            ->where('email', '<>', $server_admin)
            ->orderBy('name', 'ASC')
            ->get();

        foreach ($user_data as $rec) {
            $inst_roles = [];
            $group_roles = [];
            foreach ($rec->allRoles() as $r) {
                $_name = preg_replace('/ /', '', $r['name']);
                if (!is_null($r['inst_id'])) {
                    $inst_roles[] = $_name . ':' . $r['inst_id'];
                }
                if (!is_null($r['group_id'])) {
                    $group_roles[] = $_name . ':' . $r['group_id'];
                }
            }

            $data[] = [
                'id'          => $rec->id,
                'email'       => $rec->email,
                'name'        => $rec->name,
                'inst_id'     => $rec->inst_id,
                'institution' => $rec->institution,
                'password'    => '',
                'phone'       => $rec->phone,
                'last_login'  => $rec->last_login,
                'status'      => $rec->is_active ? 'Active' : 'Inactive',
                'user_role'   => $rec->fullRoleName(),
                'inst_roles'  => $inst_roles,
                'group_roles' => $group_roles,
                'fiscalYr'    => $rec->fiscalYr ?: config('ccplus.fiscalYr'),
                'can_edit'    => $rec->canManage(),
                'can_delete'  => $rec->canManage(),
            ];
        }

        $filter_options['uroles'] = [];
        $all_roles = Role::where('id', '<=', $thisUser->maxRole())
            ->orderBy('id', 'DESC')
            ->where('name', '<>', 'ServerAdmin')
            ->get(['name', 'id'])
            ->toArray();

        foreach ($all_roles as $r) {
            if ($r['name'] === 'Admin' && $thisUser->isConsoAdmin()) {
                $filter_options['uroles'][] = 'Consortium Admin';
            }
            if ($r['name'] === 'Viewer' && $thisUser->isConsoAdmin()) {
                $filter_options['uroles'][] = 'Consortium Viewer';
            }
            $filter_options['uroles'][] = $r['name'];
        }

        $instIds = $user_data->pluck('inst_id')->toArray();
        $filter_options['institutions'] = $thisUser->isConsoAdmin()
            ? Institution::orderBy('name', 'ASC')->get(['id', 'name'])->toArray()
            : Institution::orderBy('name', 'ASC')->whereIn('id', $instIds)->get(['id', 'name'])->toArray();

        return response()->json(['records' => $data, 'options' => $filter_options, 'result' => true], 200);
    }

    /**
     * Settings method to return user settings for profile page.
     */
    public function settings(User $user)
    {
        $thisUser = auth()->user();
        if ($thisUser->id != $user->id && !$user->canManage()) {
            return $this->respondError(__('users.forbidden_request'));
        }

        $user->load('roles', 'institution:id,name');
        $data = $user->toArray();
        $data['inst_name'] = $user->institution->name;
        $data['fiscalYr'] = $user->fiscalYr ?: config('ccplus.fiscalYr');

        $token = $user->tokens()->where('name', 'PAT_User_' . $user->id)->first();
        $data['access_token'] = $token ? Crypt::decryptString($user->enc_api_token) : null;
        $data['token_expires'] = $token ? $token->expires_at : null;

        return response()->json(['records' => $data, 'options' => [], 'result' => true], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $thisUser = auth()->user();
        if (!$thisUser->isAdmin()) {
            return $this->respondError(__('users.operation_forbidden'));
        }

        $this->validate($request, [
            'name'     => 'required',
            'email'    => 'required|email',
            'password' => 'required|same:confirm_pass',
            'inst_id'  => 'required',
        ]);

        $input = $request->all();
        if ($thisUser->inst_id != $input['inst_id'] && !$thisUser->isConsoAdmin()) {
            return $this->respondError(__('users.operation_forbidden'));
        }

        if (User::where('email', $input['email'])->exists()) {
            return $this->respondError(__('users.email_exists'));
        }

        $input['is_active'] = (isset($input['status']) && $input['status'] === 'Active') ? 1 : 0;

        $user = User::create($input);

        $viewer_role_id = Role::where('name', 'Viewer')->value('id');
        try {
            UserRole::create([
                'user_id'  => $user->id,
                'role_id'  => $viewer_role_id,
                'inst_id'  => $user->inst_id,
                'group_id' => null,
            ]);
        } catch (\Exception $e) {
            return $this->respondError(__('users.role_db_error', ['error' => $e->getMessage()]));
        }

        $user->load(['institution:id,name']);

        $consortia = Consortium::where('is_active', 1)->get();
        $con_name = '';
        if ($consortia->count() > 1) {
            $current = $consortia->where('ccp_key', $request->header('X-Tenant'))->first();
            $con_name = $current ? $current->name : '';
        }

        try {
            Mail::to($input['email'])->send(new \App\Mail\NewUser($con_name, [
                'name'     => $user->name,
                'password' => $input['password'],
            ]));
        } catch (\Exception $e) {
            // Fail silently if mail transport is not configured
        }

        $new_user = [
            'id'          => $user->id,
            'email'       => $user->email,
            'name'        => $user->name,
            'inst_id'     => $user->inst_id,
            'institution' => $user->institution,
            'last_login'  => null,
            'status'      => $user->is_active ? 'Active' : 'Inactive',
            'user_role'   => $user->fullRoleName(),
            'fiscalYr'    => $user->fiscalYr ?: config('ccplus.fiscalYr'),
            'can_edit'    => true,
            'can_delete'  => true,
        ];

        return $this->respondSuccess(__('users.created'), $new_user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $thisUser = auth()->user();

        if (
            !$user->canManage() ||
            ($user->isServerAdmin() && $user->email === config('ccplus.server_admin'))
        ) {
            return $this->respondError(__('users.update_forbidden'));
        }

        $fields = [];
        if (isset($request->password)) {
            $fields['password'] = 'same:confirm_pass';
        }
        if (isset($request->email) && $request->email !== 'Administrator') {
            $fields['email'] = 'email|unique:consodb.users,email,' . $user->id;
        }
        if (count($fields) > 0) {
            $this->validate($request, $fields);
        }

        $input = $request->all();
        if (empty($input['password'])) {
            $input = Arr::except($input, ['password']);
        }
        $input = Arr::except($input, ['confirm_pass']);

        $targetInstId = $input['inst_id'] ?? $user->inst_id;
        if (
            !$thisUser->isConsoAdmin() &&
            (!$thisUser->hasRole('Admin', $user->inst_id) || !$thisUser->hasRole('Admin', $targetInstId))
        ) {
            return $this->respondError(__('users.update_forbidden'));
        }

        if (isset($input['fiscalYr'])) {
            $months = [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
            ];
            if (!in_array($input['fiscalYr'], $months, true) || $input['fiscalYr'] === config('ccplus.fiscalYr')) {
                $input['fiscalYr'] = null;
            }
        }

        $all_roles = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        if (isset($input['roles'])) {
            $new_roles = $input['roles'];
            $current_user_roles = UserRole::where('user_id', $user->id)->get();

            $skipped_insts = [];
            foreach ($new_roles as $r_new) {
                $roleId = $r_new['role_id'] ?? $r_new['id'] ?? null;
                $roleInstId = $r_new['inst_id'] ?? $user->inst_id;
                $roleName = $r_new['name'] ?? $all_roles->where('id', $roleId)->value('name');

                if (!$roleId || !$roleName) {
                    continue;
                }

                if ($thisUser->maxRole($roleInstId) < $roleId) {
                    $skipped_insts[] = $roleInstId;
                    continue;
                }
                if (!$user->hasRole($roleName, $roleInstId)) {
                    UserRole::create([
                        'user_id' => $user->id,
                        'inst_id' => $roleInstId,
                        'role_id' => $roleId,
                    ]);
                }
            }
            foreach ($current_user_roles as $role) {
                $stillPresent = collect($new_roles)->first(function ($r) use ($role) {
                    $rId = $r['role_id'] ?? $r['id'] ?? null;
                    return ($rId == $role->role_id && ($r['inst_id'] ?? null) == $role->inst_id);
                });
                if (!$stillPresent && !in_array($role->inst_id, $skipped_insts)) {
                    UserRole::where('id', $role->id)->delete();
                }
            }
            $input = Arr::except($input, ['roles']);
        }
        $user->update($input);
        $user->load(['institution:id,name', 'roles']);
        $inst_roles = [];
        $group_roles = [];
        foreach ($user->allRoles() as $r) {
            $_name = preg_replace('/ /', '', $r['name']);
            if (!is_null($r['inst_id'])) {
                $inst_roles[] = $_name . ':' . $r['inst_id'];
            }
            if (!is_null($r['group_id'])) {
                $group_roles[] = $_name . ':' . $r['group_id'];
            }
        }
        $updated_user = $user->toArray();
        $updated_user['status'] = $user->is_active ? 'Active' : 'Inactive';
        $updated_user['inst_name'] = $user->institution->name;
        $updated_user['fiscalYr'] = $user->fiscalYr ?: config('ccplus.fiscalYr');
        $updated_user['user_role'] = $user->fullRoleName();
        $updated_user['inst_roles'] = $inst_roles;
        $updated_user['group_roles'] = $group_roles;
        $updated_user['can_edit'] = $user->canManage();
        $updated_user['can_delete'] = $user->canManage();
        return $this->respondSuccess(__('users.updated'), $updated_user);
    }
    /**
     * Enforce authorization and self-deletion guards before BaseController deletes the user.
     */
    protected function canDeleteRecord(Model $user): bool|string
    {
        if (!$user->canManage() || ($user->isServerAdmin() && $user->email === config('ccplus.server_admin'))) {
            return __('users.delete_forbidden');
        }
        if (auth()->id() == $user->id) {
            return __('users.self_delete_forbidden');
        }
        return true;
    }
    /**
     * Restrict bulk user actions: exclude ServerAdmin, current user, and out-of-scope institutions.
     */
    protected function scopeBulkQuery($query, $user): void
    {
        $query->where('email', '<>', config('ccplus.server_admin'))
            ->where('id', '<>', $user->id);
        if (!$user->isConsoAdmin()) {
            $limitInsts = $user->adminInsts();
            if (count($limitInsts) > 0) {
                $query->whereIn('inst_id', $limitInsts);
            }
        }
    }
    /**
     * Import users from a CSV file to the database.
     */
    public function import(Request $request)
    {
        $thisUser = auth()->user();
        abort_unless($thisUser->hasAnyRole(['Admin']), 403);
        $parsed = $this->parseCsvUpload($request);
        if (isset($parsed['error'])) {
            return $this->respondError($parsed['error']);
        }
        $rows = $parsed['rows'];
        $users = User::with('roles', 'institution:id,name')->orderBy('name', 'ASC')->get();
        $institutions = Institution::get();
        $all_groups = InstitutionGroup::get();
        $all_roles = Role::orderBy('id', 'ASC')->get(['name', 'id']);
        $num_skipped = 0;
        $num_updated = 0;
        $num_created = 0;
        foreach ($rows as $row) {
            $rawId = Arr::get($row, 0, '');
            if ($rawId === '' || !is_numeric($rawId) || count($row) < 2) {
                continue;
            }
            $cur_user_id = intval($rawId);
            $_email = trim(Arr::get($row, 1, ''));
            if ($_email === 'ServerAdmin' || strlen($_email) < 1) {
                $num_skipped++;
                continue;
            }
            $current_user = $users->where('id', $cur_user_id)->first();
            if ($current_user) {
                $existing_user = $users->where('name', $_email)->first();
                if (!is_null($existing_user)) {
                    $_email = trim($current_user->email);
                }
            } else {
                $current_user = $users->where('email', $_email)->first();
                if (!is_null($current_user)) {
                    $_email = trim($current_user->email);
                }
            }
            $rawPassword = trim(Arr::get($row, 2, ''));
            if (is_null($current_user) && $rawPassword === '') {
                $num_skipped++;
                continue;
            }
            $rawName   = trim(Arr::get($row, 3, ''));
            $rawPhone  = trim(Arr::get($row, 4, ''));
            $rawStatus = trim(Arr::get($row, 5, ''));
            $rawInst   = trim(Arr::get($row, 6, ''));
            $_name   = strlen($rawName) > 0 ? $rawName : null;
            $_phone  = strlen($rawPhone) > 0 ? $rawPhone : null;
            $_active = strtolower($rawStatus) === 'inactive' ? 0 : 1;
            $_inst   = strlen($rawInst) > 0 ? intval($rawInst) : 1;
            $user_inst = $institutions->where('id', $_inst)->first();
            if (!$user_inst || !$user_inst->canManage()) {
                $num_skipped++;
                continue;
            }
            $_user = [
                'id'        => $cur_user_id,
                'email'     => $_email,
                'name'      => $_name,
                'phone'     => $_phone,
                'is_active' => $_active,
                'inst_id'   => $_inst,
            ];
            if (strlen($rawPassword) > 0) {
                $_user['password'] = $rawPassword;
            }
            if (is_null($current_user)) {
                $current_user = User::create($_user);
                $users->push($current_user);
                $num_created++;
            } else {
                $current_user->update($_user);
                $num_updated++;
            }
            $import_roles = collect([]);
            // Institution roles (col 7)
            $rawInstRoles = trim(Arr::get($row, 7, ''));
            if ($rawInstRoles !== '') {
                $inst_roles = preg_split('/,/', preg_replace('/, /', ',', $rawInstRoles));
                foreach ($inst_roles as $inputRole) {
                    $res = preg_split('/:/', preg_replace('/: /', ':', $inputRole));
                    if (count($res) < 2) {
                        continue;
                    }
                    $role = $all_roles->where('name', ucwords(trim(Arr::get($res, 0, ''))))->first();
                    if (!$role) {
                        continue;
                    }
                    $_instID = intval(trim(Arr::get($res, 1, '0')));
                    $_instObj = $institutions->where('id', $_instID)->first();
                    if (!$_instObj || !$_instObj->canManage()) {
                        continue;
                    }
                    $import_roles[] = [
                        'user_id'  => $current_user->id,
                        'role_id'  => $role->id,
                        'inst_id'  => $_instID,
                        'group_id' => null,
                    ];
                }
            }
            // Group roles (col 8)
            $rawGroupRoles = trim(Arr::get($row, 8, ''));
            if ($rawGroupRoles !== '') {
                $group_roles = preg_split('/,/', preg_replace('/, /', ',', $rawGroupRoles));
                foreach ($group_roles as $inputRole) {
                    $res = preg_split('/:/', preg_replace('/: /', ':', $inputRole));
                    if (count($res) < 2) {
                        continue;
                    }
                    $role = $all_roles->where('name', ucwords(trim(Arr::get($res, 0, ''))))->first();
                    if (!$role) {
                        continue;
                    }
                    $_groupID = intval(trim(Arr::get($res, 1, '0')));
                    $group = $all_groups->where('id', $_groupID)->first();
                    if (!$group || !$group->canManage()) {
                        continue;
                    }
                    $import_roles[] = [
                        'user_id'  => $current_user->id,
                        'role_id'  => $role->id,
                        'inst_id'  => null,
                        'group_id' => $_groupID,
                    ];
                }
            }
            foreach ($current_user->roles as $ur) {
                $keep_role = $import_roles
                    ->where('user_id', $ur->user_id)
                    ->where('role_id', $ur->role_id)
                    ->where('inst_id', $ur->inst_id)
                    ->where('group_id', $ur->group_id)
                    ->first();
                if (!$keep_role) {
                    UserRole::where('id', $ur->id)->delete();
                }
            }
            foreach ($import_roles as $ir) {
                $role_exists = $current_user->roles
                    ->where('user_id', $ir['user_id'])
                    ->where('role_id', $ir['role_id'])
                    ->where('inst_id', $ir['inst_id'])
                    ->where('group_id', $ir['group_id'])
                    ->first();
                if (!$role_exists) {
                    UserRole::create($ir);
                }
            }
        }
        $msg = $this->formatImportSummary(
            __('users.import_success'),
            $num_updated,
            $num_created,
            0,
            $num_skipped
        );
        return $this->respondSuccess($msg);
    }
}
