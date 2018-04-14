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
use Illuminate\Support\Facades\Auth;

class GamesManager
{
    //games -- stores uuids of all games
    //13223dfdf -- each game is stored separately in cache (key: uuid, value:game)
    public function createGame($userId)
    {
        $this->ensureUserNotInGame($userId);

        $gameIds = Cache::get('gameIds', []);




        $uuid = $this->generateUuid($gameIds);
        $boardState = $this->createStartGrid();

        $player = $this->makePlayerObject($userId);
        $createdGame = new Game($uuid, $userId, $boardState, $player);


        $gameIds[] = $uuid;
        Cache::forever('gameIds', $gameIds);
        Cache::forever($uuid, serialize($createdGame));


        return $createdGame;
    }

    private function makePlayerObject($playerId){
        $username  = Auth::user()->email;//TODO make username mandatory?
        return array("id"=>$playerId, "username"=>$username);
    }

    private function ensureUserNotInGame($userId){//!!! make sure user is not in some game already
        $currentGame = $this->findGameInWhichUserParticipates($userId);
        if ($currentGame != null){
            abort(403, 'cannot join > 1 game');
        }
    }

    public function playGame($gameId, $playerId)
    {
        $this->ensureUserNotInGame($playerId);

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null){
            abort(403, 'game doesn\'t exist ');
        }
        else{
            $game = unserialize($gameStr);
            if (count($game->players)>1){
                abort(403, 'game is full');
            }

            $game->players[] = $this->makePlayerObject($playerId);
            $game->isGameGoing = true;


            Cache::forever($gameId, serialize($game));
            return $game;
        }

    }


    public function watchGame($gameId, $playerId)
    {
        $this->ensureUserNotInGame($playerId);

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null){
            abort(403, 'game doesn\'t exist ');
        }
        else{
            $game = unserialize($gameStr);

            $game->watchers[] = $this->makePlayerObject($playerId);

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

                foreach($game->players as $player) {
                    if ($player["id"] == $userId) {
                        return $game;
                    }
                }

                foreach($game->watchers as $watcher) {
                    if ($watcher["id"] == $userId) {
                        return $game;
                    }
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



    public function userPick($row, $col, $userId, $gameId){

        $gameStr = Cache::get($gameId);
        if ($gameStr == null){
            return null;//move not allowed!
        }
        $game = unserialize($gameStr);


        if ($game->isGameGoing && $game->players[$game->currentPlayer]["id"] == $userId){//if user's turn

            if ($game->selectChecker == true){//checker selection

                $turnMultiplier = 1;
                if ($game->currentPlayer == 1){
                    $turnMultiplier = -1;
                }
                if ($game->boardState[$row][$col] * $turnMultiplier >0){//own piece
                    $possibleGoChoices = [];
                     if (abs($game->boardState[$row][$col]) == 1) {//if checker

                         $possibleGoChoices = $this->getCheckerBeatOptions($row, $col, $game->boardState, $turnMultiplier);
                         if (count($possibleGoChoices) == 0){
                            $beatsExist = $this->getAllBeatOptions($game->boardState, $turnMultiplier);
                            if ($beatsExist){return null;}//move not allowed


                            $possibleGoChoices = $this->getCheckerGoOptions($row, $col, $game->boardState,$turnMultiplier);
                         }
                     }
                     else if (abs($game->boardState[$row][$col]) == 2){
                         $possibleGoChoices = $this->getDamkaBeatOptions($row, $col, $game->boardState, 0, 0, $turnMultiplier);
                         if (count($possibleGoChoices) == 0){
                             $beatsExist = $this->getAllBeatOptions($game->boardState, $turnMultiplier);
                             if ($beatsExist){return null;}//move not allowed

                             $possibleGoChoices = $this->getDamkaGoOptions($row, $col, $game->boardState);
                         }
                     }

                    if (count($possibleGoChoices) == 0) {//if no go options
                        return null;//move not allowed
                    }
                    $possibleGoChoices[] = array("row"=>$row, "col"=>$col);//last item is the select position itself, as it is a possible choice

                    $game->selectChecker = false;
                    $game->possibleGoChoices = $possibleGoChoices;
                    $game->pickedChecker = [$row, $col];

                    Cache::forever($gameId, serialize($game));

                    return $game;

                }

            }

        }
        else{
            return null;
        }


    }

    public function userMoveWrapper($row, $col, $userId, $gameId){

        $gameStr = Cache::get($gameId);
        if ($gameStr == null){
            return null;//move not allowed!
        }
        $game = unserialize($gameStr);

        $result = $this->userMove($row, $col, $userId, $game);

        if ($result != null){
            Cache::forever($gameId, serialize($game));
        }
        return $result;
    }

    public function userMove($row, $col, $userId, &$game){


        if ($game->isGameGoing && $game->players[$game->currentPlayer]["id"] == $userId) {//if user's turn
            if ($game->selectChecker == false){



                $moveInf = array("row"=>$row, "col"=>$col);


                if ($moveInf === $game->possibleGoChoices[count($game->possibleGoChoices)-1] && $game->moves[count($game->moves)-1]["finished"] == true){//stopped at start position and deleted nothing
                    $game->selectChecker = true;
                    $game->pickedChecker = [];
                    $game->possibleGoChoices = [];
                    return array("boardChanged"=>false, "game"=>$game);
                }

                $turnMultiplier = 1;
                if ($game->currentPlayer == 1){
                    $turnMultiplier = -1;
                }

                for ($i = 0; $i < count($game->possibleGoChoices); $i++) {
                    if ($moveInf === $game->possibleGoChoices[$i]) {

                        $prevPos = array("row"=>$game->pickedChecker[0], "col"=>$game->pickedChecker[1]);
                        $nextPos = $moveInf;
                        $prevType = $game->boardState[$prevPos["row"]][$prevPos["col"]];
                        //update grid on prev and next pos

                        if ($game->boardState[$prevPos["row"]][$prevPos["col"]] == 2 * $turnMultiplier ||
                            ($nextPos["row"] == 0 && $turnMultiplier == 1) ||
                            ($nextPos["row"] == 7 && $turnMultiplier == -1)) {
                            $game->boardState[$nextPos["row"]][$nextPos["col"]] = 2 * $turnMultiplier;
                        } else {
                            $game->boardState[$nextPos["row"]][$nextPos["col"]] = $turnMultiplier;
                        }
                        $game->boardState[$prevPos["row"]][$prevPos["col"]] = 0;

                        $killed = $this->getDeletedCell($prevPos, $nextPos, $game->boardState);


                        //3 FUCKING OPTIONS!!!!!!!!!!!!!!!!!!!!!!!
                        if (count($killed) == 0) {//KILLED NONE

                            $game->selectChecker = true;
                            $game->pickedChecker = [];
                            $game->possibleGoChoices = [];

                            $game->moves[] = array("player"=>$game->currentPlayer,
                                                    "finished"=>true,
                                                    "moveInfo"=> [array("prev"=>$prevPos, "next"=>$nextPos, "killed"=>null, "prevType"=>$prevType)]);
                            $this->afterTurn($game, $turnMultiplier);

                            return array("boardChanged"=>true, "game"=>$game);

                        }
                        else {

                            $killed["type"] = $game->boardState[$killed["row"]][$killed["col"]];
                            $game->boardState[$killed["row"]][$killed["col"]] = 66;



                            if ($game->boardState[$nextPos["row"]][$nextPos["col"]] === 2 * $turnMultiplier) {
                                $game->possibleGoChoices = $this->getDamkaBeatOptions($nextPos["row"], $nextPos["col"], $game->boardState, 0, 0, $turnMultiplier);//cannot jump over 66
                            }
                            else {
                                $game->possibleGoChoices = $this->getCheckerBeatOptions($nextPos["row"], $nextPos["col"], $game->boardState, $turnMultiplier);//cannot jump over 66
                            }


                            if (count( $game->possibleGoChoices) == 0) {//KILLED AND NO MORE TO KILL


                                if ($game->moves[count($game->moves)-1]["player"] != $game->currentPlayer){//1st move (in sequence) by this player
                                    $game->moves[] = array("player"=>$game->currentPlayer,//new move
                                                            "finished"=>true,
                                                            "moveInfo"=> [array("prev"=>$prevPos, "next"=>$nextPos, "killed"=>$killed, "prevType"=>$prevType)]);
                                }
                                else{
                                    $game->moves[count($game->moves)-1]["moveInfo"][] = array("prev"=>$prevPos, "next"=>$nextPos, "killed"=>$killed, "prevType"=>$prevType);//continuing move
                                    $game->moves[count($game->moves)-1]["finished"] = true;
                                }
                                $game->selectChecker = true;
                                $game->pickedChecker = [];


                                $moveInfo = $game->moves[count($game->moves)-1]["moveInfo"];
                                for ($i = 0; $i < count($moveInfo); $i++) {
                                    $game->boardState[$moveInfo[$i]["killed"]["row"]][$moveInfo[$i]["killed"]["col"]] = 0;//get rid of 66
                                }


                                $this->afterTurn($game, $turnMultiplier);

                                return array("boardChanged"=>true, "game"=>$game);
                            }
                            else {//KILLED AND STILL MORE TO KILL
                                if ($game->moves[count($game->moves)-1]["player"] != $game->currentPlayer){//1st move (in sequence) by this player
                                    $game->moves[] = array("player"=>$game->currentPlayer,//new move
                                        "finished"=>false,
                                        "moveInfo"=> [array("prev"=>$prevPos, "next"=>$nextPos, "killed"=>$killed, "prevType"=>$prevType)]);
                                }
                                else{
                                    $game->moves[count($game->moves)-1]["moveInfo"][] = array("prev"=>$prevPos, "next"=>$nextPos, "killed"=>$killed,"prevType"=>$prevType);//continuing move
                                }

                                $game->pickedChecker = [$nextPos["row"], $nextPos["col"]];


                                return array("boardChanged"=>true, "game"=>$game);
                             }


                        }


                    }
                }



            }

        }


    }


    private function afterTurn(&$game, $turnMultiplier){
        $turnMultiplier *= -1;
        //check if enemy lost
        $lost = $this->checkLost($game->boardState, $turnMultiplier);//check if enemy lost after my turn!
        if ($lost){

            Log::info("player won!");
            //updatePlayerStatistics("enemyLost",theGame, socket);//TODO update player statistics
            $game->isGameGoing = false;
        }

        //change turn
        $game->currentPlayer = $game->currentPlayer == 0 ? 1 : 0;


    }

    private function checkLost(&$boardState, $turnMultiplier){
        $allGoOpt = [];
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($boardState[$r][$c] ==  $turnMultiplier) {
                    $allGoOpt = $this->getCheckerGoOptions($r, $c, $boardState, $turnMultiplier);
                }
                else if ($boardState[$r][$c] == 2 * $turnMultiplier) {
                    $allGoOpt = $this->getDamkaGoOptions($r, $c, $boardState);
                }

                if (count($allGoOpt) > 0) {
                    return false;
                }
            }
        }

        return !$this->getAllBeatOptions($boardState, $turnMultiplier);
    }



    private function getDeletedCell($prevPos, $nextPos, &$boardState){
        //find killed checker
        $dx=($nextPos["row"]-$prevPos["row"])/abs($nextPos["row"]-$prevPos["row"]);
        $dy=($nextPos["col"]-$prevPos["col"])/abs($nextPos["col"]-$prevPos["col"]);

        for ( $r=$prevPos["row"]+ $dx, $c=$prevPos["col"]+$dy; $r!=$nextPos["row"]; $r=$r+$dx, $c=$c+$dy){

            if ($boardState[$r][$c] != 0){
                return array("row"=>$r, "col"=>$c);
            }
        }
        return array();
    }


    private function getCheckerBeatOptions($row, $col, &$boardState, $turnMultiplier){

        $beatPos = [];

        for ($dx = -1; $dx<=1; $dx+=2){
            for ($dy = -1; $dy<=1; $dy+=2){
                $newx = $row + $dx*2;
                $newy = $col + $dy*2;
                if ($newx < 8 && $newx>=0 && $newy <8 && $newy>=0 && $boardState[$newx][$newy] == 0
                    && $boardState[$row+$dx][$col+$dy]*$turnMultiplier<0 && $boardState[$row+$dx][$col+$dy] != 66){//can't jump over 66
                    $beatPos[] = array("row"=>$newx, "col"=> $newy);
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
                                $beatPos[] = array("row"=>$newx, "col"=>$newy);//when found 1 secondary beat option return right away
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
                            $beatPos[] = array("row"=>$newx, "col"=>$newy);
                            $canBeatSnd = true;
                        }
                        else{
                            $tempPos[] = array("row"=>$newx, "col"=>$newy);
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

            $goPos[] = array("row"=>$row-$turnMultiplier, "col"=> $col-1);
        }
        if ($row-$turnMultiplier<8 && $row-$turnMultiplier>=0 && $col+1<8 && $col+1>=0
                                        && $boardState[$row-$turnMultiplier][$col+1]==0){//usergame.turnMultiplier!!
            $goPos[] = array("row"=>$row-$turnMultiplier, "col"=> $col+1);
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

                    $goPos[] = array("row"=>$newx, "col"=>$newy);

                    $newx = $newx + $dx ;
                    $newy = $newy + $dy ;
                }
            }
        }

        return $goPos;
    }


}