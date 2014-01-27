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
    'options' => array(
      'host' => 'The hostname to use for the Drush API server.',
      'port' => 'The port to use for the Drush API server.',
      'allowable-ips' => 'A comma-separated list of IPs that may use the web service. By default all IPs are allowed.',
      'allowable-http-hosts' => 'A comma-separated list of host names that will be accepted from the requester. By default all host names are allowed.',
    ),
  );
  $items['api-request'] = array(
    'description' => 'Return data from Drush to the caller.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(),
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
 * Implements hook_COMMAND_validate().
 */
function drush_api_server_validate() {
  if (drush_get_option('server-type') == 'http') {
    // Verify that user is on PHP 5.4+ for HTTP option.
    if (version_compare(phpversion(), '5.4') < 0) {
      return drush_set_error('DRUSH_API_SERVER_REQUIREMENTS_ERROR', dt('You need PHP 5.4 to use the HTTP option for the API Server command. You are running PHP !version', array('!version' => phpversion())));
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
          'description' => 'A comma-separated list of additional headers to set, for example, "Access-Control-Allow-Origin: *".',
        ),
      ),
    ),
    'websocket' => array(
      'description' => 'Serve Drush over a websocket. Use SSL if using over a public network.',
    ),
  );
}

/**
 * @} End of "Engine types".
 */

/**
 * Command callback for `drush api-request`.
 */
function drush_api_request($request, $http_host = NULL, $ip_address = NULL) {
  // Check if access is allowed.
  if (!_api_request_allow($http_host, $ip_address)) {
    return _api_access_denied();
  }
  return api_process_request($ip_address, $http_host, $request);
}

/**
 * Check if the request is allowed.
 */
function _api_request_allow($http_host, $ip_address) {

  // Check if allowable IPs are defined.
  if ($allowable_ips = array_filter(drush_get_option('allowable-ips'))) {
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
 */
function _api_parse_request($request) {
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
  return _api_is_valid_request($parsed_request) ? $parsed_request : FALSE;
}

/**
 * Validate the incoming request.
 */
function _api_is_valid_request($request) {
  // Verify alias.
  if (!drush_sitealias_get_record($request['alias']) && $request['alias'] !== '@none') {
    return FALSE;
  }
  $context = drush_get_context();
  if (!in_array($request['command'], array_keys($context['DRUSH_COMMANDS']))) {
    return FALSE;
  }
  // Don't let users get clever.
  if (in_array($request['command'], array('api-server', 'api-request'))) {
    return FALSE;
  }
  // Validate number of args does not exceed what command will accept.
  if (count($request['args']) > count($context['DRUSH_COMMANDS'][$request['command']]['arguments'])) {
    return FALSE;
  }
  // Validate options.


  return TRUE;
}
/**
 * Process an incoming request via drush_invoke_process() and return results.
 */
function api_process_request($ip_address, $host, $request) {
  $data = _api_parse_request($request);
  if (!$data) {
    return _api_error();
  }
  if ($result = drush_command_invoke_all_ref('drush_api_process_request_alter', $ip_address, $host, $request)) {
    return $result;
  }
  return _api_run_command($data['alias'], $data['command'], $data['args'], $data['options']);
}

/**
 * Run a command via drush_invoke_process() and return the output.
 */
function _api_run_command($alias, $command, $args = array(), $options = array()) {
  return drush_invoke_process($alias, $command, $args, $options, FALSE);
}

/**
 * An access denied array to return to drush_api_request().
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
 */
function _api_error() {
  return array(
    'error_status' => 1,
    'error_log' => dt('Invalid request.'),
  );
}