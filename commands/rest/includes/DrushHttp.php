<?php

/**
 * @file
 * Code for exposing Drush as a web service using PHP's built in server.
 */

// Process the request and return the output.
return _drush_rest_api_http_set_output(_drush_rest_api_http_request());

/**
 * Set the output and response code.
 *
 * @param array $response
 *   The data to output to the requester.
 *
 * @return string
 *   Return a JSON encoded string of data.
 */
function _drush_rest_api_http_set_output($response) {
  // Set headers.
  _drush_rest_api_http_set_headers();
  // Print output.
  echo json_encode($response);
  // Set response code.
  if (isset($response['response_code'])) {
    http_response_code($response['response_code']);
  }
  else {
    if ($response['error_status'] == 1) {
      http_response_code(400);
    }
    else {
      http_response_code(200);
    }
  }
}
/**
 * Set the headers.
 */
function _drush_rest_api_http_set_headers() {
  header('Content-Type: application/json');
  // Set custom headers.
  if (isset($_ENV['DRUSH_REST_API_HTTP_HEADERS'])) {
    $headers = unserialize($_ENV['DRUSH_REST_API_HTTP_HEADERS']);
    foreach ($headers as $header) {
      header($header);
    }
  }
}

/**
 * Make the request and get a response.
 *
 * @return array
 *   Return an array of data to display to the requester.
 */
function _drush_rest_api_http_request() {
  // Take our request and pass to `drush web-service-request`.
  $request = urldecode(ltrim($_SERVER['REQUEST_URI'], '/'));
  $drush_executable = $_ENV['DRUSH'];
  $command = sprintf('%s rest-api-request %s %s %s',
    escapeshellarg($drush_executable),
    escapeshellarg($request),
    escapeshellarg($_SERVER['HTTP_HOST']),
    escapeshellarg($_SERVER['REMOTE_ADDR'])
  );
  if (isset($_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_IPS'])) {
    $command .= sprintf(' --allowable-ips="%s"', $_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_IPS']);
  }
  if (isset($_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_HOSTS'])) {
    $command .= sprintf(' --allowable-http-hosts="%s"', $_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_HOSTS']);
  }
  if (isset($_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_COMMANDS'])) {
    $command .= sprintf(' --allowable-commands="%s"', $_ENV['DRUSH_REST_API_HTTP_ALLOWABLE_COMMANDS']);
  }
  // Log the command.
  error_log('Drush REST API: ' . $command);
  // `rest-api-request` will return a JSON encoded string. We need to decode it
  // so that we can get at the response code and error status values.
  $response = json_decode(shell_exec($command), TRUE);
  return $response;
}
