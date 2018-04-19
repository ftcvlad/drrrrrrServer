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
        DB::table('game_result')->insert(
            ['match_result' => $matchResult,
                'plays_white' => $playsWhite,
                'rating_after'=>$initiatorRatingAfter,
                'opponent_id'=>$opponentId,
                'user_id'=>$initiatorId,
                ]
        );

        DB::table('users')
            ->where('id', $initiatorId)
            ->update(['rating' => $initiatorRatingAfter]);

        //update opponent results
        DB::table('game_result')->insert(
            ['match_result' => 2-$matchResult,
                'plays_white' => !$playsWhite,
                'rating_after'=>$oppRatingAfter,
                'opponent_id'=>$initiatorId,
                'user_id'=>$opponentId,
            ]
        );

        DB::table('users')
            ->where('id', $opponentId)
            ->update(['rating' => $oppRatingAfter]);


        $initiatorRes = array("ratingBefore"=>$initiatorRatingBefore, "ratingAfter"=>$initiatorRatingAfter, "username"=>$initiator->email);
        $oppRes = array("ratingBefore"=>$oppRatingBefore, "ratingAfter"=>$oppRatingAfter, "username"=>$opponent->email);

        if ($playsWhite){
            return [$initiatorRes, $oppRes ];
        }
        else{
            return [$oppRes, $initiatorRes ];
        }


    }


    public function getPlayerWinDrawLoseStatistics($userId){


        $resultAggregates = DB::table('game_result')
            ->select('match_result', DB::raw('count(*) as total'))
            ->where("user_id", $userId)
            ->groupBy('match_result')
            ->orderBy('match_result')
            ->get();

        $losses = 0;
        $draws = 0;
        $wins = 0;

        foreach ($resultAggregates as $ra) {
            if ($ra->match_result == 0){
                $losses = $ra->total;
            }
            else if ($ra->match_result == 1){
                $draws = $ra->total;
            }
            else if ($ra->match_result == 2){
                $wins = $ra->total;
            }
        }

        Log::info($losses." ".$draws." ".$wins);


        return array("losses"=>$losses, "draws"=>$draws, "wins"=>$wins);

    }


}