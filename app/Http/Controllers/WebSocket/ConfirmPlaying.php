<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 21:22
 */

namespace App\Http\Controllers\WebSocket;



use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConfirmPlaying
{
    public function handleMessage(Request $request, GamesManager $gm)
    {

        $data = $request->get('data');

        $gameId = $data->gameId;
        $playerId = Auth::id();
        $result = $gm->confirmPlaying($gameId, $playerId);


        return response()->json(['result' => $result, 'gameId'=>$gameId], 200);


    }

}