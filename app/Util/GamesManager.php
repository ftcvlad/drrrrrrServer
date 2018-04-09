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
    //games -- stores uuids of all games
    //13223dfdf -- each game is stored separately in cache (key: uuid, value:game)
    public function createGame($userId)
    {
        $gameIds = Cache::get('gameIds', []);

        //!!! make sure user is not in some game already
        $currentGame = $this->findGameInWhichUserParticipates($userId);
        if ($currentGame != null){
            abort(403, 'cannot join > 1 game');//already in some game
        }

        $uuid = $this->generateUuid($gameIds);
        $boardState = $this->createStartGrid();
        $createdGame = new Game($uuid, $userId, $boardState);


        $gameIds[] = $uuid;
        Cache::forever('gameIds', $gameIds);
        Cache::forever($uuid, serialize($createdGame));


        return $createdGame;
    }


    public function playGame($gameId, $playerId)
    {

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null){
            abort(403, 'game doesn\'t exist ');
        }
        else{
            $game = unserialize($gameStr);
            if (count($game->players)>1){
                abort(403, 'game is full');
            }

            $game->players[] = $playerId;
            $game->isGameGoing = true;


            Cache::forever($gameId, serialize($game));
            return $game;
        }


    }


    public function removeAllGames(){


        $gameIds = Cache::get('gameIds');
        foreach ($gameIds as $gameId){
            Cache::forget($gameId);
        }
        Cache::forget('gameIds');
    }



    public function findGameInWhichUserParticipates($userId)
    {

        $gameIds = Cache::get('gameIds', []);
        foreach ($gameIds as $gameId){
            $gameStr = Cache::get($gameId, null);
            if ($gameStr != null){
                $game = unserialize($gameStr);
                if (in_array($userId,$game->players ) || in_array($userId,$game->watchers)){
                    return $game;
                }
            }
            else{
                //assume in gameIds and games in sync, for each gameId there is game
            }


        }
        return null;
    }

    private function generateUuid(&$gameIds)
    {

        $success = false;
        while(!$success){
            $uuid = uniqid();
            $success = true;
            foreach ($gameIds as $gameId){//check that it's ineed unique
                if ($gameId == $uuid){
                    $success = false;
                    break;
                }
            }
        }
        return $uuid;

    }
    private  function createStartGrid ()
    {
        return [[0, -1, 0, -1, 0, -1, 0, -1],
            [-1, 0, -1, 0, -1, 0, -1, 0],
            [0, -1, 0, -1, 0, -1, 0, -1],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [0, 0, 0, 0, 0, 0, 0, 0],
            [1, 0, 1, 0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1, 0]];
    }







    public function  findGamesByCategory($category)//!!! when will be multiple categories, filter
    {
        $gameIds = Cache::get('gameIds', []);
        $allGames = [];
        foreach ($gameIds as $gameId){
            $gameStr = Cache::get($gameId, null);
            if ($gameStr != null){
                $allGames[] = unserialize($gameStr);
            }

        }
        return $allGames;
    }


    public function findGameByGameId($gameId)
    {
        $gameStr = Cache::get($gameId,null);
        if ($gameStr != null){
            return unserialize($gameStr);
        }
        return null;

    }


    //********************** MOVEMENT of pieces **********************



    public function userPick($row, $column, $userId, $gameId){

        $gameStr = Cache::get($gameId);
        if ($gameStr == null){
            return null;//move not allowed!
        }
        $game = unserialize($gameStr);


        if ($game->isGameGoing && $game->players[$game->currentPlayer] == $userId){//if user's turn

            if ($game->selectChecker == true){//checker selection

                $turnMultiplier = 1;
                if ($game->currentPlayer == 1){
                    $turnMultiplier = -1;
                }
                if ($game->boardState[$row][$column] * $turnMultiplier >0){//own piece
                    $possibleGoChoices = [];
                     if (abs($game->boardState[$row][$column]) == 1) {//if checker

                         $possibleGoChoices = $this->getCheckerBeatOptions($row, $column, $game->boardState, $turnMultiplier);
                         if (count($possibleGoChoices) == 0){
                            $beatsExist = $this->getAllBeatOptions($game->boardState, $turnMultiplier);
                            if ($beatsExist){return null;}//move not allowed


                            $possibleGoChoices = $this->getCheckerGoOptions($row, $column, $game->boardState,$turnMultiplier);
                         }
                     }
                     else if (abs($game->boardState[$row][$column]) == 2){
                         $possibleGoChoices = $this->getDamkaBeatOptions($row, $column, $game->boardState, 0, 0, $turnMultiplier);
                         if (count($possibleGoChoices) == 0){
                             $beatsExist = $this->getAllBeatOptions($game->boardState, $turnMultiplier);
                             if ($beatsExist){return null;}//move not allowed

                             $possibleGoChoices = $this->getDamkaGoOptions($row, $column, $game->boardState);
                         }
                     }




                    if (count($possibleGoChoices) == 0) {//if no go options
                        return null;//move not allowed
                    }
                    $possibleGoChoices[] = array("row"=>$row, "column"=>$column);//last item is the select position itself, as it is a possible choice

                    $game->selectChecker = false;
                    $game->possibleGoChoices = $possibleGoChoices;
                    $game->pickedChecker = [$row, $column];

                    Cache::forever($gameId, serialize($game));

                    return $game;

                }

            }

        }
        else{
            return null;
        }


    }


    private function getCheckerBeatOptions($row, $column, &$boardState, $turnMultiplier){

        $beatPos = [];

        for ($dx = -1; $dx<=1; $dx+=2){
            for ($dy = -1; $dy<=1; $dy+=2){
                $newx = $row + $dx*2;
                $newy = $column + $dy*2;
                if ($newx < 8 && $newx>=0 && $newy <8 && $newy>=0 && $boardState[$newx][$newy] == 0
                    && $boardState[$row+$dx][$column+$dy]*$turnMultiplier<0 && $boardState[$row+$dx][$column+$dy] != 66){//can't jump over 66
                    $beatPos[] = array("row"=>$newx, "column"=> $newy);
                }
            }
        }
        return $beatPos;
    }


    private function getAllBeatOptions(&$boardState, $turnMultiplier){//this used in determining lost AND in picking a piece

        $otherBeatOptions = [];
        for ($r = 0; $r<8; $r++) {
            for ($c = 0; $c <8; $c++) {
                if ($boardState[$r][$c] == 1 * $turnMultiplier){
                    $otherBeatOptions = $this->getCheckerBeatOptions($r, $c, $boardState, $turnMultiplier);
                }
                else if($boardState[$r][$c] == 2 * $turnMultiplier){
                    $otherBeatOptions = $this->getDamkaBeatOptions($r, $c, $boardState, 0, 0, $turnMultiplier);
                }
                if (count($otherBeatOptions) > 0){
                    return true;
                }
            }
        }
        return false;

    }

    private function getDamkaBeatOptions($row, $col, &$boardState, $currDx, $currDy, $turnMultiplier){//TODO check validity :)


        //get array of beats
        $beatPos = [];
        for ($dx = -1; $dx <= 1; $dx = $dx + 2) {
            for ($dy = -1; $dy <= 1; $dy = $dy + 2) {

                if ($dx == $currDx*-1 && $dy == $currDy*-1){//to avoid going back
                    continue;
                }

                $newx = $row + $dx;
                $newy = $col + $dy;

                $tempPos = [];
                $destroyedOne = false;
                $canBeatSnd = false;

                while ($newx < 8 && $newx >= 0 && $newy < 8 && $newy >= 0){
                    if (!$destroyedOne){


                        if ($newx==7 || $newx==0 || $newy==7 || $newy==0) {//don't try to beat border pieces
                            break;
                        }
                        else if ( $boardState[$newx][$newy]*$turnMultiplier <0 && $boardState[$newx+ $dx][$newy + $dy]==0
                                                                        &&  $boardState[$newx][$newy]!=66 ) {//found to kill
                            if ($currDx == 0){//direct call
                                $destroyedOne = true;
                            }
                            else{//recursive call
                                $beatPos[] = array("row"=>$newx, "column"=>$newy);//when found 1 secondary beat option return right away
                                return $beatPos;
                            }

                        }
                        else if ( $boardState[$newx][$newy]*$turnMultiplier <0 && ($boardState[$newx+ $dx][$newy + $dy]*$turnMultiplier<0
                                ||  $boardState[$newx+$dx][$newy+$dy]==66)){//two enemy pieces stop us
                            break;//if cannot beat in this direction!
                        }
                        else if ($boardState[$newx][$newy]==66 || $boardState[$newx][$newy]*$turnMultiplier>0 ){//can't jump over destroyed or own pieces
                            break;
                        }
                    }
                    else{
                        if ($boardState[$newx][$newy]!=0){
                            break;

                        }
                        $secondaryBeatOpt = $this->getDamkaBeatOptions($newx,$newy,$boardState,$dx,$dy,$turnMultiplier);
                        if (count($secondaryBeatOpt)>0 ){
                            $beatPos[] = array("row"=>$newx, "column"=>$newy);
                            $canBeatSnd = true;
                        }
                        else{
                            $tempPos[] = array("row"=>$newx, "column"=>$newy);
                        }
                    }

                    $newx =$newx + $dx ;
                    $newy = $newy + $dy;
                }//end while



                if ($destroyedOne && !$canBeatSnd){
                    $beatPos = array_merge($beatPos, $tempPos); //attach tempPos to the end of beatPos
                }
            }
        }
        return $beatPos;
    }


    private function getCheckerGoOptions($row, $col, &$boardState, $turnMultiplier){


        $goPos = [];
        //players 1 and 2 go to different directions, but columns same stuff
        if ($row-$turnMultiplier<8 && $row-$turnMultiplier>=0 && $col-1<8 && $col-1>=0//TODO attached to 8x8 checkers
                                        && $boardState[$row-$turnMultiplier][$col-1]==0){//usergame.turnMultiplier!

            $goPos[] = array("row"=>$row-$turnMultiplier, "column"=> $col-1);
        }
        if ($row-$turnMultiplier<8 && $row-$turnMultiplier>=0 && $col+1<8 && $col+1>=0
                                        && $boardState[$row-$turnMultiplier][$col+1]==0){//usergame.turnMultiplier!!
            $goPos[] = array("row"=>$row-$turnMultiplier, "column"=> $col+1);
        }

        return $goPos;

    }


    private function getDamkaGoOptions($row, $col, &$boardState){
        $goPos = [];
        for ($dx = -1; $dx <= 1; $dx = $dx + 2) {
            for ($dy = -1; $dy <= 1; $dy = $dy + 2) {

                $newx = $row + $dx ;
                $newy = $col + $dy ;
                while ($newx < 8 && $newx >= 0 && $newy < 8 && $newy >= 0 &&  $boardState[$newx][$newy]==0){

                    $goPos[] = array("row"=>$newx, "column"=>$newy);

                    $newx = $newx + $dx ;
                    $newy = $newy + $dy ;
                }
            }
        }

        return $goPos;
    }


}