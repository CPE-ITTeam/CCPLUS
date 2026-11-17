<?php

namespace App\Http\Controllers;

use App\Models\Consortium;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ConsortiumController extends BaseController
{
    protected string $modelClass = Consortium::class;
    protected string $modelLabel = 'Instance';

    /**
     * Return a JSON array of known consortia, including inactive ones.
     */
      private function normalizeFiscalYr($value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }
        if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 12) {
            $value = \Carbon\Carbon::create(null, (int) $value, 1)->format('F');
        }
        $parsed = date_parse((string) $value);
        if ($parsed['error_count'] > 0 || empty($parsed['month'])) {
            return null;
        }
        $monthName = \Carbon\Carbon::create(null, (int) $parsed['month'], 1)->format('F');
        return ($monthName === config('ccplus.fiscalYr')) ? null : $monthName;
    }

    public function index(Request $request)
    {
        $isServerAdmin = auth()->check() && auth()->user()->isServerAdmin();
        $consortia = Consortium::orderBy('name', 'asc')->get();

        $defaultName     = 'Server Administrator';
        $defaultEmail    = config('ccplus.server_admin');
        $defaultLanguage = config('ccplus.language', config('app.locale', 'en'));

        foreach ($consortia as $consortium) {
            $adminName  = $defaultName;
            $adminEmail = $defaultEmail;
            $tenantDb   = 'ccplus_' . $consortium->ccp_key;

            try {
                $adminUser = DB::table("{$tenantDb}.users as UU")
                    ->join("{$tenantDb}.role_user as RU", 'UU.id', '=', 'RU.user_id')
                    ->where('UU.inst_id', 1)
                    ->where('RU.role_id', 99)
                    ->orderBy('UU.id', 'asc')
                    ->select('UU.name', 'UU.email')
                    ->first();

                if ($adminUser) {
                    $adminName  = $adminUser->name;
                    $adminEmail = $adminUser->email;
                }
            } catch (\Exception $e) {
                Log::warning("Could not fetch admin details for tenant database [{$tenantDb}]: " . $e->getMessage());
            }

            $consortium->admin_name  = $adminName;
            $consortium->admin_email = $adminEmail;
            $consortium->fiscalYr    = $consortium->effectiveFiscalYr();
            $consortium->language    = $consortium->language ?: $defaultLanguage;
            $consortium->theme       = $consortium->theme ?: 'light';
            $consortium->status      = $consortium->is_active ? 'Active' : 'Inactive';
            $consortium->can_edit    = $isServerAdmin;
            $consortium->can_delete  = $isServerAdmin;
        }

        $months = collect(range(1, 12))->map(function ($m) {
            $name = \Carbon\Carbon::create(2024, $m, 1)->format('F');
            return ['id' => $name, 'name' => $name];
        })->values()->all();

        $languages = collect(['en', 'de', 'es', 'fi', 'fr', 'hu'])->map(fn ($code) => [
            'id'   => $code,
            'name' => strtoupper($code),
        ])->values()->all();

        $themes = [
            ['id' => 'light',   'name' => 'Classic Blue (Default)'],
            ['id' => 'emerald', 'name' => 'Forest Teal'],
            ['id' => 'indigo',  'name' => 'Academic Indigo'],
            ['id' => 'plum',    'name' => 'Burgundy Plum'],
            ['id' => 'slate',   'name' => 'Warm Charcoal'],
        ];

        return response()->json([
            'records' => $consortia,
            'options' => [
                'defaultFiscalYr' => config('ccplus.fiscalYr'),
                'fiscalYr'        => $months,
                'language'        => $languages,
                'theme'           => $themes,
            ],
            'result'  => true,
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'ccp_key'            => 'required|max:10|unique:globaldb.consortia,ccp_key',
            'name'               => 'required',
            'email'              => 'required|email',
            'admin_pass'         => 'required',
            'admin_confirm_pass' => 'required|same:admin_pass',
            'language'           => 'nullable|string|max:10',
            'theme'              => 'nullable|string|in:light,emerald,indigo,plum,slate',
        ], [
            'ccp_key.unique' => __('validation.duplicate_ccp_key', ['value' => $request->input('ccp_key')]),
        ]);

        $input = $request->all();

        $conso_db = 'ccplus_' . $input['ccp_key'];
        $count = DB::table('information_schema.tables')->where('TABLE_SCHEMA', '=', $conso_db)->count();
        if ($count > 0) {
            return response()->json([
                'errors' => ['ccp_key' => [__('validation.duplicate_ccp_key', ['value' => $input['ccp_key']])]],
            ], 422);
        }

        $server_admin = config('ccplus.server_admin');
        $server_admin_pass = config('ccplus.server_admin_pass');
        if (strlen((string) $server_admin) === 0 || strlen((string) $server_admin_pass) === 0) {
            return $this->respondError(__('users.conso_admin_undefined'));
        }

        $_host         = config('database.connections.con_template.host');
        $db_admin_user = config('database.connections.globaldb.username');
        $db_conso_user = config('database.connections.consodb.username');

        try {
            DB::statement("CREATE DATABASE `{$conso_db}`");
        } catch (\Exception $dbError) {
            return $this->respondError(__('users.conso_db_create_failed', ['error' => $dbError->getMessage()]), 500);
        }

        DB::purge('consodb');
        config(['database.connections.consodb.database' => $conso_db]);
        session(['ccp_key' => $input['ccp_key']]);
        DB::reconnect('consodb');

        $migration_path = base_path() . '/database/migrations/con_template/*.*';
        $table_names = [];
        foreach (glob($migration_path) as $file) {
            $name_beg = strpos($file, '_create_') + 8;
            if ($name_beg > 8) {
                $name_end = strpos($file, '_table.') - 1;
                $table_names[] = substr($file, $name_beg, $name_end - $name_beg + 1);
            }
        }

        $_qry1 = 'SHOW CREATE TABLE ccplus_con_template.ZZZZ';
        $_qry2 = 'INSERT INTO ZZZZ SELECT * FROM ccplus_con_template.ZZZZ';
        $ctField = 'Create Table';

        foreach ($table_names as $table) {
            $show_query = str_replace('ZZZZ', $table, $_qry1);
            $result = DB::select($show_query);
            $raw_command = $result[0]->$ctField;
            if (strpos($raw_command, '`') === false) {
                return $this->respondError(__('users.conso_clone_failed', ['table' => $table]));
            }

            $command = preg_replace('/ AUTO_INCREMENT=(\d+)/', '', $raw_command);
            $data_query = str_replace('ZZZZ', $table, $_qry2);

            if ($table === 'users' || $table === 'institutions') {
                $data_query .= ' WHERE id=1';
            } elseif (in_array($table, ['institution_groups', 'sushi_settings', 'harvest_logs', 'saved_reports', 'failed_harvests', 'alerts'], true)) {
                $data_query .= ' WHERE 1=0';
            }

            try {
                DB::connection('consodb')->statement($command);
                DB::connection('consodb')->statement($data_query);
            } catch (\Exception $e) {
                return $this->respondError(__('users.conso_schema_failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]), 422);
            }
        }

        $exitCode = Artisan::call('db:seed');
        if ($exitCode !== 0) {
            return $this->respondError(__('users.conso_seed_failed', ['code' => $exitCode]));
        }

        try {
            DB::statement("GRANT ALL PRIVILEGES ON `{$conso_db}`.* TO '{$db_admin_user}'@'{$_host}'");
            DB::statement("GRANT SELECT ON `{$conso_db}`.* TO '{$db_conso_user}'@'{$_host}'");
            DB::statement("GRANT UPDATE ON `{$conso_db}`.users TO '{$db_conso_user}'@'{$_host}'");
            DB::statement('FLUSH PRIVILEGES');
        } catch (\Exception $grantError) {
            return $this->respondError(__('users.conso_grant_failed', ['error' => $grantError->getMessage()]), 500);
        }

        $defaultLanguage = config('ccplus.language', config('app.locale', 'en'));
        $consortiumData = $request->only('ccp_key', 'name', 'email');
        $consortiumData['fiscalYr']  = $this->normalizeFiscalYr($input['fiscalYr'] ?? null);
        $consortiumData['language']  = trim((string) ($input['language'] ?? '')) ?: $defaultLanguage;
        $consortiumData['theme']     = trim((string) ($input['theme'] ?? '')) ?: 'light';
        $consortiumData['is_active'] = 1;

        $consortium = Consortium::create($consortiumData);

        $adminDisplayName = trim((string) ($input['admin_name'] ?? '')) ?: 'CC-Plus Administrator';

        DB::table($conso_db . '.users')->insert([
            [
                'id'        => 2,
                'name'      => $adminDisplayName,
                'password'  => Hash::make($input['admin_pass']),
                'email'     => trim((string) $input['email']),
                'inst_id'   => 1,
                'is_active' => 1,
            ],
        ]);

        DB::table($conso_db . '.role_user')->insert(['role_id' => 99, 'user_id' => 2]);

        $consortium->admin_name  = $adminDisplayName;
        $consortium->admin_email = trim((string) $input['email']);
        $consortium->fiscalYr    = $consortium->effectiveFiscalYr();
        $consortium->status      = 'Active';
        $consortium->can_edit    = true;
        $consortium->can_delete  = true;

        return response()->json([
            'result'     => true,
            'consortium' => $consortium,
            'msg'        => __('users.conso_created', ['name' => $consortium->name]),
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $consortium = Consortium::findOrFail($id);
        return response()->json(['consortium' => $consortium], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $thisUser = auth()->user();
        if (!$thisUser || !$thisUser->isServerAdmin()) {
            return $this->respondError(__('connections.forbidden_request'), 403);
        }

        $this->validate($request, [
            'email'    => 'nullable|email',
            'language' => 'nullable|string|max:10',
            'theme'    => 'nullable|string|in:light,emerald,indigo,plum,slate',
        ]);

        $consortium = Consortium::findOrFail($id);

        if ($request->has('status')) {
            $consortium->is_active = ($request->input('status') === 'Active') ? 1 : 0;
        } elseif ($request->has('is_active')) {
            $consortium->is_active = $request->boolean('is_active') ? 1 : 0;
        }

        if ($request->filled('name')) {
            $consortium->name = trim((string) $request->input('name'));
        }

        if ($request->filled('email')) {
            $consortium->email = trim((string) $request->input('email'));
        }

        if ($request->has('fiscalYr')) {
            $consortium->fiscalYr = $this->normalizeFiscalYr($request->input('fiscalYr'));
        }

        if ($request->has('language')) {
            $lang = trim((string) $request->input('language'));
            $consortium->language = strlen($lang) > 0 ? $lang : null;
        }

        if ($request->has('theme')) {
            $theme = trim((string) $request->input('theme'));
            $consortium->theme = strlen($theme) > 0 ? $theme : 'light';
        }

        // Never allow ccp_key to be changed after creation
        $consortium->save();

        $tenantDb = 'ccplus_' . $consortium->ccp_key;
        $adminName = $request->filled('admin_name')
            ? trim((string) $request->input('admin_name'))
            : null;

        if ($adminName !== null) {
            try {
                DB::table("{$tenantDb}.users")
                    ->where('id', 2)
                    ->update(['name' => $adminName]);
            } catch (\Exception $e) {
                Log::warning("Could not update admin name for tenant database [{$tenantDb}]: " . $e->getMessage());
            }
        }

        $defaultLanguage = config('ccplus.language', config('app.locale', 'en'));
        $consortium->admin_name = $adminName ?? 'CC-Plus Administrator';
        $consortium->fiscalYr   = $consortium->effectiveFiscalYr();
        $consortium->language   = $consortium->language ?: $defaultLanguage;
        $consortium->theme      = $consortium->theme ?: 'light';
        $consortium->status     = $consortium->is_active ? 'Active' : 'Inactive';
        $consortium->can_edit   = true;
        $consortium->can_delete = true;

        return response()->json([
            'result' => true,
            'record' => $consortium,
            'msg'    => __('users.conso_updated', ['name' => $consortium->name]),
        ]);
    }



    /**
     * Return the active tenant's Consortium record for Consortium Admins.
     */
    public function current(Request $request)
    {
        if (!auth()->user() || !auth()->user()->isConsoAdmin()) {
            return $this->respondError(__('users.update_forbidden'), 403);
        }

        $ccpKey = $request->header('X-Tenant') ?: session('ccp_key');
        $consortium = Consortium::where('ccp_key', $ccpKey)->firstOrFail();
        $consortium->fiscalYr = $consortium->effectiveFiscalYr();

        // Ensure status string ('Active' / 'Inactive') is present for the UI switch
        if (!isset($consortium->status) && isset($consortium->is_active)) {
            $consortium->status = $consortium->is_active ? 'Active' : 'Inactive';
        }

        $defaultLanguage = config('ccplus.language', config('app.locale', 'en'));
        if (empty($consortium->language)) {
            $consortium->language = $defaultLanguage;
        }

        if (empty($consortium->theme)) {
            $consortium->theme = 'light';
        }

        return response()->json([
            'result'          => true,
            'consortium'      => $consortium,
            'defaultFiscalYr' => config('ccplus.fiscalYr'),
            'defaultLanguage' => $defaultLanguage,
        ], 200);
    }


    /**
     * Update the active tenant's Consortium name and email (Consortium Admin only).
     */
    public function updateCurrent(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isConsoAdmin()) {
            return $this->respondError(__('users.update_forbidden'), 403);
        }

        $this->validate($request, [
            'name'       => 'required|string|max:255',
            'admin_name' => 'nullable|string|max:255',
            'email'      => 'required|email|max:255',
            'language'   => 'nullable|string|max:10',
            'theme'      => 'nullable|string|in:light,emerald,indigo,plum,slate',
        ]);

        $ccpKey = $request->header('X-Tenant') ?: session('ccp_key');
        $consortium = Consortium::where('ccp_key', $ccpKey)->firstOrFail();

        $consortium->name = trim((string) $request->input('name'));
        $consortium->email = trim((string) $request->input('email'));

        if ($request->has('admin_name')) {
            $consortium->admin_name = trim((string) $request->input('admin_name')) ?: null;
        }

        if ($request->has('fiscalYr')) {
            $consortium->fiscalYr = $this->normalizeFiscalYr($request->input('fiscalYr'));
        }

        if ($request->has('language')) {
            $lang = trim((string) $request->input('language'));
            $consortium->language = strlen($lang) > 0 ? $lang : null;
        }

        if ($request->has('theme')) {
            $theme = trim((string) $request->input('theme'));
            $consortium->theme = strlen($theme) > 0 ? $theme : 'light';
        }

        // Only ServerAdmin may toggle consortium status
        if ($user->isServerAdmin() && $request->has('status')) {
            $consortium->is_active = ($request->input('status') === 'Active' || $request->input('status') === true || $request->input('status') == 1) ? 1 : 0;
        }

        $consortium->save();
        $consortium->fiscalYr = $consortium->effectiveFiscalYr();

        if (!isset($consortium->status) && isset($consortium->is_active)) {
            $consortium->status = $consortium->is_active ? 'Active' : 'Inactive';
        }

        if (empty($consortium->theme)) {
            $consortium->theme = 'light';
        }

        return response()->json([
            'result'     => true,
            'consortium' => $consortium,
            'msg'        => __('users.conso_updated', ['name' => $consortium->name]),
        ], 200);
    }

}
