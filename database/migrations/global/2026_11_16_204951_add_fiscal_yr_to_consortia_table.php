<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('globaldb')->table('consortia', function (Blueprint $table) {
            $table->string('fiscalYr', 12)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('globaldb')->table('consortia', function (Blueprint $table) {
            $table->dropColumn('fiscalYr');
        });
    }
};
