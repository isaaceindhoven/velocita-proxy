<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InitialDatabase extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('repositories', function(Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 200);
            $table->text('notify');
            $table->text('notify_batch');
            $table->text('providers_pattern');
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('provider_includes', function(Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('repository_id')->unsigned();
            $table->text('pattern');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->unique(['repository_id', 'pattern']);

            $table->foreign('repository_id')->references('id')->on('repositories');
        });

        Schema::create('providers', function(Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('repository_id')->unsigned();
            $table->bigInteger('provider_include_id')->unsigned()->nullable();
            $table->text('namespace');
            $table->text('package');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->unique(['repository_id', 'namespace', 'package']);

            $table->foreign('repository_id')->references('id')->on('repositories');
            $table->foreign('provider_include_id')->references('id')->on('provider_includes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('providers');
        Schema::dropIfExists('provider_includes');
        Schema::dropIfExists('repositories');
    }
}
