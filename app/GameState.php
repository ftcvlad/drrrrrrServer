<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 15/04/2018
 * Time: 18:56
 */

namespace App;


class GameState
{

    public $boardState;
    public $isGameGoing = false;
    public $selectChecker = true;
    public $currentPlayer = 0;
    public $possibleGoChoices = [];
    public $pickedChecker = [];//[row, col]
    public $moves = [];
    public $timeLeft;//[1st, 2nd]
    public $gameStartTime;

    function __construct($timeReserve)
    {
        $this->timeLeft = [$timeReserve, $timeReserve];
        $this->boardState = $this->createStartTest2Grid();
    }


    private function createStartGrid()
    {
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }


    private function createStartTestGrid()
    {



        return [[0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 2],
                [2, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, -1, 0, 0, 0, 0],
                [-1, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [-2, 0, 0, 0, 0, 0, 0, 0]];
    }

    private function createStartTest2Grid()
    {
        return [[0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, -1, 0, -1, 0],
            [0, -1, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, -1, 0, 0, 0],
            [0, -1, 0, 1, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0]];

    }

}