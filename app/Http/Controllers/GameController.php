<?php

namespace App\Http\Controllers;

use App\Util\GamesManager;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateGameRequest;
use App\Http\Requests\PlayGameRequest;
use App\User;
use App\Game;


class GameController extends Controller
{
    public function playGame(PlayGameRequest $request, GamesManager $gm){
        $gameId = $request->input('gameId');
        $playerId = Auth::id();

        $currentGame = $gm->playGame($gameId, $playerId);



        return response(json_encode(get_object_vars($currentGame)), 200);

    }

    public function createGame(CreateGameRequest $request, GamesManager $gm){

        $createdGame = $gm->createGame(Auth::id());
        return response(json_encode(get_object_vars($createdGame)), 201);
    }








    public function removeAllGames(Request $request, GamesManager $gm){

        $gm->removeAllGames();
        return response('', 204);

    }
}
