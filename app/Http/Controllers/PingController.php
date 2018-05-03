<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 03/05/2018
 * Time: 13:54
 */

namespace App\Http\Controllers;


class PingController extends Controller
{


    public function getPing()
    {

        return 'pong';
    }

}