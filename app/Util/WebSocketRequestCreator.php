<?php
namespace App\Util;

require __DIR__ . '/../../vendor/autoload.php';


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Dflydev\FigCookies\Cookies;

use Illuminate\Support\Facades\Log;

//adopted from https://gist.github.com/Mevrael/6855dd47d45fa34ee7161c8e0d2d0e88
class WebSocketRequestCreator implements MessageComponentInterface
{
    protected $clients;
    public function __construct() {
        echo 'Creating app...' . PHP_EOL;
        $this->clients = new \SplObjectStorage;
    }
    protected function handleLaravelRequest(ConnectionInterface $con, $route, $data = null)
    {


        /**
         * @var \GuzzleHttp\Psr7\Request $wsrequest
         * @var \Illuminate\Http\Response $response
         */
        $params = [
            'connection' => $con,
            'other_clients' => [],
        ];
        if ($data !== null) {
            if (is_string($data)) {
                $params = ['data' => json_decode($data)];
            } else {
                $params = ['data' => $data];
            }
        }
        foreach ($this->clients as $client) {
            if ($con != $client) {
                $params['other_clients'][] = $client;
            } else {
                $params['current_client'] = $client;
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


//        $controllerResult = $response->getContent();
//        $kernel->terminate($request, $response);
//        return json_encode($controllerResult);


        return $response;



    }
    public function onOpen(ConnectionInterface $con)
    {

        $response = $this->handleLaravelRequest($con, '/websocket/open');


        if ($response->status() == 401){
            $con->send(json_encode(['connection closed' => 'not authorised!']));
            $con->close();
        }
        else{
            $this->clients->attach($con);
        }


    }
    public function onMessage(ConnectionInterface $con, $msg)
    {
        $con->send(json_encode(['onMessage#JoinRoomBack' => $msg]));
        //$this->handleLaravelRequest($con, '/websocket/message', $msg);
    }
    public function onClose(ConnectionInterface $con)
    {
        //$this->handleLaravelRequest($con, '/websocket/close');
       //$this->clients->detach($con);
    }
    public function onError(ConnectionInterface $con, \Exception $e)
    {

        $con->send(json_encode(['error' => $e->getMessage()]));
        //$this->handleLaravelRequest($con, '/websocket/error');
       // echo 'Error: ' . $e->getMessage() . PHP_EOL;
       // $con->close();
    }
}


