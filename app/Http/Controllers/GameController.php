<?php

namespace App\Http\Controllers;

use App\Util\GamesManager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateGameRequest;
use App\User;
use App\Game;


class GameController extends Controller
{
    public function createGame(CreateGameRequest $request){

        $games = Cache::get('games', []);


        //!!! make sure user is not in some game already
        $currentGame = GamesManager::findGameInWhichUserParticipates(Auth::id());
        if ($currentGame != null){
            return response(json_encode(['msg' => "cannot join > 1 game"]), 403);//already in some game
        }


        $uuid = $this->generateUuid($games);
        $playerId = Auth::user()->id;
        $boardState = $this->createStartGrid();


        $createdGame = new Game($uuid, $playerId, $boardState);

        $games[] = $createdGame;
        Cache::forever('games', $games);


        return response(json_encode(get_object_vars($createdGame)), 201);
    }



    function createStartGrid (){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }

    function generateUuid(&$games){

        $success = false;
        while(!$success){
            $uuid = uniqid();
            $success = true;
            foreach ($games as $game){//check that it's ineed unique
                if ($game->gameId == $uuid){
                    $success = false;
                    break;
                }
            }
        }
       return $uuid;

    }


    public function removeAllGames(Request $request){
        Cache::forget('games');
        return response('', 204);

    }
}
