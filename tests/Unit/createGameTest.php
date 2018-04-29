<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 29/04/2018
 * Time: 11:40
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

class createGameTest extends TestCase
{

    public function testCreatesGameWithPlayer(){

        $gm = \Mockery::mock(GamesManager::class)->makePartial();
        $gm->shouldReceive('ensureUserNotInGame');
        $gm->shouldReceive('generateUuid')->andReturn("12345");
        Cache::shouldReceive('get')
            ->once()
            ->with('gameIds', [])
            ->andReturn(["abc"]);

        $user = new \stdClass();
        $user->rating=10;
        $user->email = "a@b";

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        Cache::shouldReceive("forever")->with("gameIds", []);
        Cache::shouldReceive("forever");

        $options = new \stdClass();
        $options->timeReserve = 15;
        $game = $gm->createGame(1, $options);


        $this->assertEquals(1, count($game->gameInfo->players));
        $this->assertEquals("12345", $game->gameInfo->gameId);
        $this->assertEquals(PlayerStatuses::waiting, $game->gameInfo->players[0]->currentStatus);

    }

}