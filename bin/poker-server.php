<?php
/**
 * Poker Game WebSocket Server
 * 
 * Implements a WebSocket server using the Ratchet library.
 * Install using the command: composer require cboden/ratchet
 */

// Load required files
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/php/PokerGame.php';
require dirname(__DIR__) . '/php/gameSocket.php';

// Use Ratchet library
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Port configuration
$port = 8080;

// Get port from command line arguments (if available)
if (isset($argv[1])) {
    $port = (int)$argv[1];
}

// Create WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new PokerGameServer()
        )
    ),
    $port
);

echo "Poker game WebSocket server running on port $port.\n";
echo "Press Ctrl+C to stop the server.\n";

// Run server
$server->run();
?> 