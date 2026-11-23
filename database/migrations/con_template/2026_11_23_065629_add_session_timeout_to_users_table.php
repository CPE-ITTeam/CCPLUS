<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('consodb')->table('users', function (Blueprint $table) {
            if (!Schema::connection('consodb')->hasColumn('users', 'theme')) {
                $table->string('theme', 32)->nullable();
            }
            if (!Schema::connection('consodb')->hasColumn('users', 'session_timeout')) {
                $table->unsignedInteger('session_timeout')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('consodb')->table('users', function (Blueprint $table) {
            $cols = array_filter(['theme', 'session_timeout'], fn ($col) => Schema::connection('consodb')->hasColumn('users', $col));
            if (count($cols) > 0) {
                $table->dropColumn($cols);
            }
        });
    }
};
