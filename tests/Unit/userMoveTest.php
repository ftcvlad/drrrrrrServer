<?php

namespace Tests\Unit;
use App\PlayerStatuses;
use App\Util\GamesManager;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Player;
use App\Game;


class userMoveTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGameIsGoing()
    {
        $this->expectException(HttpException::class);

        $gm = new GamesManager();
        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = false;

        $gm->userMove(7, 0, 1,  $game );
    }

    public function testUsersTurn()
    {
        $this->expectException(HttpException::class);

        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 1;

        $gm->userMove(7, 0, 1,  $game );
    }

    public function testTargetCellIsEmpty()
    {


        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->possibleGoChoices = [array("row"=>5, "col"=>2)];
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userMove(5, 0, 1,  $game );


        $this->assertNull($result);

    }

    public function testCanDeselectChecker()
    {


        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->possibleGoChoices = [array("row"=>5, "col"=>0)];
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userMove(5, 0, 1,  $game );

        $this->assertEquals(false, $result['boardChanged']);
        $this->assertEquals(true, $result['gameState']->selectChecker);

    }

    public function testCanDoSimpleMove(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [5, 0];
        $game->gameState->possibleGoChoices = [array("row"=>4, "col"=>1), array("row"=>5, "col"=>0)];
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userMove(4, 1, 1,  $game );

        $this->assertEquals(true, $result['boardChanged']);
        $this->assertEquals(1, $result["gameState"]->boardState[4][1]);
        $this->assertEquals(0, $result["gameState"]->boardState[5][0]);

    }


    public function testCanKillSingleChecker(){

        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [5, 0];
        $game->gameState->possibleGoChoices = [array("row"=>3, "col"=>2), array("row"=>5, "col"=>0)];
        $game->gameState->boardState = $this->getSingleBeat();

        $result = $gm->userMove(3, 2, 1,  $game );

        $this->assertEquals(true, $result['boardChanged']);
        $this->assertEquals(0, $result["gameState"]->boardState[4][1]);
        $this->assertEquals(0, $result["gameState"]->boardState[5][0]);
        $this->assertEquals(1, $result["gameState"]->boardState[3][2]);
        $this->assertEquals(0, count($result["gameState"]->possibleGoChoices));
        $this->assertEquals(true, $result["gameState"]->selectChecker);
        $this->assertEquals(1, $result["gameState"]->currentPlayer);
    }

    public function testCanContinueMoveAfterFirstKill(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [5, 0];
        $game->gameState->possibleGoChoices = [array("row"=>3, "col"=>2), array("row"=>5, "col"=>0)];
        $game->gameState->boardState = $this->getDoubleBeat();

        $result = $gm->userMove(3, 2, 1,  $game );

        $this->assertEquals(true, $result['boardChanged']);
        $this->assertEquals(2, count($result["gameState"]->possibleGoChoices));
        $this->assertEquals(false, $result["gameState"]->selectChecker);
        $this->assertEquals(0, $result["gameState"]->currentPlayer);

        $this->assertEquals(1, $result["gameState"]->possibleGoChoices[0]['row']);
        $this->assertEquals(0, $result["gameState"]->possibleGoChoices[0]['col']);

        $this->assertEquals(1, $result["gameState"]->possibleGoChoices[1]['row']);
        $this->assertEquals(4, $result["gameState"]->possibleGoChoices[1]['col']);

    }


    //checker beats multiple pieces,
    //becomes a king and continues turn as a king,
    //skips checker due to turkish turn rule
    //lands on one of possible end positions
    public function testComplexMove(){
        $gm = new GamesManager();

        $game = $this->generateGameObjectWithTwoPlayers();


        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [4, 3];
        $game->gameState->possibleGoChoices = [array("row"=>2, "col"=>5), array("row"=>4, "col"=>3)];
        $game->gameState->boardState = $this->getMultipleBeat();

        $gm->userMove(2, 5, 1,  $game );
        $gm->userMove(0, 3, 1,  $game );
        $gm->userMove(3, 0, 1,  $game );
        $result = $gm->userMove(7, 4, 1,  $game );


        $this->assertEquals(0, $result["gameState"]->boardState[4][3]);//initial
        $this->assertEquals(0, $result["gameState"]->boardState[3][4]);//beaten
        $this->assertEquals(-1, $result["gameState"]->boardState[1][6]);//still there due to turkish turn rule
        $this->assertEquals(0, $result["gameState"]->boardState[1][4]);//beaten
        $this->assertEquals(0, $result["gameState"]->boardState[2][1]);//beaten
        $this->assertEquals(0, $result["gameState"]->boardState[4][1]);//beaten
        $this->assertEquals(2, $result["gameState"]->boardState[7][4]);//end position of the hero checker

    }


    public function testGameFinishedWhenBeatsAll(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [4, 3];
        $game->gameState->possibleGoChoices = [array("row"=>2, "col"=>5), array("row"=>4, "col"=>3)];
        $game->gameState->boardState = $this->getLastBeat();

        $result = $gm->userMove(2, 5, 1,  $game );
        $this->assertEquals(true, $result["opponentLost"]);

    }

    public function testGameFinishedWhenBeatsAndOpponentHasNoMoves(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 0;
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->pickedChecker = [4, 3];
        $game->gameState->possibleGoChoices = [array("row"=>2, "col"=>5), array("row"=>4, "col"=>3)];
        $game->gameState->boardState = $this->getBeatAndNoMoves();

        $result = $gm->userMove(2, 5, 1,  $game );
        $this->assertEquals(true, $result["opponentLost"]);

    }



    public function generateGameObject(){


        $player =new Player("a", 1, PlayerStatuses::playing, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }

    public function generateGameObjectWithTwoPlayers(){
        $game = $this->generateGameObject();
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::playing, 100, 0);
        return $game;
    }

    private function getInitial(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }


    private function getSingleBeat(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, -1, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }

    private function getDoubleBeat(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
                [0, 0, -1, 0, 0, 0, -1, 0],
                [0, -1, 0, -1, 0, -1, 0, -1],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, -1, 0, 0, 0, 0, 0, 0],
                [1, 0, 1, 0, 1, 0, 1, 0],
                [0, 1, 0, 1, 0, 1, 0, 1],
                [1, 0, 1, 0, 1, 0, 1, 0]];
    }

    private function getMultipleBeat(){
        return [[0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, -1, 0, -1, 0],
                [0, -1, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, -1, 0, 0, 0],
                [0, -1, 0, 1, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0]];
        }


    private function getLastBeat(){
        return [[0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, -1, 0, 0, 0],
                [0, 0, 0, 1, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0]];
    }


    private function getBeatAndNoMoves(){
        return [[0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, -1, 0, 0, 0],
            [0, 0, 0, 1, 0, 0, 0, 0],
            [-1, 0, 0, 0, 0, 0, 0, 0],
            [0, 1, 0, 0, 0, 0, 0, -1],
            [0, 0, 1, 0, 0, 0, 1, 0]];
    }


}
