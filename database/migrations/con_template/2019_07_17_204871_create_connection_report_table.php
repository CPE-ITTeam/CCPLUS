<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateConnectionReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connection_report', function (Blueprint $table) {
            $global_db = DB::connection('globaldb')->getDatabaseName();

            $table->increments('id');
            $table->integer('connection_id')->unsigned();
            $table->integer('report_id')->unsigned();
            $table->timestamps();
            $table->foreign('connection_id')->references('id')->on('connections')->onDelete('cascade');
            $table->foreign('report_id')->references('id')->on($global_db . '.reports');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('connection_report');
    }
}
