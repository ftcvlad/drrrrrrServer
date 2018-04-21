<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 17/04/2018
 * Time: 19:08
 */

namespace App\Http\Controllers\WebSocket;

use App\Util\GamesManager;
use App\Http\Controllers\WebSocketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreateGame extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm)
    {

        $data = $request->get('data');

        $createdGame = $gm->createGame(Auth::id(), $data->options);
        return response()->json(['currentGame'=>$createdGame], 200);
    }
}