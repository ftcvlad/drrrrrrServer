<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 14/04/2018
 * Time: 11:17
 */

namespace App\Http\Controllers\WebSocket;


use App\Util\GamesManager;
use Illuminate\Http\Request;
use App\Util\RoomCategories;
use Illuminate\Support\Facades\Auth;
use App\GameInfo;

class JoinRoomTables
{

    private function createGameInfoList(&$games){
        $gameInfoList = [];
        foreach ($games as $game){
            $gameInfoList[] = $game->gameInfo;
        }
        return $gameInfoList;
    }


    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');

        $category = $data->roomCategory;
        if ($category == RoomCategories::TABLE_64_ROOM || $category == RoomCategories::TABLE_100_ROOM){
            $games = $gm->findGamesByCategory($category);

            $gameInfoList = $this->createGameInfoList($games);
            return response()->json(['room' => $category, 'gameList'=>$gameInfoList], 200);
        }

    }
}