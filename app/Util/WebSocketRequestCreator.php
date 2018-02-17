<?php
namespace App\Util;

require __DIR__ . '/../../vendor/autoload.php';


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Dflydev\FigCookies\Cookies;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
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
         * @var \Ratchet\WebSocket\Version\RFC6455\Connection $con
         * @var \Guzzle\Http\Message\Request $wsrequest
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


        $app = require __DIR__ . '/../../bootstrap/app.php';
        $kernel = $app->make(Kernel::class);


        $response = $kernel->handle(
            $request = Request::create($route, 'GET', $params,$cookies)
        );

        //var_dump(Auth::id());
        $controllerResult = $response->getContent();
        $kernel->terminate($request, $response);
        return json_encode($controllerResult);
    }
    public function onOpen(ConnectionInterface $con)
    {

        $this->clients->attach($con);
        $result = $this->handleLaravelRequest($con, '/websocket/open');

        $con->send(json_encode(['megawin' => $result]));

    }
    public function onMessage(ConnectionInterface $con, $msg)
    {
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


