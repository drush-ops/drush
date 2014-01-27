<?php

/**
 * Launch a Drush server over HTTP(s).
 */
function api_server_launch() {
  if (!drush_confirm('You should not use the HTTP server option on a public network. Are you sure you want to continue?')) {
    return FALSE;
  }
  $env_vars = array();
  $env_vars['DRUSH'] = DRUSH_BASE_PATH . '/drush';

  $php = drush_get_option('php');
  if ($allowable_ips = array_filter(drush_get_option_list('allowable-ips'))) {
    $env_vars['DRUSH_API_ALLOWABLE_IPS'] = $allowable_ips;
  }
  if ($allowable_http_hosts = array_filter(drush_get_option_list('allowable-http-hosts'))) {
    $env_vars['DRUSH_API_ALLOWABLE_HOSTS'] = $allowable_http_hosts;
  }
  if ($headers = array_filter(drush_get_option_list('headers'))) {
    $env_vars['DRUSH_API_HEADERS'] = escapeshellarg(serialize($headers));
  }
  $prepend = DRUSH_BASE_PATH . '/commands/api/includes/DrushHttp.php';
  $host = drush_get_option('host', 'localhost');
  $port = drush_get_option('port', '8888');
  $vars = '';
  foreach ($env_vars as $key => $value) {
    $vars .= $key . '=' . $value . ' ';
  }
  $cmd = sprintf('%s%s -d variables_order=EGPCS -S %s:%s %s -c %s',
    $vars,
    $php,
    $host,
    $port,
    $prepend,
    DRUSH_BASE_PATH . '/commands/api/includes/cli-server.ini');
  return drush_shell_exec_interactive($cmd);
}
