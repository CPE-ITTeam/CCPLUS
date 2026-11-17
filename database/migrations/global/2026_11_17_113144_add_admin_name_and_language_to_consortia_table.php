<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consortia', function (Blueprint $table) {
            $table->string('admin_name', 191)->nullable()->after('name');
            $table->string('language', 10)->nullable()->after('fiscalYr');
        });
    }

    public function down(): void
    {
        Schema::table('consortia', function (Blueprint $table) {
            $table->dropColumn(['admin_name', 'language']);
        });
    }
};
