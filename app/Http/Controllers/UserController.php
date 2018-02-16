<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function getUser(Request $request)
    {

        $user = Auth::user();
        if ($user){
            return response($user, 200);
        }
        return response('', 404);
    }
}
