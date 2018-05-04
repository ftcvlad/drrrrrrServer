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

        $host = env("APP_URL");
        $request = \Illuminate\Http\Request::create($host.$route, 'GET', $params, $allCookies);

        // $con->send(json_encode(['ADDED TO REQUEST?!' => json_encode($request->cookies->all())]));

        $response = $kernel->handle($request);

        return $response;



    }
    public function onOpen(ConnectionInterface $con)
    {

        $response = $this->handleLaravelRequest($con, '/websocket/open');


        if ($response->status() == 401){//user has not supplied session cookie
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR ,'msg' => 'not authorised!', 'status'=>401]));
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
            $this->clients[$con->resourceId] = array('conn'=>$con, 'rooms'=>array("gameRoom"=>"", "tableRoom"=>""), 'userId'=>$currentUserId);
        }


    }




    public function onMessage(ConnectionInterface $con, $msg)
    {




        $messageId = json_decode($msg,true)['id'];
        $messageType = json_decode($msg,true)['msgType'];


        if ($messageType == MessageTypes::LEAVE_ROOM_TABLES) {
            $this->clients[$con->resourceId]['rooms']["tableRoom"] = "";
            $this->sendToSelf204($con, $messageType, $messageId);
            return;
        }



        $response = $this->handleLaravelRequest($con,  "/websocket/message/".$messageType, $msg);
        $contAssocArray = json_decode($response->getContent(),true);

        //TODO abort message is null. Modify Exceptions/Handler
        if ($response->status() == 401){//unauthorised
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR, 'message' => $contAssocArray['message'], 'status' =>401, 'id'=>$messageId]));
            $con->close();
            return;
        }
        else if ($response->status() == 403){//forbidden
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR, 'message' => $contAssocArray['message'], 'status'=>403, 'id'=>$messageId]));
            return;
        }
        else if ($response->status() == 409){//for userMove, userPick, failed timeIsUp. Request allowed by client application, could not process it, but it's fine
            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR,'status'=>409, 'id'=>$messageId]));
            return;
        }


//        else if ($response->status() == 404){//for userMove, userPick, failed timeIsUp. Request allowed by client application, could not process it, but it's fine
//            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR,'status'=>404, 'id'=>$messageId]));
//            return;
//        }
//        else if ($response->status() == 405){//for userMove, userPick, failed timeIsUp. Request allowed by client application, could not process it, but it's fine
//            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR,'status'=>405, 'id'=>$messageId]));
//            return;
//        }
//        else if ($response->status() == 406){//for userMove, userPick, failed timeIsUp. Request allowed by client application, could not process it, but it's fine
//            $con->send(json_encode(['servMessType'=>MessageTypes::ERROR,'status'=>406, 'id'=>$messageId]));
//            return;
//        }

        //self , table64-self, table64+play64-self, play64,



        if ($messageType == MessageTypes::JOIN_ROOM_TABLES){//++

            $this->clients[$con->resourceId]['rooms']["tableRoom"] = $contAssocArray['room'];
            $this->sendToSelf($con, $contAssocArray['gameList'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::JOIN_ROOM_PLAY){//++

            $gameId = $contAssocArray['currentGame']["gameInfo"]["gameId"];
            $this->clients[$con->resourceId]['rooms']["gameRoom"] = $contAssocArray['room'];
            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);

            $this->sendToPlay64($gameId,$contAssocArray['currentGame']['gameInfo'],
                MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_table, $con);

        }
        else if ($messageType == MessageTypes::CREATE_GAME){//++
            $gameId = $contAssocArray['currentGame']['gameInfo']['gameId'];
            $this->clients[$con->resourceId]['rooms']["gameRoom"] = $gameId;
            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);
            $this->sendToTable64($contAssocArray['currentGame']['gameInfo'], MessageTypes::BROADCAST_GAME_CREATED, $con);
        }
        else if ($messageType == MessageTypes::PLAY_GAME || $messageType == MessageTypes::WATCH_GAME){
            $gameId = $contAssocArray['currentGame']['gameInfo']['gameId'];
            $this->clients[$con->resourceId]['rooms']["gameRoom"] = $gameId;

            $this->sendToSelf($con, $contAssocArray['currentGame'], $messageType, $messageId);

            $this->sendToPlay64($gameId,$contAssocArray['currentGame']['gameInfo'],
                MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_table, $con);

                $this->sendToTable64($contAssocArray['currentGame']['gameInfo'],
                    MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_tables, $con);

        }
        else if ($messageType == MessageTypes::CONFIRM_PLAYING){//++

            $gameId = $contAssocArray['gameId'];
            $gameStarted = $contAssocArray['result']['gameStarted'];


            $this->sendToSelf($con, $contAssocArray['result']['gameInfo'], $messageType, $messageId);
            $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_CONFIRM_PLAYING, $con);

            if ($gameStarted){
                $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameState'],
                      MessageTypes::BROADCAST_GAME_STARTED);
            }
        }
        else if ($messageType == MessageTypes::USER_PICK){//++
            $this->sendToSelf($con, $contAssocArray['gameState'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::USER_MOVE){//++
            $boardChanged = $contAssocArray['result']['boardChanged'];
            $gameId = $contAssocArray['gameId'];

            $this->sendToSelf($con, $contAssocArray['result']['gameState'], $messageType, $messageId);
            if ($boardChanged){//moved checker
                $this->sendToPlay64($gameId,$contAssocArray['result']['gameState'], $messageType, $con);

                $opponentLost = $contAssocArray['result']['opponentLost'];
                if ($opponentLost){

                    //BROADCAST_SURRENDER just to send game info :)
                    $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameInfo'],
                        MessageTypes::BROADCAST_SURRENDER, $con);

                    $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameResult'],
                        MessageTypes::BROADCAST_GAME_FINISHED, $con);
                }

            }

        }
        else if ($messageType == MessageTypes::SEND_CHAT_MESSAGE){//++
            $this->sendToSelf($con, $contAssocArray, $messageType, $messageId);
            $this->sendToPlay64($contAssocArray['gameId'],$contAssocArray, $messageType, $con);
        }

        else if ($messageType == MessageTypes::EXIT_GAME){//++
            $isLastPerson = $contAssocArray['result']['isLastPerson'];
            $gameId = $contAssocArray['gameId'];

            $this->clients[$con->resourceId]['rooms']["gameRoom"] = "";
            $this->sendToSelf204($con, $messageType, $messageId);

            if ($isLastPerson){//game table removed
                $this->sendToTable64($gameId,MessageTypes::BROADCAST_TABLE_REMOVED, $con);
            }
            else{
                $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                    MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_table, $con);

                $this->sendToTable64($contAssocArray['result']['gameInfo'],
                    MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_tables, $con);

                $isPlayer = $contAssocArray['result']['isPlayer'];
                if ($isPlayer){

                    $wasGameGoing = $contAssocArray['result']['wasGameGoing'];
                    if ($wasGameGoing){
                        $this->sendToPlay64($gameId,$contAssocArray['result']['gameResult'],
                            MessageTypes::BROADCAST_GAME_FINISHED, $con);
                    }
                }
            }
        }
        else if ($messageType == MessageTypes::SURRENDER){//+++ surrender, broadcast_surrender change status. broadcast_game_finished changes gameState and gameResult
            $gameId = $contAssocArray['gameId'];

            $this->sendToSelf($con, $contAssocArray['result']['gameInfo'], $messageType, $messageId);
            $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_SURRENDER, $con);

            $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameResult'],
                MessageTypes::BROADCAST_GAME_FINISHED);



        }
        else if ($messageType == MessageTypes::SUGGEST_DRAW){//+++
            $gameId = $contAssocArray['gameId'];

            $this->sendToSelf($con, $contAssocArray['gameInfo'], $messageType, $messageId);
            $this->sendToPlay64($gameId,$contAssocArray['gameInfo'],
                MessageTypes::BROADCAST_SUGGEST_DRAW, $con);
        }
        else if ($messageType == MessageTypes::RESPOND_DRAW_OFFER){//+++
            $gameId = $contAssocArray['gameId'];

            $this->sendToSelf($con, $contAssocArray['result']['gameInfo'], $messageType, $messageId);
            $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_RESPOND_DRAW_OFFER, $con);

            if ($contAssocArray['result']['drawAccepted']){
                $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameResult'],
                    MessageTypes::BROADCAST_GAME_FINISHED, $con);
            }
        }
        else if ($messageType == MessageTypes::CANCEL_DRAW_OFFER){
            $gameId = $contAssocArray['gameId'];

            $this->sendToSelf($con, $contAssocArray['gameInfo'], $messageType, $messageId);
            $this->sendToPlay64($gameId,$contAssocArray['gameInfo'],
                MessageTypes::BROADCAST_CANCEL_DRAW_OFFER, $con);


        }
        else if ($messageType == MessageTypes::TIME_IS_UP){
            $gameId = $contAssocArray['gameId'];


            //BROADCAST_SURRENDER just to send game info :)
            $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_SURRENDER);

            $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameResult'],
                MessageTypes::BROADCAST_GAME_FINISHED);

        }
        else if ($messageType == MessageTypes::UPDATE_TIME_LEFT){
            $this->sendToSelf($con, $contAssocArray['gameState'], $messageType, $messageId);
        }
        else if ($messageType == MessageTypes::DROP_OPPONENT){
            $gameId = $contAssocArray['gameId'];



            $this->sendToSelf($con, $contAssocArray['result']['gameInfo'], $messageType, $messageId);


            $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_table, $con);
            $this->sendToTable64($contAssocArray['result']['gameInfo'],
                MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_tables, $con);


            $this->sendToAllPlay64($gameId,$contAssocArray['result']['gameResult'],
                MessageTypes::BROADCAST_GAME_FINISHED);
        }




    }

    private function sendToTable64(&$data, $messageType, $con){
        foreach ($this->clients as $client){
            if ($client['rooms']['tableRoom'] == RoomCategories::TABLE_64_ROOM  && $client['conn'] != $con){//but self!
                $client['conn']->send(json_encode(['servMessType'=>$messageType,
                    'data' => $data,
                    'status'=>200]));
            }
        }
    }

    private function sendToSelf204(&$con, $messageType, $messageId){
        $con->send(json_encode(['servMessType'=>$messageType, 'status'=>204, 'id'=>$messageId]));
    }

    private function sendToSelf(&$con, &$data, $messageType, $messageId){
        $con->send(json_encode(['servMessType'=>$messageType, 'data' => $data, 'status'=>200, 'id'=>$messageId]));
    }

    private function sendToPlay64($gameId, &$data, $messageType, $con){//except self
        foreach ($this->clients as $client){
            if ($client['rooms']['gameRoom'] == $gameId  && $client['conn'] != $con){
                $client['conn']->send(json_encode(['servMessType'=>$messageType,
                    'data' => $data,
                    'status'=>200]));
            }

        }
    }

    private function sendToAllPlay64($gameId, &$data, $messageType){//including self
        foreach ($this->clients as $client){
            if ($client['rooms']['gameRoom'] == $gameId){
                $client['conn']->send(json_encode(['servMessType'=>$messageType,
                    'data' => $data,
                    'status'=>200]));
            }

        }
    }





    public function onClose(ConnectionInterface $con)
    {
        unset($this->clients[$con->resourceId]);

        $response = $this->handleLaravelRequest($con, '/websocket/close');
        if ($response == null){
            return;
        }

        $contAssocArray = json_decode($response->getContent(),true);

        $result = $contAssocArray["result"];
        $gameId = $result["gameId"];


        //$result = array("inGame"=>true, "isLastPerson" => false, "gameInfo"=>$game->gameInfo, "left"=>false );
        if (!$result["inGame"]){
            return;
        }
        else{
            if ($result["isLastPerson"]){
                $this->sendToTable64($gameId,MessageTypes::BROADCAST_TABLE_REMOVED, $con);
            }
            else {
                $this->sendToPlay64($gameId,$contAssocArray['result']['gameInfo'],
                    MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_table, $con);

                if ($result["left"]){
                    $this->sendToTable64($contAssocArray['result']['gameInfo'],
                        MessageTypes::BROADCAST_PARTICIPANTS_CHANGED_to_tables, $con);
                }

            }
        }




    }
    public function onError(ConnectionInterface $con, \Exception $e)
    {

        $con->send(json_encode(['error' => $e->getMessage()]));
        //$this->handleLaravelRequest($con, '/websocket/error');
       // echo 'Error: ' . $e->getMessage() . PHP_EOL;
       // $con->close();
    }
}


