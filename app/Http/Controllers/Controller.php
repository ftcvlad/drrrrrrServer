<?php

namespace App\Http\Controllers;

use App\Events\ExampleEvent;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Support\Facades\Log;
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    public function fireEvent()
    {
        Log::info('yoyo');
        //event(new ExampleEvent("new move data"));
    }

}
