<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 18/02/2018
 * Time: 01:50
 */

namespace App\Util;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GamesManager
{
    public static function findGameInWhichUserParticipates($userId){

        $games = Cache::get('games', []);
        foreach ($games as $game){
            if (in_array($userId,$game->players ) || in_array($userId,$game->watchers)){
                return $game;
            }
        }
        return null;
    }

    public static function  findGamesByCategory($category){//!!! when will be multiple categories, filter
        return Cache::get('games', []);


    }
}