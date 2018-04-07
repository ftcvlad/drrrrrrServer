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

    public $gameId;
    public $players = [];
    public $watchers = [];
    public $moves = [];
    public $boardState;
    public $isGameGoing;


    function __construct($uuid, $playerId, $boardState)
    {
        $this->gameId = $uuid;
        $this->players[] = $playerId;
        $this->boardState = $boardState;
        $this->isGameGoing = false;

    }



}