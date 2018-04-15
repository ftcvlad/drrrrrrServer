<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 11:58
 */

namespace App\Http\Controllers\WebSocket;
use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\GameInfo;

class BroadcastGameCreated extends WebSocketController
{

    public function handleMessage(Request $request, GamesManager $gm){
        $myId = Auth::id();
        $targetGame = $gm->findGameInWhichUserParticipates($myId);

        if ($targetGame != null){

            $gameInfo = new GameInfo($targetGame->gameId, $targetGame->players, $targetGame->watchers);
            return response()->json(['gameInfo'=>$gameInfo], 200);
        }
        else{
            return response()->json(['message' => 'game wasnt created! impossible happened'], 403);
        }

    }
}