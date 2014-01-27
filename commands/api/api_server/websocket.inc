<?php

/**
 * @file
 * Code for launching a Drush server using a websocket.
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * Launch the Drush API server using a websocket.
 */
function api_server_launch() {
  $port = drush_get_option('port', 8080);
  if (drush_get_option('request-handler')) {
    $request_handler = drush_get_option('command-callback', 'default');
  }
  require_once DRUSH_BASE_PATH . '/commands/api/includes/DrushWebSocket.php';
  drush_log(dt('Creating Drush Web Socket, listening at http://localhost:8080'), 'ok');
  $server = IoServer::factory(
    new HttpServer(
      new WsServer(
        new DrushWebSocket()
      )
    ),
    $port
  );

  $server->run();
}
