<?php

/**
 * @file
 * Code for Drush Web Service API functionality.
 */

/**
 * Implements hook_drush_help().
 */
function api_drush_help($section) {
  switch ($section) {
    case 'drush:api':
      return dt('Expose Drush as a web service. Allow clients to interact with Drush over HTTP or WebSockets.');
  }
}

/**
 * Implements hook_drush_command().
 */
function api_drush_command() {
  $items['api-server'] = array(
    'description' => 'Expose Drush as a web service.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'engines' => array(
      'api_server' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
    // TODO: Host may not work with Ratchet.
    'options' => array(
      'host' => array(
        'description' => 'The hostname to use for the Drush API server. Typical values may include "localhost", "127.0.0.1", or "0.0.0.0".',
        'example-value' => 'localhost',
      ),
      'port' => array(
        'description' => 'The port to use for the Drush API server.',
        'example-value' => '8888',
      ),
      'allowable-ips' => array(
        'description' => 'A comma-separated list of IPs that may use the web service. By default all IPs are allowed.',
        'example-value' => '"127.0.0.1,0.0.0.0"',
      ),
      'allowable-http-hosts' => array(
        'description' => 'A comma-separated list of host names and ports that will be accepted from the requester. By default all host names are allowed.',
        'example-value' => "localhost:8080,example.com:443",
      ),
      'kill' => array(
        'description' => 'Add this option if you already started a Drush API server process (for example, using `drush api-server &` that you want to stop.',
      ),
    ),
    'examples' => array(
      'drush api-server' => 'Start a Drush API server using WebSockets.',
      'drush api-server --server-type=http' => 'Start a Drush API server using PHP\'s built-in HTTP server.',
      'drush api-server &' => 'Start a Drush API server process using WebSockets, and run in the background. End the process by running `drush api-server --kill`.',
      'drush api-server --kill' => 'Terminate the current running Drush API server process.',
    ),
  );
  $items['api-request'] = array(
    'description' => 'Return data from Drush to the caller.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'hidden' => TRUE,
    'options' => array(
      'allowable-ips' => array(
        'description' => 'A comma-separated list of IPs that may use the web service. By default all IPs are allowed.',
        'example-value' => '"127.0.0.1,0.0.0.0"',
        'hidden' => TRUE,
      ),
      'allowable-http-hosts' => array(
        'description' => 'A comma-separated list of host names and ports that will be accepted from the requester. By default all host names are allowed.',
        'example-value' => "localhost:8080,example.com:443",
        'hidden' => TRUE,
      ),
    ),
    'arguments' => array(
      'request' => 'The request to execute',
      'http-host' => 'The HTTP host of the requester',
      'ip' => 'The IP address of the requester.',
    ),
    'outputformat' => array(
      'default' => 'json',
      'engines' => 'json',
      'pipe-format' => 'json',
    ),
  );
  return $items;
}

/**
 * Command callback for `drush api-server`.
 *
 * Launch a new Drush API server using a specified engine type.
 */
function drush_api_server() {
  api_server_launch();
}

/**
 * Implements hook_COMMAND_init().
 */
function drush_api_server_init() {
  // Remove the process ID from the cache.
  register_shutdown_function('drush_cache_clear_all', 'pid', 'drush-api');
}

/**
 * Implements hook_COMMAND_validate().
 */
function drush_api_server_validate() {
  // See if a process is already running.
  $cache = drush_cache_get('pid', 'drush-api');
  // If the '--kill' option was passed, then terminate the Drush API server
  // process ID that is stored in the cache.
  if (drush_get_option('kill')) {
    if ($cache->data) {
      // Kill the process.
      $pid = $cache->data;
      $result = stripos(php_uname('s'), 'win') > -1 ? drush_shell_exec("taskkill /F /PID $pid") : drush_shell_exec("kill -9 $pid");
      if ($result) {
        drush_log(dt('Successfully terminated Drush API server process !pid.', array('!pid' => $pid)), 'success');
      }
      else {
        drush_set_error(dt('There was no Drush API server process with ID !pid', array('!pid' => $pid)));
      }
      return FALSE;
    }
    else {
      return drush_set_error(dt('There is no active Drush API server process.'));
    }
  }
  // Clear the process ID cache, and set the new process ID.
  if ($cache->data) {
    drush_cache_clear_all('pid', 'drush-api');
  }
  drush_log(dt('Starting Drush API server with process ID !pid', array('!pid' => getmypid())));
  // Store the process ID for the server.
  drush_cache_set('pid', getmypid(), 'drush-api');
  // Verify PHP requirements.
  if (drush_get_option('server-type') == 'http') {
    // Verify that user is on PHP 5.4+ for HTTP option.
    if (version_compare(phpversion(), '5.4') < 0) {
      return drush_set_error('DRUSH_API_SERVER_REQUIREMENTS_ERROR', dt('You need PHP 5.4 to use the HTTP option for the API Server command. You are running PHP !version', array('!version' => phpversion())));
    }
  }
  // Check if an instance is already running. If so, return.
  if ($cache->data) {
    // TODO: This will only work for Linux.
    if (file_exists('/proc/' . (int) $cache->data)) {
      return drush_set_error(dt('It appears that a Drush API server instance is already running with process ID !pid. Run `drush api-server --kill` to terminate the process.', array('!pid' => $cache->data)));
    }
  }
  return TRUE;
}

/**
 * Implements hook_drush_engine_type_info().
 *
 * Inform Drush about our server type and request handler engines.
 */
function api_drush_engine_type_info() {
  return array(
    'api_server' => array(
      'description' => 'Determine how to provide access to Drush over the web.',
      'option' => 'server-type',
      'default' => 'websocket',
      'add-options-to-command' => FALSE,
    ),
  );
}

/**
 * @defgroup engines Engine types
 * @{
 */

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * Provides information about the api-server-type engine.
 */
function api_drush_engine_api_server() {
  return array(
    'http' => array(
      'description' => 'Serve Drush over HTTP using PHP\'s built-in server. This should not be used on a public network',
      'options' => array(
        'headers' => array(
          'description' => 'A comma-separated list of additional headers to set.',
          'example-value' => '"Access-Control-Allow-Origin: *"',
        ),
      ),
    ),
    'websocket' => array(
      'description' => 'Serve Drush over a WebSocket. If on a public network, using SSL is highly recommended.',
    ),
  );
}

/**
 * @} End of "Engine types".
 */

/**
 * Command callback for `drush api-request`.
 *
 * @param string $request
 *   The incoming request to the Drush API server.
 * @param string $http_host
 *   The HTTP host of the requester.
 * @param string $ip_address
 *   The IP address of the requester.
 *
 * @return array
 *   Returns an array of data to present to the requester. By default, the
 *   `api-request` command will use the JSON output format.
 */
function drush_api_request($request, $http_host = NULL, $ip_address = NULL) {
  // Check if access is allowed.
  if (!_api_request_allow($http_host, $ip_address)) {
    return _api_access_denied();
  }
  return _api_process_request($ip_address, $http_host, $request);
}

/**
 * Check if the request is allowed.
 *
 * Validation is performed against the allowable IP addresses and hosts
 * specified when the `api-server` command was run. Contrib Drush commands can
 * hook into the validation process later on in the process, in
 * _api_process_request().
 *
 * @param string $http_host
 *   The HTTP host making the request, e.g. "example.com". Note that this value
 *   can easily be spoofed by non-browser clients.
 * @param string $ip_address
 *   The IP address making the request, e.g. "127.0.0.1". Note that this value
 *   can be spoofed by non-browser clients.
 *
 * @return bool
 *   Return TRUE if the request is allowed and FALSE if not.
 */
function _api_request_allow($http_host, $ip_address) {
  // Check if allowable IPs are defined.
  if ($allowable_ips = array_filter(drush_get_option_list('allowable-ips'))) {
    if ($ip_address && !in_array($ip_address, $allowable_ips)) {
      return FALSE;
    }
  }
  // Check if HTTP_HOST matches.
  if ($allowable_http_hosts = array_filter(drush_get_option_list('allowable-http-hosts'))) {
    if (!in_array($http_host, $allowable_http_hosts)) {
      return FALSE;
    }
  }
  return TRUE;
}

/**
 * Parse an incoming request into alias, command, args and options.
 *
 * @param string $request
 *   The incoming request to the Drush API server.
 *
 * @return mixed
 *   If the request is in the format expected by the Drush API server, an array
 *   with the request values parsed into alias, command, args and options is
 *   returned. Otherwise we return FALSE.
 */
function _api_parse_request($request) {
  $parsed = parse_url($request);
  $args = explode('/', $parsed['path']);
  $alias = array_shift($args);
  $command = array_shift($args);
  // FIXME: Currently this skips the first option.
  $options = explode('&', $parsed['query']);
  $parsed_request = array(
    'alias' => $alias,
    'command' => $command,
    'args' => $args,
    'options' => $options,
  );
  return _api_is_valid_request($parsed_request) ? $parsed_request : FALSE;
}

/**
 * Validate the incoming request.
 *
 * In _api_parse_request(), we generate an array from the incoming request
 * with the alias, command, options, and args that the requester would like to
 * run. Here we check if those parameters are valid.
 *
 * @param array $request
 *   The parsed request returned from _api_parse_request().
 *
 * @return bool
 *   Return TRUE if the request is valid, and FALSE otherwise.
 */
function _api_is_valid_request($request) {
  // Verify alias.
  if (!drush_sitealias_get_record($request['alias']) && $request['alias'] !== '@none') {
    return FALSE;
  }
  // Check that the command exists.
  $context = drush_get_context();
  if (!in_array($request['command'], array_keys($context['DRUSH_COMMANDS']))) {
    return FALSE;
  }
  // Don't let users get clever.
  if (in_array($request['command'], array('api-server', 'api-request'))) {
    return FALSE;
  }
  // Validate number of args sent does not exceed what the command will accept.
  if (count($request['args']) > count($context['DRUSH_COMMANDS'][$request['command']]['arguments'])) {
    return FALSE;
  }
  // TODO: Validate options.


  return TRUE;
}

/**
 * Process an incoming request via drush_invoke_process() and return results.
 *
 * @param string $ip_address
 *   The IP address of the requester.
 * @param string $host
 *   The hostname of the requester.
 * @param string $request
 *   The request made to the API server.
 *
 * @return string
 *   Return a JSON formatted string containing either an error message if an
 *  invalid request was made, or the complete output of drush_invoke_process().
 */
function _api_process_request($ip_address, $host, $request) {
  $data = _api_parse_request($request);
  if (!$data) {
    return _api_error();
  }
  // TODO: Test this.
  if ($result = drush_command_invoke_all('drush_api_process_request_alter', $ip_address, $host, $request)) {
    return $result;
  }
  else {
    return _api_run_command(
      $data['alias'],
      $data['command'],
      $data['args'],
      $data['options']
    );
  }
}

/**
 * Run a command via drush_invoke_process() and return the output.
 *
 * @param string $alias
 *   The Drush alias to use for the command.
 * @param string $command
 *   The Drush command to run.
 * @param array $args
 *   An array of arguments to pass.
 * @param array $options
 *   An array of options to use.
 *
 * @return array
 *   The output of drush_invoke_process().
 */
function _api_run_command($alias, $command, $args = array(), $options = array()) {
  return drush_invoke_process($alias, $command, $args, $options, FALSE);
}

/**
 * An access denied array to return to drush_api_request().
 *
 * @return array
 *   Returns an array of data to be used for an access denied message to the
 *   requester.
 */
function _api_access_denied() {
  return array(
    'response_code' => 403,
    'error_status' => 1,
    'error_log' => dt('Access denied.'),
  );
}

/**
 * An error array to return to drush_api_request().
 *
 * @return array
 *   Returns an array of data to be used as an error message if an invalid
 *   request is made.
 */
function _api_error() {
  return array(
    'error_status' => 1,
    'error_log' => dt('Invalid request. API requests must use this format: {@alias}/{command}/{argument}/{argument_two}?{option=value&option2=value2}'),
  );
}