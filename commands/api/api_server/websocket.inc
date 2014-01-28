<?php

/**
 * @file
 * Code for launching a Drush server using a WebSocket.
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * Launch the Drush API server using a WebSocket.
 *
 * This code is run when the `--server-type=websocket` option is passed to the
 * `api-server` command.
 */
function api_server_launch() {
  require_once DRUSH_BASE_PATH . '/commands/api/includes/DrushWebSocket.php';
  $port = drush_get_option('port', 8080);
  $allowable_ips = drush_get_option_list('allowable-ips');
  $allowable_hosts = drush_get_option_list('allowable-http-hosts');
  drush_log(dt('Creating Drush Web Socket, listening at http://localhost:!port', array('!port' => $port)), 'ok');
  $server = IoServer::factory(
    new HttpServer(
      new WsServer(
        new DrushWebSocket($allowable_ips, $allowable_hosts)
      )
    ),
    $port
  );

  $server->run();
}
