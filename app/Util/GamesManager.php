<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 18/02/2018
 * Time: 01:50
 */

namespace App\Util;

use App\GameState;
use App\PlayerStatuses;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Game;
use App\Player;
use App\Watcher;
use Illuminate\Support\Facades\Auth;
use App\Util\StatsManager;

class GamesManager
{

    private function removeParticipant(&$participantList, $userId){
        for ($i = 0; $i < count($participantList); $i++) {
            if ($participantList[$i]->id == $userId) {
                $participant = $participantList[$i];
                array_splice($participantList, $i, 1);
                return array("participant"=> $participant, "index"=>$i);
            }
        }
        return null;
    }


    public function exitGame($gameId, $userId)
    {

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);

            //TODO security: check if user is a player or a watcher
            $isLastPerson = count($game->gameInfo->players) + count($game->gameInfo->watchers) == 1;

            if ($isLastPerson) {
                $this->removeGameById($gameId);
                return array("isLastPerson" => true);
            } else {
                $isPlayer = null;

                $leavingPlayer = $this->removeParticipant($game->gameInfo->players, $userId);

                if ($leavingPlayer){
                    $isPlayer = true;
                }
                else{
                    $leavingWatcher = $this->removeParticipant($game->gameInfo->watchers, $userId);
                    if ($leavingWatcher){
                        $isPlayer = false;
                    }
                    else{
                        abort(403, "impossible happened: player in game is not in game");
                    }
                }



                if (!$isPlayer){
                    Cache::forever($gameId, serialize($game));
                    return array("isLastPerson" => false, "isPlayer"=>false, "gameInfo"=>$game->gameInfo);
                }
                else{
                    if (count($game->gameInfo->players) == 1){//1 player exit, but 1 still there
                        $game->gameInfo->players[0]->currentStatus = PlayerStatuses::waiting;
                    }

                    $wasGameGoing = $game->gameState->isGameGoing;
                    if (!$wasGameGoing){
                        Cache::forever($gameId, serialize($game));
                        return array("isLastPerson" => false, "isPlayer"=>true, "wasGameGoing"=>false, "gameInfo"=>$game->gameInfo);
                    }
                    else{

                       $opponentId = $game->gameInfo->players[0]->id;//remaining player
                       $playsWhite = $leavingPlayer["index"] == 0;
                       $gameResult = $this->finishGame($game, $userId, $opponentId, $playsWhite, 0, "Left" );


                        Cache::forever($gameId, serialize($game));

                        return array("isLastPerson" => false,
                            "isPlayer"=>true,
                            "wasGameGoing"=>true,
                            "gameInfo"=>$game->gameInfo,
                            "gameResult" => $gameResult);
                    }

                }


            }


        }
    }

    public function surrender($gameId, $userId){
        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);

            if ($game->gameState->isGameGoing == false){
                abort(403, "impossible happened: surrenderer from non-going game");
            }


            if ($game->gameInfo->players[0]->id == $userId ){
                $gameResult = $this->finishGame($game, $userId, $game->gameInfo->players[1]->id,
                    true, 0, "Surrendered" );
            }
            else if ($game->gameInfo->players[1]->id == $userId){
                $gameResult = $this->finishGame($game, $userId, $game->gameInfo->players[0]->id,
                    false, 0, "Surrendered" );
            }
            else{
                abort(403, "impossible happened: surrenderer is not among players");
            }

            $game->gameInfo->players[0]->currentStatus = PlayerStatuses::confirming;
            $game->gameInfo->players[1]->currentStatus = PlayerStatuses::confirming;

            Cache::forever($gameId, serialize($game));

            return $gameResult;

        }
    }


    public function finishGame(&$game, $initiatorId, $opponentId, $playsWhite, $matchResult, $reason){
        $game->gameState->isGameGoing = false;
        $sm = new StatsManager();

        $gameResult = $sm->saveGameResults($initiatorId, $opponentId, $matchResult, $playsWhite);

        if ($playsWhite){
            $gameResult[0]["stats"] = $sm->getPlayerWinDrawLoseStatistics($initiatorId);
            $gameResult[1]["stats"] = $sm->getPlayerWinDrawLoseStatistics($opponentId);
            $gameResult[0]["reason"] = $reason;
        }
        else{
            $gameResult[0]["stats"] = $sm->getPlayerWinDrawLoseStatistics($opponentId);
            $gameResult[1]["stats"] = $sm->getPlayerWinDrawLoseStatistics($initiatorId);
            $gameResult[1]["reason"] = $reason;
        }

        return $gameResult;
    }


    //games -- stores uuids of all games
    //13223dfdf -- each game is stored separately in cache (key: uuid, value:game)
    public function createGame($userId)
    {
        $this->ensureUserNotInGame($userId);

        $gameIds = Cache::get('gameIds', []);


        $uuid = $this->generateUuid($gameIds);


        $player = new Player(Auth::user()->email, $userId, PlayerStatuses::waiting);

        $createdGame = new Game($uuid, $player);


        $gameIds[] = $uuid;
        Cache::forever('gameIds', $gameIds);
        Cache::forever($uuid, serialize($createdGame));


        return $createdGame;
    }


    private function ensureUserNotInGame($userId)
    {//!!! make sure user is not in some game already
        $currentGame = $this->findGameInWhichUserParticipates($userId);
        if ($currentGame != null) {
            abort(403, 'cannot join > 1 game');
        }
    }





    public function playGame($gameId, $playerId)
    {
        $this->ensureUserNotInGame($playerId);

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);
            $nOfPlayersBefore = count($game->gameInfo->players);
            if ($nOfPlayersBefore > 1) {
                abort(403, 'game is full');
            }
            else if ($nOfPlayersBefore == 0){
                $game->gameInfo->players[] = new Player(Auth::user()->email, $playerId, PlayerStatuses::waiting);
            }
            else if ($nOfPlayersBefore == 1){
                $game->gameInfo->players[] = new Player(Auth::user()->email, $playerId, PlayerStatuses::ready);
                $game->gameInfo->players[0]->currentStatus = PlayerStatuses::confirming;

            }

            Cache::forever($gameId, serialize($game));
            return $game;
        }

    }

    public function confirmPlaying($gameId, $playerId){
        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);

            $currentPlayer = null;
            $i =0;
            foreach ($game->gameInfo->players as $player){
                if ($player->id == $playerId ){
                    $currentPlayer = $game->gameInfo->players[$i];
                    break;
                }
                $i++;
            }

            if (!$currentPlayer){
                abort(403, 'impossible happened: confirming player is not in players ');
            }


            $currentPlayer->currentStatus =  PlayerStatuses::ready;


            if (count($game->gameInfo->players) == 2
                && $game->gameInfo->players[1-$i]->currentStatus == PlayerStatuses::ready){


                $game->gameInfo->players[0]->currentStatus = PlayerStatuses::playing;
                $game->gameInfo->players[1]->currentStatus = PlayerStatuses::playing;

                $game->gameState = new GameState();//reset game state
                $game->gameState->isGameGoing = true;
                Cache::forever($gameId, serialize($game));
                return array("gameStarted" => true, "gameInfo"=>$game->gameInfo, "gameState"=>$game->gameState);
            }
            else{

                Cache::forever($gameId, serialize($game));
                return array("gameStarted" => false, "gameInfo"=>$game->gameInfo);
            }


        }


    }


    public function watchGame($gameId, $watcherId)
    {
        $this->ensureUserNotInGame($watcherId);

        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);

            $watcher = new Watcher(Auth::user()->email, $watcherId);
            $game->gameInfo->watchers[] = $watcher;

            Cache::forever($gameId, serialize($game));
            return $game;
        }

    }

    private function removeGameById($gameId){
        Cache::forget($gameId);

        $gameIds = Cache::get('gameIds');
        $remainingGameIds = [];
        foreach ($gameIds as $nextGameId) {
            if ($nextGameId != $gameId){
                $remainingGameIds[] = $nextGameId;
            }
        }
        Cache::forever('gameIds', $remainingGameIds);
    }

    public function removeAllGames()
    {
        $gameIds = Cache::get('gameIds');
        foreach ($gameIds as $gameId) {
            Cache::forget($gameId);
        }
        Cache::forget('gameIds');
    }


    public function findGameInWhichUserParticipates($userId)
    {

        $gameIds = Cache::get('gameIds', []);
        foreach ($gameIds as $gameId) {
            $gameStr = Cache::get($gameId, null);
            if ($gameStr != null) {
                $game = unserialize($gameStr);

                foreach ($game->gameInfo->players as $player) {
                    if ($player->id == $userId) {
                        return $game;
                    }
                }

                foreach ($game->gameInfo->watchers as $watcher) {
                    if ($watcher->id == $userId) {
                        return $game;
                    }
                }


            } else {
                //assume in gameIds and games in sync, for each gameId there is game
            }


        }
        return null;
    }

    private function generateUuid(&$gameIds)
    {

        $success = false;
        while (!$success) {
            $uuid = uniqid();
            $success = true;
            foreach ($gameIds as $gameId) {//check that it's ineed unique
                if ($gameId == $uuid) {
                    $success = false;
                    break;
                }
            }
        }
        return $uuid;

    }




    public function findGamesByCategory($category)//!!! when will be multiple categories, filter
    {
        $gameIds = Cache::get('gameIds', []);
        $allGames = [];
        foreach ($gameIds as $gameId) {
            $gameStr = Cache::get($gameId, null);
            if ($gameStr != null) {
                $allGames[] = unserialize($gameStr);
            }

        }
        return $allGames;
    }


    public function findGameByGameId($gameId)
    {
        $gameStr = Cache::get($gameId, null);
        if ($gameStr != null) {
            return unserialize($gameStr);
        }
        return null;

    }


    //********************** MOVEMENT of pieces **********************


    public function userPick($row, $col, $userId, $gameId)
    {

        $gameStr = Cache::get($gameId);
        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        }
        $game = unserialize($gameStr);
        $gameState = $game->gameState;
        $gameInfo = $game->gameInfo;

        if ($gameState->isGameGoing && $gameInfo->players[$gameState->currentPlayer]->id == $userId) {//if user's turn

            if ($gameState->selectChecker == true) {//checker selection

                $turnMultiplier = 1;
                if ($gameState->currentPlayer == 1) {
                    $turnMultiplier = -1;
                }
                if ($gameState->boardState[$row][$col] * $turnMultiplier > 0) {//own piece
                    $possibleGoChoices = [];
                    if (abs($gameState->boardState[$row][$col]) == 1) {//if checker

                        $possibleGoChoices = $this->getCheckerBeatOptions($row, $col, $gameState->boardState, $turnMultiplier);
                        if (count($possibleGoChoices) == 0) {
                            $beatsExist = $this->getAllBeatOptions($gameState->boardState, $turnMultiplier);
                            if ($beatsExist) {
                                return null;
                            }//move not allowed


                            $possibleGoChoices = $this->getCheckerGoOptions($row, $col, $gameState->boardState, $turnMultiplier);
                        }
                    } else if (abs($gameState->boardState[$row][$col]) == 2) {
                        $possibleGoChoices = $this->getDamkaBeatOptions($row, $col, $gameState->boardState, 0, 0, $turnMultiplier);
                        if (count($possibleGoChoices) == 0) {
                            $beatsExist = $this->getAllBeatOptions($gameState->boardState, $turnMultiplier);
                            if ($beatsExist) {
                                return null;
                            }//move not allowed

                            $possibleGoChoices = $this->getDamkaGoOptions($row, $col, $gameState->boardState);
                        }
                    }

                    if (count($possibleGoChoices) == 0) {//if no go options
                        return null;//move not allowed
                    }
                    $possibleGoChoices[] = array("row" => $row, "col" => $col);//last item is the select position itself, as it is a possible choice

                    $gameState->selectChecker = false;
                    $gameState->possibleGoChoices = $possibleGoChoices;
                    $gameState->pickedChecker = [$row, $col];

                    Cache::forever($gameId, serialize($game));

                    return $gameState;

                }

            }

        } else {
            abort(403, 'trying to move other player / in a non-going game ');
        }


    }

    public function userMoveWrapper($row, $col, $userId, $gameId)
    {

        $gameStr = Cache::get($gameId);
        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        }
        $game = unserialize($gameStr);

        $result = $this->userMove($row, $col, $userId, $game);

        if ($result != null) {
            $result['gameId'] = $game->gameInfo->gameId;
            Cache::forever($gameId, serialize($game));
        }
        return $result;
    }

    public function userMove($row, $col, $userId, &$game)
    {

        $gameInfo = $game->gameInfo;
        $gameState = $game->gameState;

        if ($gameState->isGameGoing && $gameInfo->players[$gameState->currentPlayer]->id == $userId) {//if user's turn
            if ($gameState->selectChecker == false) {


                $moveInf = array("row" => $row, "col" => $col);


                if ($moveInf === $gameState->possibleGoChoices[count($gameState->possibleGoChoices) - 1]
                    && (count($gameState->moves) == 0 || $gameState->moves[count($gameState->moves) - 1]["finished"] == true)) {//stopped at start position and deleted nothing
                    $gameState->selectChecker = true;
                    $gameState->pickedChecker = [];
                    $gameState->possibleGoChoices = [];
                    return array("boardChanged" => false, "gameState" => $gameState);
                }

                $turnMultiplier = 1;
                if ($gameState->currentPlayer == 1) {
                    $turnMultiplier = -1;
                }

                for ($i = 0; $i < count($gameState->possibleGoChoices); $i++) {
                    if ($moveInf === $gameState->possibleGoChoices[$i]) {

                        $prevPos = array("row" => $gameState->pickedChecker[0], "col" => $gameState->pickedChecker[1]);
                        $nextPos = $moveInf;
                        $prevType = $gameState->boardState[$prevPos["row"]][$prevPos["col"]];
                        //update grid on prev and next pos

                        if ($gameState->boardState[$prevPos["row"]][$prevPos["col"]] == 2 * $turnMultiplier ||
                            ($nextPos["row"] == 0 && $turnMultiplier == 1) ||
                            ($nextPos["row"] == 7 && $turnMultiplier == -1)) {
                            $gameState->boardState[$nextPos["row"]][$nextPos["col"]] = 2 * $turnMultiplier;
                        } else {
                            $gameState->boardState[$nextPos["row"]][$nextPos["col"]] = $turnMultiplier;
                        }
                        $gameState->boardState[$prevPos["row"]][$prevPos["col"]] = 0;

                        $killed = $this->getDeletedCell($prevPos, $nextPos, $gameState->boardState);


                        //3 FUCKING OPTIONS!!!!!!!!!!!!!!!!!!!!!!!
                        if (count($killed) == 0) {//KILLED NONE

                            $gameState->selectChecker = true;
                            $gameState->pickedChecker = [];
                            $gameState->possibleGoChoices = [];
                            $gameState->moves[] = array("player" => $gameState->currentPlayer,
                                "finished" => true,
                                "moveInfo" => [array("prev" => $prevPos, "next" => $nextPos, "killed" => null, "prevType" => $prevType)]);
                            $this->afterTurn($gameState, $turnMultiplier);

                            return array("boardChanged" => true, "gameState" => $gameState);

                        } else {

                            $killed["type"] = $gameState->boardState[$killed["row"]][$killed["col"]];
                            $gameState->boardState[$killed["row"]][$killed["col"]] = 66;


                            if ($gameState->boardState[$nextPos["row"]][$nextPos["col"]] === 2 * $turnMultiplier) {
                                $gameState->possibleGoChoices = $this->getDamkaBeatOptions($nextPos["row"], $nextPos["col"], $gameState->boardState, 0, 0, $turnMultiplier);//cannot jump over 66
                            } else {
                                $gameState->possibleGoChoices = $this->getCheckerBeatOptions($nextPos["row"], $nextPos["col"], $gameState->boardState, $turnMultiplier);//cannot jump over 66
                            }


                            if (count($gameState->possibleGoChoices) == 0) {//KILLED AND NO MORE TO KILL


                                if ($gameState->moves[count($gameState->moves) - 1]["player"] != $gameState->currentPlayer) {//1st move (in sequence) by this player
                                    $gameState->moves[] = array("player" => $gameState->currentPlayer,//new move
                                        "finished" => true,
                                        "moveInfo" => [array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType)]);
                                } else {
                                    $gameState->moves[count($gameState->moves) - 1]["moveInfo"][] = array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType);//continuing move
                                    $gameState->moves[count($gameState->moves) - 1]["finished"] = true;
                                }
                                $gameState->selectChecker = true;
                                $gameState->pickedChecker = [];


                                $moveInfo = $gameState->moves[count($gameState->moves) - 1]["moveInfo"];
                                for ($i = 0; $i < count($moveInfo); $i++) {
                                    $gameState->boardState[$moveInfo[$i]["killed"]["row"]][$moveInfo[$i]["killed"]["col"]] = 0;//get rid of 66
                                }


                                $this->afterTurn($gameState, $turnMultiplier);//TODO

                                return array("boardChanged" => true, "gameState" => $gameState);
                            } else {//KILLED AND STILL MORE TO KILL
                                if ($gameState->moves[count($gameState->moves) - 1]["player"] != $gameState->currentPlayer) {//1st move (in sequence) by this player
                                    $gameState->moves[] = array("player" => $gameState->currentPlayer,//new move
                                        "finished" => false,
                                        "moveInfo" => [array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType)]);
                                } else {
                                    $gameState->moves[count($gameState->moves) - 1]["moveInfo"][] = array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType);//continuing move
                                }

                                $gameState->pickedChecker = [$nextPos["row"], $nextPos["col"]];


                                return array("boardChanged" => true, "gameState" => $gameState);
                            }


                        }


                    }
                }


            }

        }
        else{
            abort(403, 'trying to move other player / in a non-going game ');
        }


    }


    private function afterTurn(&$gameState, $turnMultiplier)
    {
        $turnMultiplier *= -1;
        //check if enemy lost
        $lost = $this->checkLost($gameState->boardState, $turnMultiplier);//check if enemy lost after my turn!
        if ($lost) {

            Log::info("player won!");
            //updatePlayerStatistics("enemyLost",theGame, socket);//TODO update player statistics
            $gameState->isGameGoing = false;
        }

        //change turn
        $gameState->currentPlayer = $gameState->currentPlayer == 0 ? 1 : 0;


    }

    private function checkLost(&$boardState, $turnMultiplier)
    {
        $allGoOpt = [];
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($boardState[$r][$c] == $turnMultiplier) {
                    $allGoOpt = $this->getCheckerGoOptions($r, $c, $boardState, $turnMultiplier);
                } else if ($boardState[$r][$c] == 2 * $turnMultiplier) {
                    $allGoOpt = $this->getDamkaGoOptions($r, $c, $boardState);
                }

                if (count($allGoOpt) > 0) {
                    return false;
                }
            }
        }

        return !$this->getAllBeatOptions($boardState, $turnMultiplier);
    }


    private function getDeletedCell($prevPos, $nextPos, &$boardState)
    {
        //find killed checker
        $dx = ($nextPos["row"] - $prevPos["row"]) / abs($nextPos["row"] - $prevPos["row"]);
        $dy = ($nextPos["col"] - $prevPos["col"]) / abs($nextPos["col"] - $prevPos["col"]);

        for ($r = $prevPos["row"] + $dx, $c = $prevPos["col"] + $dy; $r != $nextPos["row"]; $r = $r + $dx, $c = $c + $dy) {

            if ($boardState[$r][$c] != 0) {
                return array("row" => $r, "col" => $c);
            }
        }
        return array();
    }


    private function getCheckerBeatOptions($row, $col, &$boardState, $turnMultiplier)
    {

        $beatPos = [];

        for ($dx = -1; $dx <= 1; $dx += 2) {
            for ($dy = -1; $dy <= 1; $dy += 2) {
                $newx = $row + $dx * 2;
                $newy = $col + $dy * 2;
                if ($newx < 8 && $newx >= 0 && $newy < 8 && $newy >= 0 && $boardState[$newx][$newy] == 0
                    && $boardState[$row + $dx][$col + $dy] * $turnMultiplier < 0 && $boardState[$row + $dx][$col + $dy] != 66) {//can't jump over 66
                    $beatPos[] = array("row" => $newx, "col" => $newy);
                }
            }
        }
        return $beatPos;
    }


    private function getAllBeatOptions(&$boardState, $turnMultiplier)
    {//this used in determining lost AND in picking a piece

        $otherBeatOptions = [];
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($boardState[$r][$c] == 1 * $turnMultiplier) {
                    $otherBeatOptions = $this->getCheckerBeatOptions($r, $c, $boardState, $turnMultiplier);
                } else if ($boardState[$r][$c] == 2 * $turnMultiplier) {
                    $otherBeatOptions = $this->getDamkaBeatOptions($r, $c, $boardState, 0, 0, $turnMultiplier);
                }
                if (count($otherBeatOptions) > 0) {
                    return true;
                }
            }
        }
        return false;

    }

    private function getDamkaBeatOptions($row, $col, &$boardState, $currDx, $currDy, $turnMultiplier)
    {//TODO check validity :)


        //get array of beats
        $beatPos = [];
        for ($dx = -1; $dx <= 1; $dx = $dx + 2) {
            for ($dy = -1; $dy <= 1; $dy = $dy + 2) {

                if ($dx == $currDx * -1 && $dy == $currDy * -1) {//to avoid going back
                    continue;
                }

                $newx = $row + $dx;
                $newy = $col + $dy;

                $tempPos = [];
                $destroyedOne = false;
                $canBeatSnd = false;

                while ($newx < 8 && $newx >= 0 && $newy < 8 && $newy >= 0) {
                    if (!$destroyedOne) {


                        if ($newx == 7 || $newx == 0 || $newy == 7 || $newy == 0) {//don't try to beat border pieces
                            break;
                        } else if ($boardState[$newx][$newy] * $turnMultiplier < 0 && $boardState[$newx + $dx][$newy + $dy] == 0
                            && $boardState[$newx][$newy] != 66) {//found to kill
                            if ($currDx == 0) {//direct call
                                $destroyedOne = true;
                            } else {//recursive call
                                $beatPos[] = array("row" => $newx, "col" => $newy);//when found 1 secondary beat option return right away
                                return $beatPos;
                            }

                        } else if ($boardState[$newx][$newy] * $turnMultiplier < 0 && ($boardState[$newx + $dx][$newy + $dy] * $turnMultiplier < 0
                                || $boardState[$newx + $dx][$newy + $dy] == 66)) {//two enemy pieces stop us
                            break;//if cannot beat in this direction!
                        } else if ($boardState[$newx][$newy] == 66 || $boardState[$newx][$newy] * $turnMultiplier > 0) {//can't jump over destroyed or own pieces
                            break;
                        }
                    } else {
                        if ($boardState[$newx][$newy] != 0) {
                            break;

                        }
                        $secondaryBeatOpt = $this->getDamkaBeatOptions($newx, $newy, $boardState, $dx, $dy, $turnMultiplier);
                        if (count($secondaryBeatOpt) > 0) {
                            $beatPos[] = array("row" => $newx, "col" => $newy);
                            $canBeatSnd = true;
                        } else {
                            $tempPos[] = array("row" => $newx, "col" => $newy);
                        }
                    }

                    $newx = $newx + $dx;
                    $newy = $newy + $dy;
                }//end while


                if ($destroyedOne && !$canBeatSnd) {
                    $beatPos = array_merge($beatPos, $tempPos); //attach tempPos to the end of beatPos
                }
            }
        }
        return $beatPos;
    }


    private function getCheckerGoOptions($row, $col, &$boardState, $turnMultiplier)
    {


        $goPos = [];
        //players 1 and 2 go to different directions, but columns same stuff
        if ($row - $turnMultiplier < 8 && $row - $turnMultiplier >= 0 && $col - 1 < 8 && $col - 1 >= 0//TODO attached to 8x8 checkers
            && $boardState[$row - $turnMultiplier][$col - 1] == 0) {//usergame.turnMultiplier!

            $goPos[] = array("row" => $row - $turnMultiplier, "col" => $col - 1);
        }
        if ($row - $turnMultiplier < 8 && $row - $turnMultiplier >= 0 && $col + 1 < 8 && $col + 1 >= 0
            && $boardState[$row - $turnMultiplier][$col + 1] == 0) {//usergame.turnMultiplier!!
            $goPos[] = array("row" => $row - $turnMultiplier, "col" => $col + 1);
        }

        return $goPos;

    }


    private function getDamkaGoOptions($row, $col, &$boardState)
    {
        $goPos = [];
        for ($dx = -1; $dx <= 1; $dx = $dx + 2) {
            for ($dy = -1; $dy <= 1; $dy = $dy + 2) {

                $newx = $row + $dx;
                $newy = $col + $dy;
                while ($newx < 8 && $newx >= 0 && $newy < 8 && $newy >= 0 && $boardState[$newx][$newy] == 0) {

                    $goPos[] = array("row" => $newx, "col" => $newy);

                    $newx = $newx + $dx;
                    $newy = $newy + $dy;
                }
            }
        }

        return $goPos;
    }


}