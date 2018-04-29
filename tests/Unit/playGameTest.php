<?php

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

class playGameTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testCannotPlayIf2PlayersInGame()
    {
//        Cache::shouldReceive('get')
//            ->once()
//            ->with('gameIds', [])
//            ->andReturn(["abc"]);
//
//
//
//        Cache::shouldReceive('get')
//            ->once()
//            ->with('abc', [])
//            ->andReturn(serialize($game));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage("game is full");

        $game = $this->generateGameObjectWithOnePlayer(0);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::playing, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();
        $gm->shouldReceive('ensureUserNotInGame');
        $gm->shouldReceive('getGame', "abc")->andReturn($game);





        $gm->playGame($game, 1);

    }


    public function testJoinsAsTheFirstPlayer(){

        $game = $this->generateGameObjectWithOnePlayer(0);
        $game->gameInfo->players = [];
        $gm = \Mockery::mock(GamesManager::class)->makePartial();
        $gm->shouldReceive('ensureUserNotInGame');
        $gm->shouldReceive('getGame', "abc")->andReturn($game);


        $user = new \stdClass();
        $user->rating=10;
        $user->email = "a@b";

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);
        Cache::shouldReceive('forever');



        $game = $gm->playGame($game, 1);
        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::waiting, $game->gameInfo->players[0]->currentStatus);


    }


    public function testJoinsAsTheSecondPlayer(){

        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::waiting);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();
        $gm->shouldReceive('ensureUserNotInGame');
        $gm->shouldReceive('getGame', "abc")->andReturn($game);


        $user = new \stdClass();
        $user->rating=10;
        $user->email = "a@b";

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);
        Cache::shouldReceive('forever');



        $game = $gm->playGame($game, 1);
        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::confirming, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals(PlayerStatuses::ready, $game->gameInfo->players[1]->currentStatus);


    }












    public function generateGameObjectWithOnePlayer($status){


        $player =new Player("a", 1, $status, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }



}
