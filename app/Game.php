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
    public $currentPlayer;
    public $selectChecker;
    public $possibleGoChoices;
    public $pickedChecker = [];//row, col
    public $itemsToDelete = [];
    public $lastTurns = [];


    function __construct($uuid, $playerId, $boardState)
    {
        $this->gameId = $uuid;
        $this->players[] = $playerId;
        $this->boardState = $boardState;
        $this->isGameGoing = false;

        $this->selectChecker = true;
        $this->currentPlayer = 0;
        $this->possibleGoChoices = [];


    }



}