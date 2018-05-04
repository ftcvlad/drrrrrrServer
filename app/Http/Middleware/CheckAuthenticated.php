<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Support\Facades\Log;
use App\User;
class CheckAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        Log::info( User::where( 'id', 1 )->first()->username);
        if (!Auth::check()){
            Log::info('pizdec');
            return response()->json(['message' => 'Unauthorized!'], 401);
        }

        return $next($request);
    }
}
