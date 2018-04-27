<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 12:56
 */

namespace App\Http\Controllers\WebSocket;

use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SendChatMessage
{

    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $messageText = $data->msgObj->msg;
        $gameId = $data->gameId;
        $userId = Auth::id();
        $user = Auth::user();
        $userEmail = $user->email;


        //check if sending user participates in the game
        $game = $gm->findGameInWhichUserParticipates($userId);
        if ($game==null || $game->gameInfo->gameId !== $gameId){
            return response()->json(['message' => 'user must participate in game to send messages'], 403);
        }
        else{
            $msg =  array("msgText"=>$messageText, "sender"=>$userEmail, "senderId"=>$userId);
            $game->chatMessages[] = $msg;

            Cache::forever($gameId, serialize($game));
            return response()->json(['msg' => $msg, "gameId"=>$gameId], 200);
        }
    }
}