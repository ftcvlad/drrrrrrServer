<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 22/04/2018
 * Time: 19:52
 */

namespace App\Http\Controllers\WebSocket;

use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\WebSocketController;

class TimeIsUp extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm){
        $data = $request->get('data');

        $gameId = $data->gameId;

        $result = $gm->handleTimeIsUp($gameId);

        if ($result == null){
            return response()->json([], 409);
        }
        else{
            return response()->json(['result' => $result, 'gameId'=>$gameId], 200);
        }

    }


}