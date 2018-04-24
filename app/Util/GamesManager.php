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
                return $participant;
            }
        }
        return null;
    }


    public function exitGame($gameId, $userId)
    {
        $game = $this->getGame($gameId);

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
                    $game->gameInfo->players[0]->playsWhite = true;
                }

                $wasGameGoing = $game->gameState->isGameGoing;
                if (!$wasGameGoing){
                    Cache::forever($gameId, serialize($game));
                    return array("isLastPerson" => false, "isPlayer"=>true, "wasGameGoing"=>false, "gameInfo"=>$game->gameInfo);
                }
                else{

                   $remainingPlayer =  $game->gameInfo->players[0];
                   $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);//could also swap time if 1st player leaves

                   $temp = $game->gameState->timeLeft[0];
                   $game->gameState->timeLeft[0] =  $game->gameState->timeLeft[1];
                   $game->gameState->timeLeft[1] = $temp;

                   $gameResult = $this->finishGame($game, $userId, $remainingPlayer->id, $leavingPlayer->playsWhite, 0, "Left" );


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

    public function surrender($gameId, $userId){
        $game = $this->getGame($gameId);

        if ($game->gameState->isGameGoing == false){
            abort(403, "impossible happened: surrenderer from non-going game");
        }

        $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);
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

        return array("gameInfo"=>$game->gameInfo, "gameResult"=>$gameResult);


    }


    public function suggestDraw($gameId, $userId){
        $game = $this->getGame($gameId);

        if ($game->gameState->isGameGoing == false){
            abort(403, "impossible happened: draw in non-going game");
        }


        foreach($game->gameInfo->players as $player){
            if ($player->id == $userId){
                $player->currentStatus = PlayerStatuses::suggestingDraw;
            }
            else{
                $player->currentStatus = PlayerStatuses::resolvingDrawOffer;
            }
        }

        Cache::forever($gameId, serialize($game));
        return $game->gameInfo;
    }




    public function cancelDrawOffer($gameId, $userId){
        $game = $this->getGame($gameId);
        if ($game->gameState->isGameGoing == false){
            abort(403, "impossible happened: cancel draw in non-going game");
        }

        //TODO make sure userId in game
        foreach($game->gameInfo->players as $player){
            if ($player->currentStatus == PlayerStatuses::suggestingDraw
                || $player->currentStatus == PlayerStatuses::resolvingDrawOffer){//'make sure' check
                $player->currentStatus = PlayerStatuses::playing;
            }
        }

        Cache::forever($gameId, serialize($game));
        return $game->gameInfo;

    }

    public function respondDrawOffer($gameId, $userId, $decision){
        $game = $this->getGame($gameId);
        if ($game->gameState->isGameGoing == false){
            abort(403, "impossible happened: responded draw in non-going game");
        }
        //TODO make sure userId in game (for decline!)
        if ($decision){//accepts
            $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);
            if ($game->gameInfo->players[0]->id == $userId ){
                $gameResult = $this->finishGame($game, $userId, $game->gameInfo->players[1]->id,
                    true, 1, "Draw" );
            }
            else if ($game->gameInfo->players[1]->id == $userId){
                $gameResult = $this->finishGame($game, $userId, $game->gameInfo->players[0]->id,
                    false, 1, "Draw" );
            }
            else{
                abort(403, "impossible happened: draw acceptor is not among players");
            }

            $game->gameInfo->players[0]->currentStatus = PlayerStatuses::confirming;
            $game->gameInfo->players[1]->currentStatus = PlayerStatuses::confirming;

            Cache::forever($gameId, serialize($game));

            return array("drawAccepted" => true, "gameInfo"=>$game->gameInfo, "gameResult"=>$gameResult);


        }
        else {//declines
            foreach($game->gameInfo->players as $player){
                if ($player->currentStatus == PlayerStatuses::suggestingDraw
                        || $player->currentStatus == PlayerStatuses::resolvingDrawOffer){//'make sure' check
                    $player->currentStatus = PlayerStatuses::playing;
                }
            }

            Cache::forever($gameId, serialize($game));
            return array("gameInfo"=>$game->gameInfo, "drawAccepted" => false);
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
    public function createGame($userId, $options)
    {
        $this->ensureUserNotInGame($userId);

        $gameIds = Cache::get('gameIds', []);


        $uuid = $this->generateUuid($gameIds);


        $user = Auth::user();
        $player = new Player($user->email, $userId, PlayerStatuses::waiting, $user->rating, true);

        $createdGame = new Game($uuid, $player, $options);


        $gameIds[] = $uuid;
        Cache::forever('gameIds', $gameIds);
        Cache::forever($uuid, serialize($createdGame));


        return $createdGame;
    }




    public function handleTimeIsUp($gameId){
        $game = $this->getGame($gameId);
        $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);

        if ($game->gameState->isGameGoing && count($game->gameInfo->players) == 2){
            if ($game->gameState->timeLeft[0]<=0){
                $loser = $game->gameInfo->players[0];
                $winner = $game->gameInfo->players[1];
            }
            else if ($game->gameState->timeLeft[1]<=0){
                $winner = $game->gameInfo->players[0];
                $loser = $game->gameInfo->players[1];
            }
            else {
                //false request
                abort(403, "impossible happened: client says time is up, but it is not!");
            }


            $gameResult = $this->finishGame($game, $loser->id, $winner->id,
                $loser->playsWhite, 0, "Time is up" );
            $game->gameInfo->players[0]->currentStatus = PlayerStatuses::confirming;
            $game->gameInfo->players[1]->currentStatus = PlayerStatuses::confirming;

            Cache::forever($gameId, serialize($game));

            return array("gameInfo"=>$game->gameInfo, "gameResult"=>$gameResult);

        }
        return null;



    }




    public function playGame($gameId, $playerId)
    {
        $this->ensureUserNotInGame($playerId);

        $game = $this->getGame($gameId);

        $nOfPlayersBefore = count($game->gameInfo->players);
        if ($nOfPlayersBefore > 1) {
            abort(403, 'game is full');
        }
        else{
            $user = Auth::user();
            if ($nOfPlayersBefore == 0){
                $game->gameInfo->players[] = new Player($user->email, $playerId, PlayerStatuses::waiting, $user->rating, true);
            }
            else if ($nOfPlayersBefore == 1){
                $game->gameInfo->players[] = new Player($user->email, $playerId, PlayerStatuses::ready, $user->rating, false);
                $game->gameInfo->players[0]->currentStatus = PlayerStatuses::confirming;
            }

        }



        Cache::forever($gameId, serialize($game));
        return $game;


    }

    public function confirmPlaying($gameId, $playerId){

        $game = $this->getGame($gameId);

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

            $game->gameState = new GameState($game->gameInfo->timeReserve);//reset game state
            $game->gameState->isGameGoing = true;
            $game->gameState->gameStartTime = time();
            Cache::forever($gameId, serialize($game));
            return array("gameStarted" => true, "gameInfo"=>$game->gameInfo, "gameState"=>$game->gameState);
        }
        else{
            Cache::forever($gameId, serialize($game));
            return array("gameStarted" => false, "gameInfo"=>$game->gameInfo);
        }





    }


    public function watchGame($gameId, $watcherId)
    {
        $this->ensureUserNotInGame($watcherId);

        $game = $this->getGame($gameId);

        $watcher = new Watcher(Auth::user()->email, $watcherId);
        $game->gameInfo->watchers[] = $watcher;

        $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);

        Cache::forever($gameId, serialize($game));
        return $game;


    }


    //************************HELPER functions *************

    private function ensureUserNotInGame($userId)
    {//!!! make sure user is not in some game already
        $currentGame = $this->findGameInWhichUserParticipates($userId);
        if ($currentGame != null) {
            abort(403, 'cannot join > 1 game');
        }
    }

    public function getGame($gameId){
        $gameStr = Cache::get($gameId, null);

        if ($gameStr == null) {
            abort(403, 'game doesn\'t exist ');
        } else {
            $game = unserialize($gameStr);
        }

        return $game;
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

        $moveResult = $this->userMove($row, $col, $userId, $game);//boardChanged, gameState, opponentLost

        if ($moveResult != null) {



            if ($moveResult['boardChanged'] && $moveResult['opponentLost']){
                $gameResult = $this->finishGame($game, $userId,
                    $game->gameInfo->players[1-$game->gameState->currentPlayer]->id,
                    $game->gameState->currentPlayer == 0, 2, "Won"
                    );
                $moveResult['gameResult'] = $gameResult;

                foreach($game->gameInfo->players as $player){
                    $player->currentStatus = PlayerStatuses::confirming;
                }

                $moveResult['gameInfo'] = $game->gameInfo;

            }


            Cache::forever($gameId, serialize($game));
            return $moveResult;
        }
        return null;
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

                            $opponentLost = $this->afterTurn($gameState, $turnMultiplier, $gameInfo->timeReserve);

                            return array("boardChanged" => true, "gameState" => $gameState, "opponentLost"=>$opponentLost);

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


                                $opponentLost = $this->afterTurn($gameState, $turnMultiplier, $gameInfo->timeReserve);

                                return array("boardChanged" => true, "gameState" => $gameState, "opponentLost"=>$opponentLost);
                            } else {//KILLED AND STILL MORE TO KILL
                                if ($gameState->moves[count($gameState->moves) - 1]["player"] != $gameState->currentPlayer) {//1st move (in sequence) by this player
                                    $gameState->moves[] = array("player" => $gameState->currentPlayer,//new move
                                        "finished" => false,
                                        "moveInfo" => [array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType)]);
                                } else {
                                    $gameState->moves[count($gameState->moves) - 1]["moveInfo"][] = array("prev" => $prevPos, "next" => $nextPos, "killed" => $killed, "prevType" => $prevType);//continuing move
                                }

                                $gameState->pickedChecker = [$nextPos["row"], $nextPos["col"]];


                                return array("boardChanged" => true, "gameState" => $gameState, "opponentLost"=>false);
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

    public function updatePlayerTimeLeft(&$game){
        if ($game->gameState->isGameGoing){
            $this->decreaseTimeLeft($game->gameState, $game->gameInfo->timeReserve);
            Cache::forever($game->gameInfo->gameId, serialize($game));
        }
    }

    private function decreaseTimeLeft(&$gameState, $timeReserve){

        if ($gameState->isGameGoing) {
            $totalPassedTime = $timeReserve * 2 - ($gameState->timeLeft[0] + $gameState->timeLeft[1]);

            $lastMoveTime = $gameState->gameStartTime + $totalPassedTime;
            $currentTime = time();

            $gameState->timeLeft[$gameState->currentPlayer] -= $currentTime - $lastMoveTime;
        }
    }

    private function afterTurn(&$gameState, $turnMultiplier, $timeReserve)
    {

        $this->decreaseTimeLeft($gameState, $timeReserve);

        $turnMultiplier *= -1;
        //check if enemy lost after my turn!
        $lost = $this->checkLost($gameState->boardState, $turnMultiplier);
        if ($lost) {
            return true;
        }
        else{

            //change turn
            $gameState->currentPlayer = $gameState->currentPlayer == 0 ? 1 : 0;
            return false;
        }




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