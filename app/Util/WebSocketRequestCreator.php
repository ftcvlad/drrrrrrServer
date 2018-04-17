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

        $messageId = json_decode($msg,true)['id'];
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

        //self , table64-self, table64+play64-self, play64,



        if ($messageType == MessageTypes::JOIN_ROOM_TABLES){//

            $this->clients[$con->resourceId]['room'] = $contAssocArray['room'];
            $this->sendToSelf($con, $contAssocArray['gameList'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::JOIN_ROOM_PLAY){//
            $this->clients[$con->resourceId]['room'] = $contAssocArray['room'];
            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::CREATE_GAME){
            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);
            $this->sendToTable64($contAssocArray['currentGame']['gameInfo'], MessageTypes::BROADCAST_GAME_CREATED, $con);
        }
        else if ($messageType == MessageTypes::PLAY_GAME || $messageType == MessageTypes::WATCH_GAME){
            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);

            $this->sendToTable64($contAssocArray['currentGame']['gameInfo'],
                MessageTypes::BROADCAST_PLAYER_JOINED_TO_TABLES, $con);

            $this->sendToPlay64($contAssocArray['currentGame']['gameInfo']['gameId'],$contAssocArray['currentGame'],
                MessageTypes::BROADCAST_PLAYER_JOINED_TO_TABLE, $con);
        }
        else if ($messageType == MessageTypes::USER_PICK){
            $this->sendToSelf($con, $contAssocArray['gameState'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::USER_MOVE){
            $boardChanged = $contAssocArray['boardChanged'];

            $this->sendToSelf($con, $contAssocArray['gameState'], $messageType, $messageId);
            if ($boardChanged){//moved checker
                $this->sendToPlay64($contAssocArray['gameId'],$contAssocArray['gameState'], $messageType, $con);
            }

        }
        else if ($messageType == MessageTypes::SEND_CHAT_MESSAGE){
            $this->sendToSelf($con, $contAssocArray, $messageType, $messageId);
            $this->sendToPlay64($contAssocArray['gameId'],$contAssocArray, $messageType, $con);
        }




    }

    private function sendToTable64(&$data, $messageType, $con){
        foreach ($this->clients as $client){
            if ($client['room'] == RoomCategories::TABLE_64_ROOM  && $client['conn'] != $con){//but self!
                $client['conn']->send(json_encode(['servMessType'=>$messageType,
                    'data' => $data,
                    'status'=>200]));
            }
        }
    }


    private function sendToSelf(&$con, &$data, $messageType, $messageId){
        $con->send(json_encode(['servMessType'=>$messageType, 'data' => $data, 'status'=>200, 'id'=>$messageId]));
    }

    private function sendToPlay64($gameId, &$data, $messageType, $con){
        foreach ($this->clients as $client){
            if ($client['room'] == $gameId  && $client['conn'] != $con){
                $client['conn']->send(json_encode(['servMessType'=>$messageType,
                    'data' => $data,
                    'status'=>200]));
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


