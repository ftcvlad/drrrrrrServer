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


    function __construct($gameId, $boardState, $player)
    {
        $this->gameInfo = new GameInfo($gameId, $player);
        $this->gameState = new GameState($boardState);









    }



}