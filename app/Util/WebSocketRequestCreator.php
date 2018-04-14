<?php
namespace App\Util;

require __DIR__ . '/../../vendor/autoload.php';


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Dflydev\FigCookies\Cookies;

use Illuminate\Support\Facades\Log;
use App\Util\MessageTypes;
use App\Util\RoomCategories;

//adopted from https://gist.github.com/Mevrael/6855dd47d45fa34ee7161c8e0d2d0e88
class WebSocketRequestCreator implements MessageComponentInterface
{
    protected $clients;
    public function __construct() {
        echo 'Creating app...' . PHP_EOL;
        $this->clients = array();
    }
    protected function handleLaravelRequest(ConnectionInterface $con, $route, $data = null)
    {


        $params = [];
        if ($data !== null) {
            if (is_string($data)) {
                $params = ['data' => json_decode($data)];
            } else {
                $params = ['data' => $data];
            }
        }


        $wsrequest = $con->httpRequest ;
        $cookies = Cookies::fromRequest($wsrequest)->getAll();
        $allCookies = array();
        foreach ($cookies as $cookie){
            $allCookies[$cookie->getName()] = $cookie->getValue();
        }



        $app = require __DIR__ . '/../../bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

        $request = \Illuminate\Http\Request::create($route, 'GET', $params, $allCookies);

        // $con->send(json_encode(['ADDED TO REQUEST?!' => json_encode($request->cookies->all())]));

        $response = $kernel->handle($request);

        return $response;



    }
    public function onOpen(ConnectionInterface $con)
    {

        $response = $this->handleLaravelRequest($con, '/websocket/open');


        if ($response->status() == 401){//user has not supplied session cookie
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR ,'msg' => 'not authorised!', 'status'=>401]));
            $con->close();
            return;
        }

        $contAssocArray = json_decode($response->getContent(),true);


        $currentUserId = $contAssocArray['userId'];//prevent >1 socket connections from 1 user
        foreach ($this->clients as $client){
            if ($client['userId'] == $currentUserId ){
                $con->send(json_encode(['servMessType'=>MessageTypes::ERROR, 'msg' => 'user already has open connection', 'status'=>403]));
                $con->close();
                return;
            }
        }



        if (!array_key_exists($con->resourceId, $this->clients)){
            $this->clients[$con->resourceId] = array('conn'=>$con, 'room'=>'', 'userId'=>$currentUserId);
        }


    }




    public function onMessage(ConnectionInterface $con, $msg)
    {

        $messageType = json_decode($msg,true)['msgType'];
        $response = $this->handleLaravelRequest($con,  "/websocket/message/".$messageType, $msg);
        $contAssocArray = json_decode($response->getContent(),true);


        if ($response->status() == 401){//unauthorised
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR, 'message' => $contAssocArray['message'], 'status' =>401]));
            $con->close();
            return;
        }
        else if ($response->status() == 403){//authorised, but cannot send this message (e.g. when not in game and trying to join game room)
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR, 'message' => $contAssocArray['message'], 'status'=>403]));
            return;
        }
        else if ($response->status() == 204){//nothing to send as content
            return;
        }

        //to

        // print_r($contAssocArray['games']);

        if ($messageType == MessageTypes::JOIN_ROOM){

            $roomToJoin = $contAssocArray['room'];
            $this->clients[$con->resourceId]['room'] = $roomToJoin;

            //return most recent relevant game info

            $con->send(json_encode(['servMessType'=>MessageTypes::JOINED_ROOM, 'room'=>$roomToJoin, 'data' => $contAssocArray['games'], 'status'=>200]));
        }
        else if ($messageType == MessageTypes::BROADCAST_GAME_CREATED){
            $myId = $contAssocArray['creatorId'];
            foreach ($this->clients as $client){
                if ($client['room'] == RoomCategories::TABLE_64_ROOM && $client['userId'] != $myId ){
                    $client['conn']->send(json_encode(['servMessType'=>MessageTypes::BROADCAST_GAME_CREATED,
                        'data' => $contAssocArray['game'],
                        'status'=>200]));
                }
            }
        }
        else if ($messageType == MessageTypes::BROADCAST_PLAYER_JOINED){//<-- problem here!!! :)


            $myId = $contAssocArray['playerId'];
            $game = $contAssocArray['game'];

            foreach ($this->clients as $client){

                if ($client['userId'] != $myId &&
                    ($client['room'] == RoomCategories::TABLE_64_ROOM || $client['room'] == $game['gameId']   )){

                    $client['conn']->send(json_encode(['servMessType'=>MessageTypes::BROADCAST_PLAYER_JOINED,
                        'data' => $contAssocArray['game'],
                        'status'=>200]));
                }
            }

        }
        else if ($messageType == MessageTypes::USER_PICK){
            $myId = $contAssocArray['playerId'];
            foreach ($this->clients as $client){
                if ($client['userId'] == $myId){
                    $client['conn']->send(json_encode(['servMessType'=>MessageTypes::USER_PICKED,
                        'data' => $contAssocArray['game'],
                        'status'=>200]));
                    break;
                }
            }


        }
        else if ($messageType == MessageTypes::USER_MOVE){
            $myId = $contAssocArray['playerId'];
            $boardChanged = $contAssocArray['boardChanged'];


            if (!$boardChanged){//unpicked piece
                foreach ($this->clients as $client){
                    if ($client['userId'] == $myId){
                        $client['conn']->send(json_encode(['servMessType'=>MessageTypes::USER_PICKED,
                            'data' => $contAssocArray['game'],
                            'status'=>200]));
                        break;
                    }
                }
            }
            else if ($boardChanged){//moved checker
                foreach ($this->clients as $client){

                    if ($client['room'] == $contAssocArray['game']['gameId']){
                        $client['conn']->send(json_encode(['servMessType'=>MessageTypes::USER_MOVED,
                            'data' => $contAssocArray['game'],
                            'status'=>200]));
                    }
                }
            }

        }
        else if ($messageType == MessageTypes::SEND_CHAT_MESSAGE){
            $gameId = $contAssocArray['gameId'];
            foreach ($this->clients as $client){
                if ($client['room'] == $gameId){
                    $client['conn']->send(json_encode(['servMessType'=>MessageTypes::SEND_CHAT_MESSAGE,
                        'data' => $contAssocArray['msg'],
                        'gameId'=>$gameId,
                        'status'=>200]));
                }

            }
        }






    }






    public function onClose(ConnectionInterface $con)
    {
        //$this->handleLaravelRequest($con, '/websocket/close');
       //$this->clients->detach($con);

        unset($this->clients[$con->resourceId]);
    }
    public function onError(ConnectionInterface $con, \Exception $e)
    {

        $con->send(json_encode(['error' => $e->getMessage()]));
        //$this->handleLaravelRequest($con, '/websocket/error');
       // echo 'Error: ' . $e->getMessage() . PHP_EOL;
       // $con->close();
    }
}


