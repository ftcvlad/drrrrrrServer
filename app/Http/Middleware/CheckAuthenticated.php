<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Auth;
use Closure;
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
        if (!Auth::check()){
            return response()->json(['msg' => 'Unauthorized!'], 401);
        }

        return $next($request);
    }
}
