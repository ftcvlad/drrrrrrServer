<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 12:02
 */

namespace App\Http\Controllers\WebSocket;


use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\GameInfo;

class BroadcastPlayerJoined extends WebSocketController
{


    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $gameId = $data->gameId;
        $targetGame = $gm->findGameByGameId($gameId);
        if ($targetGame == null){
            return response()->json(['message' => 'game doesn\'t exist! impossible happened'], 403);
        }
        else{

            $gameInfo = $targetGame->gameInfo;
            return response()->json(['game'=>$targetGame, "gameInfo"=>$gameInfo], 200);
        }
    }



}