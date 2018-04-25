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
use App\User;

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


        $initiator = User::where('id', '=', $initiatorId)->firstOrFail();
        $opponent = User::where('id', '=', $opponentId)->firstOrFail();

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
                "created_at" =>  time()
                ]
        );



        if ($matchResult == 0){
            $initiator->losses+=1;
            $opponent->wins+=1;
        }
        else if ($matchResult == 1){
            $initiator->draws+=1;
            $opponent->draws+=1;
        }
        else if ($matchResult == 2){
            $initiator->wins+=1;
            $opponent->losses+=1;
        }

        $initiator->rating = $initiatorRatingAfter;
        $opponent->rating = $oppRatingAfter;

        $initiator->save();
        $opponent->save();


        //TODO store this resId on server. or somehow else
        $initiatorRes = array("resId"=>$resId,
                                "userId"=>$initiatorId,
                                "ratingBefore"=>$initiatorRatingBefore,
                                "ratingAfter"=>$initiatorRatingAfter,
                                "username"=>$initiator->email,
                                "stats"=>array("wins"=>$initiator->wins,
                                                "losses"=>$initiator->losses,
                                                "draws"=>$initiator->draws));


        $oppRes = array("resId"=>$resId,
                        "userId"=>$opponentId,
                        "ratingBefore"=>$oppRatingBefore,
                        "ratingAfter"=>$oppRatingAfter,
                        "username"=>$opponent->email,
                        "stats"=>array("wins"=>$opponent->wins,
                                        "losses"=>$opponent->losses,
                                        "draws"=>$opponent->draws));


        if ($playsWhite){
            return [$initiatorRes, $oppRes ];
        }
        else{
            return [$oppRes, $initiatorRes ];
        }


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
                ->select('users.email as u_username', 'opponent.email as o_username',
                    'description', 'moves', 'board_state','match_result', 'plays_white',
                    'o_rating_after', 'o_rating_before', 'u_rating_after', 'u_rating_before',
                    'game_result.created_at', 'game_result.user_id', 'game_result.opponent_id')


                ->join('saved_game', 'saved_game.id', '=', 'saved_game_user.saved_game_id')
                ->join('game_result', 'game_result.id', '=', 'saved_game.result_id')
                ->join('users', 'users.id', '=', 'game_result.user_id')
                ->join('users as opponent', 'opponent.id', '=', 'game_result.opponent_id')
                ->where('saved_game_user.user_id', $userId)
                ->orderBy('game_result.created_at', 'desc')
                ->get();


        foreach ($games as $game){

            $game->moves = unserialize($game->moves);
            $game->board_state = unserialize($game->board_state);
        }
        //unserialize?
        return $games;
    }


}