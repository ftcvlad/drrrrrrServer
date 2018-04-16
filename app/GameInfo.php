<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 16:10
 */

namespace App;


class GameInfo
{
    public $gameId;
    public $players = [];
    public $watchers = [];


    function __construct($gameId, $player)
    {
        $this->gameId = $gameId;
        $this->players[] = $player;
        $this->watchers = [];
    }
}