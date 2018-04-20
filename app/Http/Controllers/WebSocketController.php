<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;



class WebSocketController
{


    public function onOpen(Request $request)
    {
        return response()->json(['userId' => Auth::id()], 200);

    }

    public function onClose(Request $request)
    {

    }
    public function onError(Request $request)
    {

    }


}