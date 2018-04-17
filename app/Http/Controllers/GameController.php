<?php

namespace App\Http\Controllers;

use App\Util\GamesManager;

use Illuminate\Http\Request;



class GameController extends Controller
{






    public function removeAllGames(Request $request, GamesManager $gm){

        $gm->removeAllGames();
        return response('', 204);

    }
}
