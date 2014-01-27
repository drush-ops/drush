<?php

/**
 * @file
 * Code for exposing Drush as a web service using PHP's built in server.
 */

$response = _drush_api_request();
return _drush_api_set_output($response);

/**
 * Set the output and response code.
 */
function _drush_api_set_output($response) {
  // Set headers.
  _drush_api_set_headers();
  // Print output.
  echo json_encode($response);
  // Set response code.
  if (isset($response['response_code'])) {
    http_response_code($response['response_code']);
  }
  else {
    if ($response['error_status'] == 1) {
      http_response_code(500);
    }
    else {
      http_response_code(200);
    }
  }
  return TRUE;
}
/**
 * Set the headers.
 */
function _drush_api_set_headers() {
  header('Content-Type: application/json');
  // Set custom headers.
  if (isset($_ENV['DRUSH_API_HEADERS'])) {
    $headers = unserialize($_ENV['DRUSH_API_HEADERS']);
    foreach ($headers as $header) {
      header($header);
    }
  }
}

/**
 * Make the request and get a response.
 */
function _drush_api_request() {
  // Take our request and pass to `drush web-service-request`.
  $request = urldecode(ltrim($_SERVER['REQUEST_URI'], '/'));
  if (!$request) {
    // Set a default command.
    $request = 'core-status';
  }
  $drush_executable = $_ENV['DRUSH'];
  $command = sprintf('%s api-request %s %s %s',
    escapeshellarg($drush_executable),
    escapeshellarg($request),
    escapeshellarg($_SERVER['HTTP_HOST']),
    escapeshellarg($_SERVER['REMOTE_ADDR'])
  );
  // Log the command.
  error_log('Drush API: ' . $command);
  $response = json_decode(shell_exec($command), TRUE);
  return $response;
}
