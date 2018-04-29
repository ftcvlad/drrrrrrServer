<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 29/04/2018
 * Time: 10:13
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


class disconnectTest extends TestCase
{


    public function testStatusChangeIfBothPlaying(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::playing);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::playing, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->disconnect($game, 1);

        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::disconnected, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals(PlayerStatuses::dropper, $game->gameInfo->players[1]->currentStatus);

    }

    public function testStatusChangeIfSuggestingDraw(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::suggestingDraw);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::resolvingDrawOffer, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->disconnect($game, 1);

        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::disconnected, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals(PlayerStatuses::dropper, $game->gameInfo->players[1]->currentStatus);

    }

    public function testStatusChangeIfAcceptingDraw(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::resolvingDrawOffer);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::suggestingDraw, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->disconnect($game, 1);

        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::disconnected, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals(PlayerStatuses::dropper, $game->gameInfo->players[1]->currentStatus);

    }


    public function testGameRemovedIfSecondDropped(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::dropper);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::disconnected, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->shouldReceive("removeGameById")->with($game->gameInfo->gameId);
        $gm->disconnect($game, 1);

    }


    public function testGameRemovedIfWaiting(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::waiting);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->shouldReceive("removeGameById")->with($game->gameInfo->gameId);
        $gm->disconnect($game, 1);

    }


    public function testStateChangesIfReadyAndConfirming(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::ready);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::confirming, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->disconnect($game, 1);

        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::waiting, $game->gameInfo->players[0]->currentStatus);
    }

    public function testStateChangesIfConfirmingAndReady(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::confirming);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::ready, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->disconnect($game, 1);

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