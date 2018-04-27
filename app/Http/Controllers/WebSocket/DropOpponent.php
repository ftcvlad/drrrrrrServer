<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 26/04/2018
 * Time: 20:28
 */

namespace App\Http\Controllers\WebSocket;

use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DropOpponent
{
    public function handleMessage(Request $request, GamesManager $gm){

        $userId = Auth::id();
        $data = $request->get('data');
        $gameId = $data->gameId;



        $result = $gm->dropOpponent($gameId, $userId);

        return response()->json(['result' => $result, 'gameId'=>$gameId], 200);

    }
}