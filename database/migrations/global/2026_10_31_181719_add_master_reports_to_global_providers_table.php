<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Force migration to point to your specific connection 'globaldb'
        Schema::connection('globaldb')->table('global_providers', function (Blueprint $table) {
            $table->text('master_reports')->nullable()->after('abbrev');
        });
    }

    public function down(): void
    {
        Schema::connection('globaldb')->table('global_providers', function (Blueprint $table) {
            $table->dropColumn('master_reports');
        });
    }
};
