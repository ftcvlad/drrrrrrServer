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


class reconnectTest extends TestCase {



    public function testDisconnectStatusRemoved(){


        $game = $this->generateGameObjectWithOnePlayer(PlayerStatuses::disconnected);
        $game->gameInfo->players[] = new Player("b", 2, PlayerStatuses::dropper, 100, 0);
        $gm = \Mockery::mock(GamesManager::class)->makePartial();

        Cache::shouldReceive("forever")->once();
        $gm->removeDisconnectedStatus($game, 1);


        $this->assertEquals(2, count($game->gameInfo->players));
        $this->assertEquals(PlayerStatuses::playing, $game->gameInfo->players[0]->currentStatus);
        $this->assertEquals(PlayerStatuses::playing, $game->gameInfo->players[1]->currentStatus);

    }



    public function generateGameObjectWithOnePlayer($status){


        $player =new Player("a", 1, $status, 100, 1);
        $options = new \stdClass();
        $options->timeReserve = 15;

        $game = new Game("abc",$player , $options);

        return $game;
    }
}