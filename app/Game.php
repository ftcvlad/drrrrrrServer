<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 16/02/2018
 * Time: 12:37
 */

namespace App;


class Game
{

    public $gameInfo;
    public $gameState;
    public $chatMessages = []; //this game is more like a game table => also stores chat messages


    function __construct($gameId, $player, $options)
    {
        $this->gameInfo = new GameInfo($gameId, $player, $options);
        $this->gameState = new GameState($options->timeReserve);









    }



}