<?php

namespace Tests\Unit;

use App\PlayerStatuses;
use App\Util\GamesManager;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Player;
use App\Game;

class userPickTest extends TestCase
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

        $gm->userPick(7, 0, 1, "a", $game );
    }


    public function testUsersTurn()
    {
        $this->expectException(HttpException::class);

        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->currentPlayer = 1;

        $gm->userPick(7, 0, 1, "a", $game );
    }

    public function testIsOwnChecker(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userPick(2, 1, 1, "a", $game );
        $this->assertNull($result);
    }

    public function testLocationHasChecker(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userPick(4, 1, 1, "a", $game );
        $this->assertNull($result);
    }


    public function testCheckerHasMoveOptions(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userPick(7, 0, 1, "a", $game );
        $this->assertNull($result);
    }



    public function testCannotSelectCheckerIfOtherCheckerHasBeatOptions(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getCheckerHasBeatOptions();

        $result = $gm->userPick(5, 0, 1, "a", $game );
        $this->assertNull($result);
    }

    public function testCannotSelectCheckerIfOtherKingHasBeatOptions(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getKingHasBeatOptions();

        $result = $gm->userPick(5, 0, 1, "a", $game );
        $this->assertNull($result);
    }


    public function testProducedGoOptionsAreCorrect1(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getInitial();

        $result = $gm->userPick(5, 0, 1, "a", $game );

        $this->assertNotNull($result);
        $this->assertEquals(count($result->possibleGoChoices), 2);

        $this->assertEquals($result->possibleGoChoices[0]['row'], 4);
        $this->assertEquals($result->possibleGoChoices[0]['col'], 1);

        $this->assertEquals($result->possibleGoChoices[1]['row'], 5);
        $this->assertEquals($result->possibleGoChoices[1]['col'], 0);

    }

    public function testProducedGoOptionsAreCorrect2(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getCheckerHasBeatBackOption();

        $result = $gm->userPick(4, 5, 1, "a", $game );


        $this->assertNotNull($result);
        $this->assertEquals(count($result->possibleGoChoices), 3);

        $this->assertEquals($result->possibleGoChoices[0]['row'], 6);
        $this->assertEquals($result->possibleGoChoices[0]['col'], 3);

        $this->assertEquals($result->possibleGoChoices[1]['row'], 6);
        $this->assertEquals($result->possibleGoChoices[1]['col'], 7);

        $this->assertEquals($result->possibleGoChoices[2]['row'], 4);
        $this->assertEquals($result->possibleGoChoices[2]['col'], 5);

    }

    public function testPlayerCanChooseTurnHavingMultipleBeatOptions(){
        $gm = new GamesManager();

        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getMultipleBeatOptions();


        $result1 = $gm->userPick(5, 0, 1, "a", $game );
        $this->assertEquals(2, count($result1->possibleGoChoices));

        //reset
        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getMultipleBeatOptions();

        $result2 = $gm->userPick(5, 2, 1, "a", $game );
        $this->assertEquals(3, count($result2->possibleGoChoices));

        //reset
        $game = $this->generateGameObject();
        $game->gameState->isGameGoing = true;
        $game->gameState->currentPlayer = 0;
        $game->gameState->boardState = $this->getMultipleBeatOptions();


        $result3 = $gm->userPick(5, 4, 1, "a", $game );
        $this->assertEquals(2, count($result3->possibleGoChoices));

        $this->assertEquals(3, $result2->possibleGoChoices[0]['row']);
        $this->assertEquals(0, $result2->possibleGoChoices[0]['col']);

        $this->assertEquals(1, $result2->possibleGoChoices[1]['row']);
        $this->assertEquals(6, $result2->possibleGoChoices[1]['col']);

        $this->assertEquals( 5, $result2->possibleGoChoices[2]['row']);
        $this->assertEquals(2, $result2->possibleGoChoices[2]['col']);


    }





    public function generateGameObject(){


        $player =new Player("a", 1, PlayerStatuses::playing, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }


    private function getMultipleBeatOptions(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
                [-1, 0, -1, 0, -1, 0, 0, 0],
                [0, -1, 0, -1, 0, -1, 0, -1],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, -1, 0, 0, 0, -1, 0, 0],
                [1, 0, 2, 0, 1, 0, 1, 0],
                [0, 1, 0, 1, 0, 1, 0, 1],
                [1, 0, 1, 0, 1, 0, 1, 0]];
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



    private function getCheckerHasBeatOptions(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
                [-1, 0, -1, 0, -1, 0, -1, 0],
                [0, -1, 0, -1, 0, -1, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, -1, 0, 0],
                [1, 0, 1, 0, 1, 0, 1, 0],
                [0, 1, 0, 1, 0, 1, 0, 1],
                [1, 0, 1, 0, 1, 0, 1, 0]];
    }


    private function getKingHasBeatOptions(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
                [-1, 0, -1, 0, -1, 0, 0, 0],
                [0, -1, 0, -1, 0, -1, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [0, 0, 0, 0, 0, 0, 0, 0],
                [1, 0, 2, 0, 1, 0, 1, 0],
                [0, 1, 0, 1, 0, 1, 0, 1],
                [1, 0, 1, 0, 1, 0, 1, 0]];
    }

    private function getCheckerHasBeatBackOption(){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 1, 0, 0],
            [1, 0, 0, 0, -1, 0, -1, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 0, 0, 1, 0, 1, 0]];
    }


}
