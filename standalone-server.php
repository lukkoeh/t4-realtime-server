<?php

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use src\WebSocketServer;

require __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src/WebSocketServer.php";

$server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new WebSocketServer()
            )
        ), 8082);

$server->run();