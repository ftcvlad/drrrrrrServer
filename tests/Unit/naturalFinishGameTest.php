<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 29/04/2018
 * Time: 11:56
 */

namespace Tests\Unit;


use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Util\GamesManager;
use App\Player;
use App\Game;
use App\PlayerStatuses;


class naturalFinishGameTest extends TestCase {


    public function generateLastMoveGameState($firstStatus, $secondStatus){
        $game = $this->generateGameObjectWithOnePlayer($firstStatus);
        $game->gameInfo->players[] = new Player("b", 2, $secondStatus, 100, 0);
        $game->gameState->isGameGoing = true;
        $game->gameState->selectChecker = false;
        $game->gameState->boardState = $this->getLastBeat();
        $game->gameState->currentPlayer = 0;
        $game->gameState->pickedChecker = [4, 3];
        $game->gameState->possibleGoChoices = [array("row"=>2, "col"=>5), array("row"=>4, "col"=>3)];
        $game->gameState->boardState = $this->getLastBeat();
        return $game;
    }

    public function confirmStatuses($firstStatus, $secondStatus, $firstStatusFinal, $secondStatusFinal ){

        $game = $this->generateLastMoveGameState($firstStatus, $secondStatus);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->shouldReceive("finishGame");
        Cache::shouldReceive("forever")->once();


        $gm->userMoveWrapper(2, 5, 1, $game->gameInfo->gameId);
        //$gm->shouldReceive("decreaseTimeLeft");




        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals($firstStatusFinal, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals($secondStatusFinal, $game->gameInfo->players[1]->currentStatus);
    }


    public function testPlayingStatusUpdated(){
        $this->confirmStatuses(PlayerStatuses::playing,
            PlayerStatuses::playing,
            PlayerStatuses::confirming,
            PlayerStatuses::confirming);
    }

    public function testResolvingSuggestingStatusUpdated(){
        $this->confirmStatuses(PlayerStatuses::resolvingDrawOffer,
            PlayerStatuses::suggestingDraw,
            PlayerStatuses::confirming,
            PlayerStatuses::confirming);
    }

    public function testSuggestingResolvingStatusUpdated(){
        $this->confirmStatuses(PlayerStatuses::suggestingDraw,
            PlayerStatuses::resolvingDrawOffer,
            PlayerStatuses::confirming,
            PlayerStatuses::confirming);
    }

    public function testGameFinishForDisconnectedOpponent(){
        $game = $this->generateLastMoveGameState(PlayerStatuses::dropper, PlayerStatuses::disconnected);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->shouldReceive("finishGame");
        Cache::shouldReceive("forever")->once();


        $gm->userMoveWrapper(2, 5, 1, $game->gameInfo->gameId);




        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::waiting, $game->gameInfo->players[0]->currentStatus);


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



    public function generateGameObjectWithOnePlayer($status){


        $player =new Player("a", 1, $status, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }
}