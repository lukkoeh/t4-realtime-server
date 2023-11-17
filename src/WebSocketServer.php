<?php

namespace src;

use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

require_once __DIR__ . "/SlimAuthenticationProvider.php";
require_once __DIR__ . "/DatabaseSingleton.php";

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $users;
    // multi dimensional array storing document ids and the connections that are currently editing them, array of SPLObjectStorage
    protected $documents = [];
    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }
    public function onOpen(ConnectionInterface $conn): void
    {
        echo "New connection: {$conn->resourceId}\n";
        $conn->send('{"code": 200, "action": "information" ,"message": "Successfully connected to the server"}');
        $this->clients->attach($conn);
    }

    /**
     * @throws Exception
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        # check message format
        if (!$this->isJson($msg)) {
            $from->send('{"code": 400, "message": "Not JSON"}');
            return;
        }
        # parse the message
        $parsed = json_decode($msg);
        if (isset($parsed->document_selection) && isset($parsed->token)) {
            // just attach the connection to the respective document
            if (!SlimAuthenticationProvider::validatetoken($parsed->token)) {
                $from->send('{"code": 401, "message": "Unauthorized"}');
                return;
            }
            if (!isset($this->documents[$parsed->document_selection])) {
                $this->documents[$parsed->document_selection] = new SplObjectStorage();
            }
            $this->documents[$parsed->document_selection]->attach($from);
            return;
        }
        # check if all fields are set
        if (!isset($parsed->token) || !isset($parsed->payload) || !isset($parsed->document_id)) {
            $from->send('{"code": 400, "message": "Bad Request"}');
            return;
        }
        # check token
        if (!SlimAuthenticationProvider::validatetoken($parsed->token)) {
            $from->send('{"code": 401, "message": "Unauthorized"}');
            return;
        }
        # assign the connection to the document
        if (!isset($this->documents[$parsed->document_id])) {
            $this->documents[$parsed->document_id] = new SplObjectStorage();
        }
        $this->documents[$parsed->document_id]->attach($from);
        // detach from all other documents
        foreach ($this->documents as $document_id => $connections) {
            if ($document_id != $parsed->document_id) {
                $connections->detach($from);
            }
        }
        // save the delta to the database
        $db_connection = DatabaseSingleton::getInstance();
        $db_connection->perform_query("INSERT INTO t4_deltas (delta_document, delta_owner, delta_content) VALUES (?, ?, ?)", [$parsed->document_id, SlimAuthenticationProvider::getUserIdByToken($parsed->token), json_encode($parsed->payload)]);
        // take the payload and send it to all other clients that use the same document
        foreach ($this->documents[$parsed->document_id] as $connection) {
            if ($connection != $from) {
                // build json array in format: code, action, payload
                $response_template = [
                    "code" => 200,
                    "action" => "update",
                    "payload" => $parsed->payload
                ];
                $connection->send(json_encode($response_template));
            }
        }
    }

    /**
     * @param ConnectionInterface $conn
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        // detach the connection from any document
        foreach ($this->documents as $document_id => $connections) {
            $connections->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        // TODO: Implement onError() method.
    }

    public function isJson($string) : bool {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}