<?php

namespace App\Http\Controllers;

use App\Util\GamesManager;

use App\Util\StatsManager;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\SaveGameRequest;

class GameController extends Controller
{






    public function removeAllGames(Request $request, GamesManager $gm){

        $gm->removeAllGames();
        return response('', 204);

    }


    public function getSavedGames(Request $request, StatsManager $sm, $userId){
        $games = $sm->getSavedGames($userId);

        Log::info(serialize($games));

        return response($games, 200);

    }


    public function saveGame(SaveGameRequest $request, StatsManager $sm){


        $description =  $request->input('description');
        if (!$description){ $description = "";}
        $userId = Auth::id();
        $gameId =  $request->input('gameId');
        $resultId =  $request->input('resId');

        $sm->saveGame($gameId, $userId, $resultId, $description);

        return response()->json([], 204);




    }
}
