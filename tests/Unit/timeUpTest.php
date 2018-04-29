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


class timeUpTest extends TestCase {


    public function confirmStatuses($firstStatus, $secondStatus, $firstStatusFinal, $secondStatusFinal ){
        $game = $this->generateGameObjectWithOnePlayer($firstStatus);
        $game->gameInfo->players[] = new Player("b", 2, $secondStatus, 100, 0);
        $game->gameState->isGameGoing = true;
        $game->gameState->timeLeft = [5, -1];


        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        Cache::shouldReceive("forever")->once();
        $gm->shouldReceive("finishGame");
        $gm->shouldReceive("decreaseTimeLeft");
        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->handleTimeIsUp($game->gameInfo->gameId);


        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals($firstStatusFinal, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals($secondStatusFinal, $game->gameInfo->players[1]->currentStatus);
    }

    public function testStateChangesIfPlaying(){
        $this->confirmStatuses(PlayerStatuses::playing,
            PlayerStatuses::playing,
            PlayerStatuses::confirming,
            PlayerStatuses::confirming);
    }


    public function testStateChangesIfAcceptingSuggestingDraw(){
        $this->confirmStatuses(PlayerStatuses::resolvingDrawOffer,
            PlayerStatuses::suggestingDraw,
            PlayerStatuses::confirming,
            PlayerStatuses::confirming);
    }


    public function testTimeUpForDisconnectedOpponent(){
        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::dropper);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::disconnected, 100, 0);
        $game->gameState->isGameGoing = true;
        $game->gameState->timeLeft = [5, -1];


        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        Cache::shouldReceive("forever")->once();
        $gm->shouldReceive("finishGame");
        $gm->shouldReceive("decreaseTimeLeft");
        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->handleTimeIsUp($game->gameInfo->gameId);


        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::waiting, $game->gameInfo->players[0]->currentStatus);

    }



    public function generateGameObjectWithOnePlayer($status){


        $player =new Player("a", 1, $status, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }
}