<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 23/04/2018
 * Time: 13:35
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use App\Util\StatsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaveGame extends WebSocketController
{
    public function handleMessage(Request $request, StatsManager $sm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $resultId = $data->resultId;
        $userId = Auth::id();
        $description = "";

        $sm->saveGame($gameId, $userId, $resultId, $description);

        return response()->json([], 204);


    }
}