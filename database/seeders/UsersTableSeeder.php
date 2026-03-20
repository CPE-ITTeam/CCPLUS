<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

       // If consodb not set, seed the template database
        $_db = \Config::get('database.connections.consodb.database');
        if ($_db == 'db-name-isbad'  || $_db == '') {
            $_db = \Config::get('database.connections.con_template.database');
        }
        $table = $_db . '.users';

        // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            $password = Hash::make('ChangeMeNow!');     // This matches GlobalSettingSeeder value
            DB::table($table)->insert([
                ['id'=>1, 'name'=>'Server Administrator', 'email'=>'ServerAdmin', 'password'=>$password, 'inst_id'=>1],
            ]);
        }
    }
}
