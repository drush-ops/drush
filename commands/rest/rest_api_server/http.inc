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
    return drush_user_abort();
  }
  // Set the path to Drush as an environment variable, for use in the DrushHttp
  // file.
  $env_vars = array();
  $env_vars['DRUSH'] = DRUSH_COMMAND;
  $php = drush_get_option('php');
  // Set allowable IPs as an environment variable.
  if ($allowable_ips = array_filter(drush_get_option_list('allowable-ips'))) {
    $env_vars['DRUSH_REST_API_HTTP_ALLOWABLE_IPS'] = implode(',', $allowable_ips);
  }
  // Set allowable HTTP hosts as an environment variable.
  if ($allowable_http_hosts = array_filter(drush_get_option_list('allowable-http-hosts'))) {
    $env_vars['DRUSH_REST_API_HTTP_ALLOWABLE_HOSTS'] = implode(',', $allowable_http_hosts);
  }
  // Set allowable commands as environment variable.
  if ($allowable_commands = array_filter(drush_get_option_list('allowable-commands'))) {
    $env_vars['DRUSH_REST_API_HTTP_ALLOWABLE_COMMANDS'] = implode(',', $allowable_commands);
  }
  // Set any additional headers as an environment variable.
  if ($headers = array_filter(drush_get_option_list('headers'))) {
    $env_vars['DRUSH_REST_API_HTTP_HEADERS'] = escapeshellarg(serialize($headers));
  }
  $prepend = DRUSH_BASE_PATH . '/commands/rest/includes/DrushHttp.php';
  $host = drush_get_option('host', 'localhost');
  $port = drush_get_option('port', '8888');
  $vars = '';
  foreach ($env_vars as $key => $value) {
    $vars .= $key . '=' . $value . ' ';
  }
  // Instantiate the HTTP server, using the environment variables defined above.
  $log_file = tempnam(drush_find_tmp(), 'drush_rest_api_server_http');
  $cmd = sprintf('%s%s -d variables_order=EGPCS -S %s:%s %s > %s 2>&1 & echo $!;',
    $vars,
    $php,
    $host,
    $port,
    $prepend,
    $log_file
  );
  $pid = exec($cmd, $output);
  if ($pid) {
    drush_log(dt('Starting Drush REST API HTTP server with process ID !pid. To stop the server, run `drush rest-api-server stop`.', array('!pid' => $pid)), 'ok');
    drush_cache_set('rest-server-pid', $pid, 'drush-rest-api');
    // AFAIK Windows does not have a `tail` command.
    if (!drush_is_windows()) {
      return drush_shell_exec_interactive('tail -f %s', $log_file);
    }
    else {
      return drush_log(dt('Drush REST API HTTP server process started. View the log file for the server: "!log"', array('!log' => $log_file)), 'success');
    }
  }
  else {
    return drush_set_error('DRUSH_REST_API_HTTP_LAUNCH_FAIL', dt('Failed to start Drush REST API HTTP server! Output: !out', array('!out' => $output)));
  }
}
