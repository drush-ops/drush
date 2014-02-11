<?php

/**
 * @file
 * Code for Drush REST API functionality.
 */

/**
 * Implements hook_drush_help().
 */
function rest_api_drush_help($section) {
  switch ($section) {
    case 'drush:rest-api':
      return dt('Expose Drush as a RESTful API over HTTP or WebSockets.');
  }
}

/**
 * Implements hook_drush_command().
 */
function rest_api_drush_command() {
  $items['rest-api-server'] = array(
    'description' => 'Expose Drush as a RESTful API over HTTP or WebSockets.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'engines' => array(
      'rest_api_server' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
    'arguments' => array(
      'action' => array(
        'description' => 'The action to run. Valid actions are `start` and `stop`.',
      ),
    ),
    'required-arguments' => TRUE,
    // TODO: Host may not work with Ratchet.
    'options' => array(
      'host' => array(
        'description' => 'The hostname to use for the Drush REST API server. Typical values may include "localhost", "127.0.0.1", or "0.0.0.0".',
        'example-value' => 'localhost',
      ),
      'port' => array(
        'description' => 'The port to use for the Drush REST API server.',
        'example-value' => '8888',
      ),
      'allowable-ips' => array(
        'description' => 'A comma-separated list of IPs that may use the Drush REST API. By default all IPs are allowed. Note that IPs may be spoofed by non-browser clients.',
        'example-value' => '"127.0.0.1,0.0.0.0"',
      ),
      'allowable-http-hosts' => array(
        'description' => 'A comma-separated list of host names and ports that will be accepted from the requester. By default all host names are allowed. Note that host names may be spoofed by non-browser clients.',
        'example-value' => "localhost:8080,example.com:443",
      ),
    ),
    'examples' => array(
      'drush rest-api-server' => 'Start a Drush REST API server using WebSockets.',
      'drush rest-api-server --server-type=http' => 'Start a Drush REST API server using PHP\'s built-in HTTP server.',
      'drush rest-api-server &' => 'Start a Drush REST API server process using WebSockets, and run in the background. End the process by running `drush rest-api-server stop`.',
      'drush rest-api-server stop' => 'Terminate the current running Drush REST server process.',
    ),
  );
  $items['rest-api-request'] = array(
    'description' => 'Return data from the Drush REST API server to the requester.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'hidden' => TRUE,
    'options' => array(
      'allowable-ips' => array(
        'description' => 'A comma-separated list of IPs that may use the Drush REST API. By default all IPs are allowed. Note that IPs can be spoofed by non-browser clients.',
        'example-value' => '"127.0.0.1,0.0.0.0"',
        'hidden' => TRUE,
      ),
      'allowable-http-hosts' => array(
        'description' => 'A comma-separated list of host names and ports that will be accepted from the requester. By default all host names are allowed. Note that host names can be spoofed by non-browser clients.',
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
 * Command callback for `drush rest-api-server`.
 *
 * Launch a new Drush REST server using a specified engine type.
 */
function drush_rest_api_server() {
  rest_api_server_launch();
}

/**
 * Implements hook_COMMAND_validate().
 */
function drush_rest_api_server_validate($action) {
  if (!in_array($action, array('stop', 'start'))) {
    return drush_set_error('DRUSH_REST_API_SERVER_INVALID_ACTION', dt('Only "start" and "stop" are valid actions.'));
  }
  // See if a process is already running.
  $cache = drush_cache_get('rest-server-pid', 'drush-rest-api');
  // If the 'stop' command was passed, then terminate the Drush REST server
  // process ID that is stored in the cache.
  if ($action == 'stop') {
    if ($cache->data) {
      // Kill the process.
      $pid = $cache->data;
      // Check if active first.
      if (!drush_process_is_active($pid)) {
        return drush_set_error(dt('There is no currently active Drush REST API server process.'));
      }
      else {
        $result = drush_kill_task($pid);
        if ($result) {
          drush_log(dt('Successfully terminated Drush REST API server process !pid.', array('!pid' => $pid)), 'success');
          // Returning FALSE to prevent starting a new REST API server instance.
          return FALSE;
        }
        else {
          return drush_set_error('DRUSH_API_SERVER_PROCESS', dt('Failed to terminate process !pid', array('!pid' => $pid)));
        }
      }
    }
    else {
      return drush_set_error(dt('There is no active Drush REST API server process.'));
    }
  }
  else {
    // `start` command. Check if an instance is already running.
    // If so, return an error.
    if ($cache->data) {
      // N.B. This will only work for Linux.
      if (drush_process_is_active($cache->data)) {
        return drush_set_error(dt('It appears that a Drush REST server instance is already running with process ID !pid. Run `drush rest-api-server stop` to terminate the process.', array('!pid' => $cache->data)));
      }
    }
  }
  // Clear the process ID and port cache, and set the new process ID.
  if ($cache->data) {
    drush_cache_clear_all(NULL, 'drush-rest-api');
  }
  drush_log(dt('Starting Drush REST API server with process ID !pid', array('!pid' => getmypid())));
  // Store the process ID for the server.
  drush_cache_set('rest-server-pid', getmypid(), 'drush-rest-api');
  drush_cache_set('rest-server-port', drush_get_option('port', 8888), 'drush-rest-api');
  // Verify PHP requirements.
  if (drush_get_option('server-type') == 'http') {
    // Verify that user is on PHP 5.4+ for HTTP option.
    if (version_compare(phpversion(), '5.4') < 0) {
      return drush_set_error('DRUSH_REST_API_SERVER_REQUIREMENTS_ERROR', dt('You need PHP 5.4 to use the HTTP option for the Drush REST API Server command. You are running PHP !version', array('!version' => phpversion())));
    }
  }
  return TRUE;
}

/**
 * Implements hook_drush_engine_type_info().
 *
 * Inform Drush about our server type and request handler engines.
 */
function rest_api_drush_engine_type_info() {
  return array(
    'rest_api_server' => array(
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
 * Provides information about the rest-api-server-type engine.
 */
function rest_api_drush_engine_rest_api_server() {
  return array(
    'http' => array(
      'description' => 'Serve Drush over HTTP using PHP\'s built-in server. It is not recommended to use this on a public network',
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
 * Command callback for `drush rest-api-request`.
 *
 * @param string $request
 *   The incoming request to the Drush REST API server.
 * @param string $http_host
 *   The HTTP host of the requester.
 * @param string $ip_address
 *   The IP address of the requester.
 *
 * @return array
 *   Returns an array of data to present to the requester. By default, the
 *   `rest-api-request` command will use the JSON output format.
 */
function drush_rest_api_request($request, $http_host = NULL, $ip_address = NULL) {
  // Check if access is allowed.
  if (!_rest_api_request_allow($http_host, $ip_address)) {
    return _rest_api_access_denied();
  }
  $port = _drush_rest_api_server_get_port();
  return _rest_api_process_request($ip_address, $http_host, $port, $request);
}

/**
 * Return the port number of the active REST API server process.
 */
function _drush_rest_api_server_get_port() {
  $cache = drush_cache_get('rest-server-port', 'drush-rest-api');
  return $cache->data;
}

/**
 * Check if the request is allowed.
 *
 * Validation is performed against the allowable IP addresses and hosts
 * specified when the `rest-api-server` command was run. Contrib Drush commands
 * can hook into the validation process later on in the process, in
 * _rest_api_process_request().
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
function _rest_api_request_allow($http_host, $ip_address) {
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
 *   The incoming request to the Drush REST API server.
 *
 * @return mixed
 *   If request is in the format expected by the Drush REST API server, an array
 *   with the request values parsed into alias, command, args and options is
 *   returned. Otherwise we return FALSE.
 */
function _rest_api_parse_request($request) {
  $parsed = parse_url($request);
  $args = explode('/', $parsed['path']);
  $alias = array_shift($args);
  $command = array_shift($args);
  $options = explode('&', $parsed['query']);
  $parsed_request = array(
    'alias' => $alias,
    'command' => $command,
    'args' => $args,
    'options' => $options,
  );
  return _rest_api_is_valid_request($parsed_request) ? $parsed_request : FALSE;
}

/**
 * Validate the incoming request.
 *
 * In _rest_api_parse_request(), we generate an array from the incoming request
 * with the alias, command, options, and args that the requester would like to
 * run. Here we check if those parameters are valid.
 *
 * @param array $request
 *   The parsed request returned from _rest_parse_request().
 *
 * @return bool
 *   Return TRUE if the request is valid, and FALSE otherwise.
 */
function _rest_api_is_valid_request($request) {
  // Verify alias.
  if (!drush_sitealias_get_record($request['alias']) && $request['alias'] !== '@none') {
    return FALSE;
  }
  // Don't let users get clever.
  if (in_array($request['command'], array('rest-api-server', 'rest-api-request'))) {
    return FALSE;
  }
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
function _rest_api_process_request($ip_address, $host, $port, $request) {
  $data = _rest_api_parse_request($request);
  if (!$data) {
    return _rest_api_error();
  }
  // Allow custom Drush commands to intercept the request and process it. If
  // no command intercepting the request then we will process it ourselves.
  if ($result = drush_command_invoke_all('drush_rest_api_process_request_alter', $ip_address, $host, $port, $request)) {
    return $result;
  }
  else {
    return _rest_api_run_command(
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
function _rest_api_run_command($alias, $command, $args = array(), $options = array()) {
  // Convert options for drush_invoke_process().
  $converted_options = array();
  foreach ($options as $option) {
    if (strpos($option, '=')) {
      list($opt, $value) = explode('=', $option);
      $converted_options[$opt] = $value;
    }
    elseif (!empty($option)) {
      $converted_options[$option] = TRUE;
    }
  }
  return drush_invoke_process($alias, $command, $args, $converted_options, FALSE);
}

/**
 * An access denied array to return to drush_rest_api_request().
 *
 * @return array
 *   Returns an array of data to be used for an access denied message to the
 *   requester.
 */
function _rest_api_access_denied() {
  return array(
    'response_code' => 403,
    'error_status' => 1,
    'error_log' => dt('Access denied.'),
  );
}

/**
 * An error array to return to drush_rest_api_request().
 *
 * @return array
 *   Returns an array of data to be used as an error message if an invalid
 *   request is made.
 */
function _rest_api_error() {
  return array(
    'error_status' => 1,
    'error_log' => dt('Invalid request. REST API requests must use this format: {@alias}/{command}/{argument}/{argument_two}?{option=value&option2=value2}'),
  );
}
