<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class WebSocketController
{


    public function onOpen(Request $request)
    {
        if (Auth::check()) {
            return response()->json(['userId' => Auth::id()], 200);
        }

        return response()->json(['message' => 'unauthorised'], 401);
    }

    public function onClose(Request $request)
    {

    }
    public function onError(Request $request)
    {

    }


}