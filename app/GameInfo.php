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
    public $timeReserve = [];


    function __construct($gameId, $player, $options)
    {
        $this->gameId = $gameId;
        $this->players[] = $player;
        $this->watchers = [];
        $this->timeReserve = $options->timeReserve;
    }
}