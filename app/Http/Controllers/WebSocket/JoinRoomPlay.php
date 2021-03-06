<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 16:29
 */

namespace App\Http\Controllers\WebSocket;

use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JoinRoomPlay
{

    public function handleMessage(Request $request, GamesManager $gm){

        $userId = Auth::id();
        $targetGame = $gm->findGameInWhichUserParticipates($userId);

        if ($targetGame != null){

            $gm->removeDisconnectedStatus($targetGame, $userId);
            $gm->updatePlayerTimeLeft($targetGame);


            return response()->json(['room' => $targetGame->gameInfo->gameId, 'currentGame'=>$targetGame], 200);
        }
        else{
            return response()->json(['message' => 'player has to join game to join socket room!'], 403);//!!! properly handle this?
        }

    }


}