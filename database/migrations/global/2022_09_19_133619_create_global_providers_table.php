<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_providers', function (Blueprint $table) {
          $table->Increments('id');
          $table->string('registry_id')->nullable();
          $table->string('name');
          $table->string('abbrev')->nullable();
          $table->index('name');
          $table->string('content_provider')->nullable();
          $table->boolean('is_active')->default(1);
          $table->boolean('refreshable')->default(1);
          // expected result values: 'success', 'failed', 'new', or null
          $table->string('refresh_result',7)->nullable();
          $table->json('master_reports')->default("[1]");
          $table->unsignedInteger('day_of_month')->default(15);
          $table->string('platform_parm')->nullable();
          $table->string('selected_release',6)->nullable();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('global_providers');
    }
}
