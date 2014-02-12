<?php

require_once DRUSH_BASE_PATH . '/commands/rest/includes/DrushHttp.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;



/**
 * Launch a Drush REST API server over HTTP.
 *
 * This code is run with the `--server-type=http` option for the
 * `rest-api-server` command.
 */
function rest_api_server_launch() {
  // Warn the user about running this command on a public network.
  if (!drush_confirm('You should not use the HTTP server option on a public network. Are you sure you want to continue?')) {
    return FALSE;
  }
  if ($allowable_ips = array_filter(drush_get_option_list('allowable-ips'))) {
    $allowable_ips = implode(',', $allowable_ips);
  }
  // Set allowable HTTP hosts as an environment variable.
  if ($allowable_http_hosts = array_filter(drush_get_option_list('allowable-http-hosts'))) {
    $allowable_hosts = implode(',', $allowable_http_hosts);
  }
  // Set any additional headers as an environment variable.
  if ($headers = array_filter(drush_get_option_list('headers'))) {
    $headers = implode(',', $headers);
  }
  $host = drush_get_option('host', 'localhost');
  $port = drush_get_option('port', '8888');
  require_once DRUSH_BASE_PATH . '/commands/rest/includes/DrushHttp.php';
  $server = new HttpServer(new DrushRestApiServerHttp($allowable_ips, $allowable_http_hosts, $headers));
//  $server = IoServer::factory($http, $port, $host);
  $server = IoServer::factory(new \Ratchet\Http\HttpServer(new DrushRestApiServerHttp($allowable_ips, $allowable_http_hosts, $headers)), $port, '127.0.0.1');
  $server->run();
//  $http_server = new
//  $http_server->run_forever();
}
