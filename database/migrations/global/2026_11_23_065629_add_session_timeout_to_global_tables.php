<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::connection('globaldb')->table('global_settings')->where('name', 'session_timeout')->exists()) {
            DB::connection('globaldb')->table('global_settings')->insert([
                'name'  => 'session_timeout',
                'type'  => 'config',
                'value' => '120',
            ]);
        }

        if (!Schema::connection('globaldb')->hasColumn('consortia', 'session_timeout')) {
            Schema::connection('globaldb')->table('consortia', function (Blueprint $table) {
                $table->unsignedInteger('session_timeout')->nullable()->after('theme');
            });
        }
    }

    public function down(): void
    {
        DB::connection('globaldb')->table('global_settings')->where('name', 'session_timeout')->delete();

        if (Schema::connection('globaldb')->hasColumn('consortia', 'session_timeout')) {
            Schema::connection('globaldb')->table('consortia', function (Blueprint $table) {
                $table->dropColumn('session_timeout');
            });
        }
    }
};
