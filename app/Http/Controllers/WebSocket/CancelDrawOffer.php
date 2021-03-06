<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 20/04/2018
 * Time: 13:57
 */

namespace App\Http\Controllers\WebSocket;


use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class CancelDrawOffer
{
    public function handleMessage(Request $request, GamesManager $gm){

        $data = $request->get('data');
        $gameId = $data->gameId;
        $userId = Auth::id();

        $gameInfo = $gm->cancelDrawOffer($gameId, $userId);

        return response()->json(['gameInfo' => $gameInfo, 'gameId'=>$gameId], 200);


    }
}