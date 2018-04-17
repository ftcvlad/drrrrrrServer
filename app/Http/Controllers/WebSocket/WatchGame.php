<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 17/04/2018
 * Time: 22:37
 */

namespace App\Http\Controllers\WebSocket;

use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WatchGame extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm)
    {

        $data = $request->get('data');

        $gameId = $data->gameId;
        $playerId = Auth::id();

        $currentGame = $gm->watchGame($gameId, $playerId);


        return response()->json(['currentGame'=>$currentGame], 200);


    }


}