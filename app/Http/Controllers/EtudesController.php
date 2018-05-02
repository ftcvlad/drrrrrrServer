<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 01/05/2018
 * Time: 04:58
 */

namespace App\Http\Controllers;

use App\Util\EtudesManager;
use App\Util\GamesManager;

use App\Util\StatsManager;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\SaveGameRequest;


class EtudesController extends Controller
{



    public function getEtudes(Request $request, EtudesManager $em){
        $etudes = $em->getEtudes();




        return response($etudes, 200);

    }


}