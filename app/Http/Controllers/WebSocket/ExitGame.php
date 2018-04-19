<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 18/04/2018
 * Time: 12:39
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;

use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExitGame extends WebSocketController
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $userId = Auth::id();

        $result = $gm->exitGame($gameId, $userId);

        return response()->json(['result' => $result, 'gameId'=>$gameId], 200);

//        $targetGame = $gm->findGameInWhichUserParticipates(Auth::id());
//        if ($targetGame != null){
//            return response()->json(['room' => $targetGame->gameInfo->gameId, 'currentGame'=>$targetGame], 200);
//        }
//        else{
//            return response()->json(['message' => 'player has to join game to join socket room!'], 403);//!!! properly handle this?
//        }

    }


}