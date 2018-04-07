<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Util\GamesManager;
use App\Util\MessageTypes;
use App\Util\RoomCategories;



//adopted from https://gist.github.com/Mevrael/6855dd47d45fa34ee7161c8e0d2d0e88
class WebSocketController
{


    protected $data = [];

    protected $currentClient = null;

    protected $otherClients = [];
    public function __construct(Request $request)
    {

        $this->data = $request->get('data');
        $this->currentClient = $request->get('current_client');
        $this->otherClients = $request->get('other_clients');



    }
    public function sendToOthers(array $data) {
        foreach ($this->otherClients as $client) {
            $client->send(json_encode($data));
        }
    }
    public function onOpen(Request $request)
    {
        if (Auth::check()) {
            return response()->json(['userId' => Auth::id()], 200);
        }

        return response()->json(['message' => 'unauthorised'], 401);
    }



    public function onMessage(Request $request, GamesManager $gm)
    {

        if (!Auth::check()) {
            return response()->json(['message' => 'unauthorised'], 401);
        }

        //print_r($this->data->msgType);
        $msgType = $this->data->msgType;
        if ($msgType == MessageTypes::JOIN_ROOM){
            $category = $this->data->roomCategory;
            if ($category == RoomCategories::TABLE_64_ROOM || $category == RoomCategories::TABLE_100_ROOM){
                $games = $gm->findGamesByCategory($category);

                return response()->json(['room' => $category, 'games'=>$games], 200);
            }
            else if ($category == RoomCategories::GAME_ROOM){

                $targetGame = $gm->findGameInWhichUserParticipates(Auth::id());

                if ($targetGame != null){

                    return response()->json(['room' => $targetGame->gameId, 'games'=>[$targetGame]], 200);
                }
                else{
                    return response()->json(['message' => 'player has to join game to join socket room!'], 403);//!!! properly handle this?
                }
            }
        }
        else if ($msgType == MessageTypes::BROADCAST_GAME_CREATED){
            $myId = Auth::id();
            $targetGame = $gm->findGameInWhichUserParticipates($myId);

            if ($targetGame != null){
                return response()->json(['game'=>$targetGame, 'creatorId' => $myId], 200);
            }
            else{
                return response()->json(['message' => 'game wasnt created! impossible happened'], 403);
            }
        }
        else if ($msgType == MessageTypes::BROADCAST_PLAYER_JOINED){

            $gameId = $this->data->gameId;
            $targetGame = $gm->findGameByGameId($gameId);
            if ($targetGame == null){
                return response()->json(['message' => 'game doesn\'t exist! impossible happened'], 403);
            }
            else{
                return response()->json(['game'=>$targetGame, 'playerId'=>Auth::id()], 200);
            }
        }
    }




    public function onClose(Request $request)
    {

    }
    public function onError(Request $request)
    {

    }


    //        $this->sendToOthers([
//            'type' => 'MESSAGE_RECEIVED',
//            'data' => [
//                'user' => Auth::user()->name,
//                'message' => $this->data->message,
//            ]
//        ]);
}