<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGameResultTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('game_result', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('match_result');
            $table->boolean('plays_white');
            $table->unsignedInteger('o_rating_after');
            $table->unsignedInteger('o_rating_before');
            $table->unsignedInteger('u_rating_after');
            $table->unsignedInteger('u_rating_before');
            $table->unsignedInteger('opponent_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('opponent_id')->references('id')->on('users')->onDelete('cascade');



        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('game_result');
    }
}
