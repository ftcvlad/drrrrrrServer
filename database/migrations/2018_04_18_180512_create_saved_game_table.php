<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSavedGameTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('saved_game', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('result_id');
            $table->text('moves');
            $table->timestamps();

            $table->foreign('result_id')->references('id')->on('game_result');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('saved_game');
    }
}
