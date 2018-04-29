<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 29/04/2018
 * Time: 10:53
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

class exitTest extends TestCase
{

    public function testGameRemovedIfWaiting(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::waiting);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->shouldReceive("removeGameById")->with($game->gameInfo->gameId);
        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->exitGame("abc", 1);

    }


    public function confirmStatuses($firstStatus, $secondStatus, $secondStatusFinal){
        $game = $this->generateGameObjectWithOnePlayer($firstStatus);
        $game->gameInfo->players[] = new Player("b", 2, $secondStatus, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->exitGame("abc", 1);

        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals(2, $game->gameInfo->players[0]->id);
        $this->assertEquals($secondStatusFinal, $game->gameInfo->players[0]->currentStatus);
    }


    public function testStateChangesIfReadyAndConfirming(){
        $this->confirmStatuses(PlayerStatuses::ready, PlayerStatuses::confirming, PlayerStatuses::waiting);
    }


    public function testStateChangesIfConfirmingAndReady(){
        $this->confirmStatuses(PlayerStatuses::confirming, PlayerStatuses::ready, PlayerStatuses::waiting);
    }

    public function testStateChangesIfSuggestingDraw(){
        $this->confirmStatuses(PlayerStatuses::suggestingDraw, PlayerStatuses::resolvingDrawOffer, PlayerStatuses::waiting);
    }


    public function testStateChangesIfAcceptingDraw(){
        $this->confirmStatuses(PlayerStatuses::resolvingDrawOffer, PlayerStatuses::suggestingDraw, PlayerStatuses::waiting);
    }

    public function testStateChangesIfPlaying(){
        $this->confirmStatuses(PlayerStatuses::playing, PlayerStatuses::playing, PlayerStatuses::waiting);
    }

    public function testGameRemovedIfDropperExit()
    {

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::dropper);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::disconnected, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();


        $gm->shouldReceive("removeGameById")->with($game->gameInfo->gameId);
        $gm->shouldReceive("getGame")->with($game->gameInfo->gameId)->andReturn($game);
        $gm->exitGame("abc", 1);


    }


    public function generateGameObjectWithOnePlayer($status){


        $player =new Player("a", 1, $status, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }


}