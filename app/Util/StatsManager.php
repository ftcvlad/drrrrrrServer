<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 18/04/2018
 * Time: 17:19
 */

namespace App\Util;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Util\GamesManager;
class StatsManager
{

    const K = 25;//K-factor

    //match result from the perspective of user!!!
    private function calculateEloRating($userRating, $opponentRating, $matchResult){

        $userR = pow(10, $userRating/400);
        $oppR =  pow(10, $opponentRating/400);

        $expUserScore = $userR/($userR+$oppR);
        $actualUserScore = $matchResult / 2;


        $userNewRating = round(max($userRating + StatsManager::K *($actualUserScore - $expUserScore ), 0));
        return $userNewRating;

    }

    public function saveGameResults($initiatorId, $opponentId, $matchResult, $playsWhite){


        $initiator = DB::table('users')->where('id', $initiatorId)->first();
        $opponent = DB::table('users')->where('id', $opponentId)->first();

        $initiatorRatingBefore = $initiator->rating;
        $oppRatingBefore = $opponent->rating;

        $initiatorRatingAfter = $this->calculateEloRating($initiatorRatingBefore, $oppRatingBefore, $matchResult);
        $oppRatingAfter = $this->calculateEloRating($oppRatingBefore, $initiatorRatingBefore, 2-$matchResult);

        //update initiator results
        $resId = DB::table('game_result')->insertGetId(
            ['match_result' => $matchResult,
                'plays_white' => $playsWhite,
                'u_rating_before'=>$initiatorRatingBefore,
                'u_rating_after'=>$initiatorRatingAfter,
                'o_rating_before'=>$oppRatingBefore,
                'o_rating_after'=>$oppRatingAfter,
                'opponent_id'=>$opponentId,
                'user_id'=>$initiatorId,
                ]
        );

        DB::table('users')
            ->where('id', $initiatorId)
            ->update(['rating' => $initiatorRatingAfter]);


        DB::table('users')
            ->where('id', $opponentId)
            ->update(['rating' => $oppRatingAfter]);

//TODO store this resId on server. or somehow else
        $initiatorRes = array("resId"=>$resId, "userId"=>$initiatorId, "ratingBefore"=>$initiatorRatingBefore, "ratingAfter"=>$initiatorRatingAfter, "username"=>$initiator->email);
        $oppRes = array("resId"=>$resId, "userId"=>$opponentId, "ratingBefore"=>$oppRatingBefore, "ratingAfter"=>$oppRatingAfter, "username"=>$opponent->email);

        if ($playsWhite){
            return [$initiatorRes, $oppRes ];
        }
        else{
            return [$oppRes, $initiatorRes ];
        }


    }


    public function getPlayerWinDrawLoseStatistics($userId){


        $resultAggregates = DB::table('game_result')
            ->select('match_result', 'user_id', 'opponent_id')
            ->where("user_id", $userId)
            ->orWhere("opponent_id", $userId)
            ->get();

        $losses = 0;
        $draws = 0;
        $wins = 0;

        foreach ($resultAggregates as $ra) {
            if ($ra->match_result == 1)$draws++;
            if ($ra->user_id == $userId){
                if ($ra->match_result == 0)$losses++;
                if ($ra->match_result == 2)$wins++;
            }
            else if ($ra->opponent_id == $userId){
                if ($ra->match_result == 0)$wins++;
                if ($ra->match_result == 2)$losses++;
            }
        }

        return array("losses"=>$losses, "draws"=>$draws, "wins"=>$wins);

    }

    public function saveGame($gameId, $userId, $resultId, $description){

        $gm = new GamesManager();
        $game = $gm->getGame($gameId);
        $savedGameId = null;

        $savedGame = DB::table("saved_game")->where('result_id', $resultId)->first();
        if ($savedGame != null){
            $savedGameId = $savedGame->id;
        }
        else {

            $savedGameId = DB::table("saved_game")->insertGetId([
                'result_id'=>$resultId,
                'moves'=>serialize($game->gameState->moves),
                'board_state'=>serialize($game->gameState->boardState)
            ]);

        }
        try {
            DB::table("saved_game_user")->insert([
                'user_id'=>$userId,
                'saved_game_id' => $savedGameId,
                'description' => $description
            ]);
        } catch(\Illuminate\Database\QueryException $ex){
            abort(403, "this user has already saved this game!");
        }


    }

    public function getSavedGames($userId){





        $games = DB::table("saved_game_user")
                ->join('saved_game', 'saved_game.id', '=', 'saved_game_user.saved_game_id')
                ->join('game_result', 'game_result.id', '=', 'saved_game.result_id')
                ->where('saved_game_user.user_id', $userId)
                ->get();


        foreach ($games as $game){

            $game->moves = unserialize($game->moves);
            $game->board_state = unserialize($game->board_state);
        }
        //unserialize?
        return $games;
    }


}