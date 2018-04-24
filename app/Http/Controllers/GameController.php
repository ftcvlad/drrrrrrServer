<?php

namespace App\Http\Controllers;

use App\Util\GamesManager;

use App\Util\StatsManager;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

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
}
