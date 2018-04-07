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
use App\Game;

class GamesManager
{

    public function createGame($userId){

        $games = Cache::get('games', []);

        //!!! make sure user is not in some game already
        $currentGame = $this->findGameInWhichUserParticipates($userId);
        if ($currentGame != null){
            abort(403, 'cannot join > 1 game');//already in some game
        }

        $uuid = $this->generateUuid($games);
        $boardState = $this->createStartGrid();


        $createdGame = new Game($uuid, $userId, $boardState);

        $games[] = $createdGame;
        Cache::forever('games', $games);
        return $createdGame;
    }


    public function playGame($gameId, $playerId){

        $games = Cache::get('games', []);



        foreach ($games as $game){
            if ($game->gameId == $gameId){

                if (count($game->players)>1){
                    abort(403, 'game is full');
                }

                $game->players[] = $playerId;
                $game->isGameGoing = true;


                Cache::forever('games', $games);
                return $game;
            }
        }

        abort(403, 'game doesn\'t exist ');





    }






    public function findGameInWhichUserParticipates($userId){

        $games = Cache::get('games', []);
        foreach ($games as $game){
            if (in_array($userId,$game->players ) || in_array($userId,$game->watchers)){
                return $game;
            }
        }
        return null;
    }

    private function generateUuid(&$games){

        $success = false;
        while(!$success){
            $uuid = uniqid();
            $success = true;
            foreach ($games as $game){//check that it's ineed unique
                if ($game->gameId == $uuid){
                    $success = false;
                    break;
                }
            }
        }
        return $uuid;

    }
    private  function createStartGrid (){
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }







    public function  findGamesByCategory($category){//!!! when will be multiple categories, filter
        return Cache::get('games', []);
    }


    public function findGameByGameId($gameId){
        $games = Cache::get('games',[]);
        foreach ($games as $game){
            if ($game->gameId == $gameId){
                return $game;
            }
        }
        return null;
    }

}