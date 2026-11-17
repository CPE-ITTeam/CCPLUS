<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consortia', function (Blueprint $table) {
            $table->string('theme', 32)->nullable()->default('light')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('consortia', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
