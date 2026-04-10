<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSavedReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('savedreports', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->Increments('id');
            $table->string('title');
            $table->integer('user_id')->unsigned();
            $table->string('date_range', 12)->default('Custom');
            $table->string('ym_from', 7)->nullable();
            $table->string('ym_to', 7)->nullable();
            $table->unsignedInteger('report_id');
            $table->string('fields')->nullable();
            $table->string('filters')->nullable();
            $table->string('format', 7)->default('Compact');
            $table->boolean('exclude_zeros')->default(1);
            $table->boolean('rpt_only')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('savedreports');
    }
}
