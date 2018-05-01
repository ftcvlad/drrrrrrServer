<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\User;
class UserController extends Controller
{
    public function getCurrentUser(Request $request)
    {
        $user = Auth::user();
        if ($user){
            return response($user, 200);
        }
        return response('', 404);
    }


    public function getUser(Request $request, $userId){


        $user = User::findOrFail($userId);

        return $user;

    }

    public function updateUser(Request $request, $userId){

        $birthday = $request->input("birthday");
        $username = $request->input("username");
        $gender = $request->input("gender");


        $user = User::find($userId);

        $user->username = $username;
        $user->birthday = $birthday;
        $user->gender = $gender;

        $user->save();
        return $user;

    }
}
