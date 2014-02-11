<?php

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
  // Set the path to Drush as an environment variable, for use in the DrushHttp
  // file.
  $env_vars = array();
  $env_vars['DRUSH'] = DRUSH_BASE_PATH . '/drush';
  $php = drush_get_option('php');
  // Set allowable IPs as an environment variable.
  if ($allowable_ips = array_filter(drush_get_option_list('allowable-ips'))) {
    $env_vars['DRUSH_API_ALLOWABLE_IPS'] = implode(',', $allowable_ips);
  }
  // Set allowable HTTP hosts as an environment variable.
  if ($allowable_http_hosts = array_filter(drush_get_option_list('allowable-http-hosts'))) {
    $env_vars['DRUSH_API_ALLOWABLE_HOSTS'] = implode(',', $allowable_http_hosts);
  }
  // Set any additional headers as an environment variable.
  if ($headers = array_filter(drush_get_option_list('headers'))) {
    $env_vars['DRUSH_API_HEADERS'] = escapeshellarg(serialize($headers));
  }
  $prepend = DRUSH_BASE_PATH . '/commands/rest/includes/DrushHttp.php';
  $host = drush_get_option('host', 'localhost');
  $port = drush_get_option('port', '8888');
  $vars = '';
  foreach ($env_vars as $key => $value) {
    $vars .= $key . '=' . $value . ' ';
  }
  // Instantiate the HTTP server, using the environment variables defined above.
  // The `cli-server.ini` file is included for colorized output to the terminal.
  $cmd = sprintf('%s%s -d variables_order=EGPCS -S %s:%s %s -c %s',
    $vars,
    $php,
    $host,
    $port,
    $prepend,
    DRUSH_BASE_PATH . '/commands/rest/includes/cli-server.ini');
  return drush_shell_exec_interactive($cmd);
}
