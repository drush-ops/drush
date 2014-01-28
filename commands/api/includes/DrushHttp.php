<?php

/**
 * @file
 * Code for exposing Drush as a web service using PHP's built in server.
 */

// Process the request and return the output.
return _drush_api_set_output(_drush_api_request());

/**
 * Set the output and response code.
 *
 * @param array $response
 *   The data to output to the requester.
 *
 * @return string
 *   Return a JSON encoded string of data.
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
 *
 * @return array
 *   Return an array of data to display to the requester.
 */
function _drush_api_request() {
  // Take our request and pass to `drush web-service-request`.
  $request = urldecode(ltrim($_SERVER['REQUEST_URI'], '/'));
  $drush_executable = $_ENV['DRUSH'];
  $command = sprintf('%s api-request %s %s %s',
    escapeshellarg($drush_executable),
    escapeshellarg($request),
    escapeshellarg($_SERVER['HTTP_HOST']),
    escapeshellarg($_SERVER['REMOTE_ADDR'])
  );
  // Log the command.
  error_log('Drush API: ' . $command);
  // `api-request` will return a JSON encoded string. We need to decode it
  // so that we can get at the response code and error status values.
  $response = json_decode(shell_exec($command), TRUE);
  return $response;
}
