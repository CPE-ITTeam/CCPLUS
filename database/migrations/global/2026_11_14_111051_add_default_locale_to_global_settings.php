<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $exists = DB::connection('globaldb')->table('global_settings')
            ->where('name', 'default_locale')
            ->exists();

        if (!$exists) {
            DB::connection('globaldb')->table('global_settings')->insert([
                'name'  => 'default_locale',
                'type'  => 'config',
                'value' => 'en',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('globaldb')->table('global_settings')
            ->where('name', 'default_locale')
            ->delete();
    }
};
