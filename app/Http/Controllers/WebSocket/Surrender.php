<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 15:59
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Surrender extends WebSocketController
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $userId = Auth::id();

        $gameResult = $gm->surrender($gameId, $userId);

        return response()->json(['gameResult' => $gameResult, 'gameId'=>$gameId], 200);


    }
}