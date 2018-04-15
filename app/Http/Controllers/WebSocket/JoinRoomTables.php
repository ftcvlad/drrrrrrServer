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
use App\GameInfo;

class JoinRoomTables extends WebSocketController
{

    private function createGameInfoList(&$games){
        $gameInfoArr = [];
        foreach ($games as $game){
            $gameInfoArr[] = new GameInfo($game->gameId, $game->players, $game->watchers);
        }
        return $gameInfoArr;
    }


    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $category = $data->roomCategory;
        if ($category == RoomCategories::TABLE_64_ROOM || $category == RoomCategories::TABLE_100_ROOM){
            $games = $gm->findGamesByCategory($category);

            $gameList = $this->createGameInfoList($games);
            return response()->json(['room' => $category, 'gameList'=>$gameList], 200);
        }

    }
}