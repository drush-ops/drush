<?php

/**
 * @file
 * Code for launching a Drush server using a WebSocket.
 */

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

/**
 * Launch the Drush REST API server using a WebSocket.
 *
 * This code is run when the `--server-type=websocket` option is passed to the
 * `rest-api-server` command.
 */
function rest_api_server_launch() {
  require_once DRUSH_BASE_PATH . '/commands/rest/includes/DrushWebSocket.php';
  $port = drush_get_option('port', 8080);
  $allowable_ips = drush_get_option_list('allowable-ips');
  $allowable_hosts = drush_get_option_list('allowable-http-hosts');
  drush_log(dt('Creating Drush Web Socket, listening at ws://localhost:!port', array('!port' => $port)), 'ok');
  $server = IoServer::factory(
    new HttpServer(
      new WsServer(
        new DrushRestApiServerWebSocket($allowable_ips, $allowable_hosts)
      )
    ),
    $port
  );

  $server->run();
}
