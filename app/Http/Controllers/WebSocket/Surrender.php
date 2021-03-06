<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 15:59
 */

namespace App\Http\Controllers\WebSocket;



use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Surrender
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $userId = Auth::id();

        $result = $gm->surrender($gameId, $userId);

        return response()->json(['result' => $result, 'gameId'=>$gameId], 200);


    }
}