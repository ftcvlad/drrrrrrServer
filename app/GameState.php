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

    function __construct()
    {
        $this->boardState = $this->createStartGrid();
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

}