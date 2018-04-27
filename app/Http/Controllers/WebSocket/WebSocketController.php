<?php
namespace App\Http\Controllers\WebSocket;
use App\Util\GamesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class WebSocketController
{


    public function onOpen(Request $request)
    {
        return response()->json(['userId' => Auth::id()], 200);

    }

    public function onClose(Request $request, GamesManager $gm)
    {

        $userId = Auth::id();
        $result = $gm->disconnect($userId);


        return response()->json(['result' => $result], 200);


    }
    public function onError(Request $request)
    {

    }


}