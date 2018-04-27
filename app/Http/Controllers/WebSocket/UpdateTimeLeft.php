<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 25/04/2018
 * Time: 23:15
 */

namespace App\Http\Controllers\WebSocket;


use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateTimeLeft
{

    public function handleMessage(Request $request, GamesManager $gm){

        $targetGame = $gm->findGameInWhichUserParticipates(Auth::id());

        if ($targetGame != null){

            $gm->updatePlayerTimeLeft($targetGame);

            return response()->json(['gameState'=>$targetGame->gameState], 200);
        }
        else{
            return response()->json(['message' => 'player not in game'], 403);
        }

    }

}