<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 16:29
 */

namespace App\Http\Controllers\WebSocket;

use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JoinRoomPlay extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm){

        $targetGame = $gm->findGameInWhichUserParticipates(Auth::id());
        if ($targetGame != null){
            return response()->json(['room' => $targetGame->gameId, 'currentGame'=>$targetGame], 200);
        }
        else{
            return response()->json(['message' => 'player has to join game to join socket room!'], 403);//!!! properly handle this?
        }

    }


}