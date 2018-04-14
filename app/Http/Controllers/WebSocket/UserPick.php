<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 12:44
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPick extends WebSocketController
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $row = $data->moveInfo->r;
        $col = $data->moveInfo->c;
        $gameId = $data->gameId;
        $userId = Auth::id();

        $updatedGame = $gm->userPick($row, $col, $userId, $gameId);
        if ($updatedGame == null){//pick not possible
            return response()->json([], 204);
        }
        else{
            return response()->json(['game'=>$updatedGame, 'playerId'=>$userId], 200);
        }
    }
}