<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCounterRegistriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('counter_registries', function (Blueprint $table) {
            $table->Increments('id');
            $table->unsignedInteger('global_id')->nullable();
            $table->string('release',6)->nullable();
            $table->json('connectors')->default("[1]");
            $table->string('service_url')->nullable();
            $table->string('notifications_url')->nullable();
            $table->timestamps();

            $table->foreign('global_id')->references('id')->on('global_providers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('counter_registries');
    }
}
