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
    public function index(Request $request)
    {
        $consortia = Consortium::orderBy('name', 'asc')->get();

        $defaultName  = 'Server Administrator';
        $defaultEmail = config('ccplus.server_admin');

        $adminSubqueryMatch = " where UR.inst_id = 1 and UU.id = UR.user_id and RR.id = UR.role_id and RR.name = 'Admin') is not null";

        foreach ($consortia as $consortium) {
            $adminName  = $defaultName;
            $adminEmail = $defaultEmail;
            $tenantDb   = 'ccplus_' . $consortium->ccp_key;

            try {
                $query  = "select name, email from {$tenantDb}.users as UU where UU.inst_id = 1 and ";
                $query .= "(select UR.user_id from {$tenantDb}.user_roles as UR ";
                $query .= "left join {$tenantDb}.roles as RR on RR.id = UR.role_id";

                $result = DB::select($query . $adminSubqueryMatch);

                if (!empty($result)) {
                    $adminName  = $result[0]->name;
                    $adminEmail = $result[0]->email;
                }
            } catch (\Exception $e) {
                Log::warning("Could not fetch admin details for tenant database [{$tenantDb}]: " . $e->getMessage());
            }

            $consortium->admin_name  = $adminName;
            $consortium->admin_email = $adminEmail;
            $consortium->status      = $consortium->is_active ? 'Active' : 'Inactive';
        }

        return response()->json(['records' => $consortia, 'result' => true], 200);
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
            'admin_user'         => 'required',
            'admin_pass'         => 'required',
            'admin_confirm_pass' => 'required|same:admin_pass',
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

            if ($table === 'users') {
                $data_query .= ' WHERE id=1';
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

        $consortiumData = $request->only('ccp_key', 'name', 'email');
        $consortiumData['is_active'] = 1;

        $consortium = Consortium::create($consortiumData);

        DB::table($conso_db . '.users')->insert([
            [
                'id'        => 2,
                'name'      => 'CC-Plus Administrator',
                'password'  => Hash::make($input['admin_pass']),
                'email'     => $input['admin_user'],
                'inst_id'   => 1,
                'is_active' => 1,
            ],
        ]);

        DB::table($conso_db . '.role_user')->insert(['role_id' => 99, 'user_id' => 2]);

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
        $consortium = Consortium::findOrFail($id);

        if (!$request->exists('is_active')) {
            return $this->respondError(__('users.conso_missing_args'));
        }

        $input = $request->all();
        $consortium->is_active = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        if (count($input) > 1) {
            if (!array_key_exists('name', $input) || !array_key_exists('email', $input)) {
                if ($request->has(['name', 'email'])) {
                    $consortium->name = $request->input('name');
                    $consortium->email = $request->input('email');
                }
            } else {
                $consortium->name = $request->input('name');
                $consortium->email = $request->input('email');
            }
        }

        $consortium->save();

        return response()->json([
            'result'     => true,
            'consortium' => $consortium,
            'msg'        => __('users.conso_updated', ['name' => $consortium->name]),
        ]);
    }
}
