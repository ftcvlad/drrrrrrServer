<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 01/05/2018
 * Time: 04:59
 */

namespace App\Util;

use Illuminate\Support\Facades\DB;

class EtudesManager
{
    public function getEtudes(){




        $etudes = DB::table("etudes")->get();



        foreach ($etudes as $etude){

            $etude->moves = unserialize($etude->moves);
            $etude->board_state = unserialize($etude->board_state);
        }

        return $etudes;
    }
}