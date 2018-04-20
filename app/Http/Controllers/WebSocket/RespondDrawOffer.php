<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 20/04/2018
 * Time: 12:00
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RespondDrawOffer extends WebSocketController
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $decision = $data->decision;
        $userId = Auth::id();

        $result = $gm->respondDrawOffer($gameId, $userId, $decision);

        return response()->json(['result' => $result, 'gameId'=>$gameId], 200);


    }
}