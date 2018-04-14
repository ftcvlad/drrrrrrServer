<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 12:51
 */

namespace App\Http\Controllers\WebSocket;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;

class UserMove extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm){
        $data = $request->get('data');

        $row = $data->moveInfo->r;
        $col = $data->moveInfo->c;
        $gameId = $data->gameId;
        $userId = Auth::id();

        $result = $gm->userMoveWrapper($row, $col, $userId, $gameId);

        if ($result == null){
            return response()->json([], 204);
        }
        else{
            return response()->json(['game'=>$result["game"], 'playerId'=>$userId, 'boardChanged'=>$result["boardChanged"]], 200);
        }
    }




}