<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 11:17
 */

namespace App\Http\Controllers\WebSocket;

use App\Http\Controllers\WebSocketController;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use App\Util\RoomCategories;
use Illuminate\Support\Facades\Auth;

class JoinRoom extends WebSocketController
{



    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $category = $data->roomCategory;
        if ($category == RoomCategories::TABLE_64_ROOM || $category == RoomCategories::TABLE_100_ROOM){
            $games = $gm->findGamesByCategory($category);

            return response()->json(['room' => $category, 'games'=>$games], 200);
        }
        else if ($category == RoomCategories::GAME_ROOM){

            $targetGame = $gm->findGameInWhichUserParticipates(Auth::id());

            if ($targetGame != null){

                return response()->json(['room' => $targetGame->gameId, 'games'=>[$targetGame]], 200);
            }
            else{
                return response()->json(['message' => 'player has to join game to join socket room!'], 403);//!!! properly handle this?
            }
        }
    }
}