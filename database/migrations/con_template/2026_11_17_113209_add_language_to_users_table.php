<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('consodb')->hasColumn('users', 'language')) {
            Schema::connection('consodb')->table('users', function (Blueprint $table) {
                $table->string('language', 10)->nullable()->after('fiscalYr');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('consodb')->hasColumn('users', 'language')) {
            Schema::connection('consodb')->table('users', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};
