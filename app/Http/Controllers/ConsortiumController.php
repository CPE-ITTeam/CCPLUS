<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\StreamOutput;
use DB;
use Hash;
use App\Models\Consortium;

class ConsortiumController extends BaseController
{
    /**
     * Return a (JSON) array of known, active consortia
     */
    public function index(Request $request) {
        // 💡 FIXED: Added ->orderBy('name', 'asc') to enforce alphabetical sorting natively at the database level!
        $data = Consortium::where('is_active', true)
                            ->orderBy('name', 'asc')
                            ->get();

        // Set default conso-admin to ServerAdmin in case one is not defined
        $defaultName  = "Server Administrator";
        $defaultEmail = config('ccplus.server_admin');

        // Add an administrator name and email for each instance to the data records
        $where = " where UR.inst_id=1 and UU.id=UR.user_id and RR.id=UR.role_id and RR.name='Admin') is not null";
        foreach ($data as $idx => $con) {
            $qry  = "select name,email from ccplus_" . $con->ccp_key . ".users as UU where UU.inst_id=1 and ";
            $qry .= "(select UR.user_id from ccplus_" . $con->ccp_key . ".user_roles as UR ";
            $qry .= "left join ccplus_" . $con->ccp_key . ".roles as RR on RR.id=UR.role_id";
            // Set the first result from the user table, or default it
            $result = DB::select($qry.$where);
            $data[$idx]['admin_name']  = (count($result) > 0) ? $result[0]->name  : $defaultName;
            $data[$idx]['admin_email'] = (count($result) > 0) ? $result[0]->email : $defaultEmail;
            // Return is_active as "status" string
            $data[$idx]['status'] = ($con->is_active) ? "Active" : "Inactive";
        }
        return response()->json(['records' => $data, 'result' => true], 200);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
public function store(Request $request)
{
    // Validate request and put fields into $input
    $this->validate($request, [
        // 1. Checks that it's required, max 10 chars
        // 2. Checks the master 'consortia' table to guarantee the ccp_key is UNIQUE
        'ccp_key' => 'required|max:10|unique:globaldb.consortia,ccp_key',
        'name' => 'required',
        'email' => 'required|email',
        'admin_user' => 'required',
        'admin_pass' => 'required',
        'admin_confirm_pass' => 'required|same:admin_pass',
    ], [
        // Links the unique constraint failure straight to your Lang definition file!
        'ccp_key.unique' => __('validation.duplicate_ccp_key', ['value' => $request->input('ccp_key')]),
    ]);

    $input = $request->all();

    // ─── DEFENSIVE DATABASE PRESENCE CHECK ───
    // Just in case the database folder exists but the consortium table record was deleted
    $conso_db = "ccplus_" . $input['ccp_key'];
    $count = DB::table('information_schema.tables')->where("TABLE_SCHEMA", "=", $conso_db)->count();
    if ($count > 0) {
        // Returning a 422 status code forces your Vue/Axios script straight into its catch block
        return response()->json([
            'errors' => ['ccp_key' => [__('validation.duplicate_ccp_key', ['value' => $input['ccp_key']])]]
        ], 422);
    }

    // Get server admin credential
    $server_admin = config('ccplus.server_admin');
    $server_admin_pass = config('ccplus.server_admin_pass');
    if (strlen($server_admin) == 0 || strlen($server_admin_pass) == 0) {
        return response()->json(['result' => false, 'msg' => 'Server Admin credential is not properly defined!']);
    }

    // Get global and template database table names and connection details
    $global_db   = \Config::get('database.connections.globaldb.database');
    $template_db = \Config::get('database.connections.con_template.database');
    $_host = \Config::get('database.connections.con_template.host');
    $_cset = \Config::get('database.connections.con_template.charset');
    $_coll = \Config::get('database.connections.con_template.collation');
    $db_admin_user = \Config::get('database.connections.globaldb.username');
    $db_admin_pass = \Config::get('database.connections.globaldb.password');
    $db_conso_user = \Config::get('database.connections.consodb.username');
    $db_conso_pass = \Config::get('database.connections.consodb.password');

    // Create the database safely
    try {
        DB::statement("CREATE DATABASE `{$conso_db}`");
    } catch (\Exception $dbError) {
        return response()->json(['result' => false, 'msg' => 'Failed to create database space: ' . $dbError->getMessage()], 500);
    }

    // Set configuration to use the new database as consodb
    config(['database.connections.consodb.database' => $conso_db]);
    session(['ccp_key' => $input['ccp_key']]);
    DB::reconnect('consodb');

    // Create tables in the new database based on the template database
    $migration_path = base_path() . "/database/migrations/con_template/*.*";
    $table_names = array();
    foreach( glob($migration_path) as $file) {
        $name_beg = strpos($file, "_create_")+8;
        if ($name_beg > 8) {
            $name_end = strpos($file, "_table.") - 1;
            $table_names[] = substr($file, $name_beg, $name_end - $name_beg + 1);
        }
    }

    // Retrieve the CREATE TABLE commands for each table and create them in the new database
    $_qry1 = "SHOW CREATE TABLE ccplus_con_template.ZZZZ";
    $_qry2 = "INSERT INTO ZZZZ SELECT * FROM ccplus_con_template.ZZZZ";
    $ctField = "Create Table";
    
    foreach ($table_names as $table) {
        $show_query = str_replace('ZZZZ', $table, $_qry1);
        $result = DB::select($show_query);
        $raw_command = $result[0]->$ctField;
        $pos = strpos($raw_command, "`");
        if ($pos == false) {
            return response()->json(['result' => false, 'msg' => 'Database cloning failed! Cannot build clone commands for: ' . $table]);
        }
        
        // Zap any AUTO_INCREMENT value in the create command
        $command = preg_replace("/ AUTO_INCREMENT=(\d+)/", "", $raw_command);
        $data_query = str_replace('ZZZZ', $table, $_qry2);
        
        if ($table == "users") {
            $data_query .= " WHERE id=1";
        }
        
        try {
            DB::connection('consodb')->statement($command);
            DB::connection('consodb')->statement($data_query);
        } catch (\Exception $e) {
            // FIX: Replaced dd() trap with clean API JSON output back to your Vue app
            return response()->json([
                'result' => false, 
                'msg' => 'Schema replication failed for table "' . $table . '": ' . $e->getMessage()
            ], 422); 
        }
    }

    // Run seeds on the new database
    $exitCode = Artisan::call('db:seed');
    if ($exitCode != 0) {
        return response()->json(['result' => false, 'msg' => 'Database seeding failed with status code: ' . $exitCode]);
    }

    // FIX: Cleaned GRANT privileges mapping to support modern MySQL syntax blocks safely
    try {
        DB::statement("GRANT ALL PRIVILEGES ON `{$conso_db}`.* TO '{$db_admin_user}'@'{$_host}'");
        DB::statement("GRANT SELECT ON `{$conso_db}`.* TO '{$db_conso_user}'@'{$_host}'");
        DB::statement("GRANT UPDATE ON `{$conso_db}`.users TO '{$db_conso_user}'@'{$_host}'");
        DB::statement("FLUSH PRIVILEGES");
    } catch (\Exception $grantError) {
        return response()->json(['result' => false, 'msg' => 'Database user mapping permissions allocation failed: ' . $grantError->getMessage()], 500);
    }

    // FIX: Force state to Active (1) on instantiation records
    $consortiumData = $request->only('ccp_key', 'name', 'email');
    $consortiumData['is_active'] = 1;

    // Create the new consortium records
    $consortium = Consortium::create($consortiumData);

    // Create the Administrator account in the new consortium users table using input values
    DB::table($conso_db . ".users")->insert([
        [
            'id' => 2,
            'name' => 'CC-Plus Administrator',
            'password' => Hash::make($input['admin_pass']),
            'email' => $input['admin_user'],
            'inst_id' => 1,
            'is_active' => 1
        ]
    ]);

    // Set role for 'Administrator'
    DB::table($conso_db . ".role_user")->insert(['role_id' =>  99, 'user_id' => 2]);

    return response()->json([
        'result' => true, 
        'consortium' => $consortium,
        'msg' => 'New consortium : ' . $consortium->name . ' Successfully Created.',
    ]);
}


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JSON
     */
    public function show($id)
    {
        $consortium = Consortium::findOrFail($id); //Find consortium w/ id = $id
        return response()->json(['consortium' => $consortium], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JSON
     */
    public function update(Request $request, $id)
    {
        // Get the consortium record
        $consortium = Consortium::findOrFail($id);

        // Check is_active and set in record if given
        if (!$request->has('is_active')) {
            return response()->json(['result' => false, 'msg' => 'Missing arguments! - no update applied.']);
        }
        $input = $request->all();
        $consortium->is_active = ($input['is_active']) ? 1 : 0;

        // If more than just is_active was requested
        if (count($input) > 1) {
            if (!isset($input['name']) || !isset($input['email'])) {
                return response()->json(['result' => false, 'msg' => 'Missing arguments! - no update applied.']);
            }
            $consortium->name = $request->input('name');
            $consortium->email = $request->input('email');
        }
        // Save the record
        $consortium->save();

        // Return the updated object
        return response()->json(['result' => true, 'consortium' => $consortium,
                                 'msg' => 'Consortium : ' . $consortium->name . ' Successfully Updated.',
                               ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $consortium = Consortium::findOrFail($id);
        try {
            $consortium->delete();
            return response()->json(['result' => true, 'msg' => 'Instance successfully deleted']);
        } catch (\Exception $e) {
            return response()->json(['result' => true, 'msg' => $e->getMessage()]);
        }
    }
}
