<?php

/**
 * @file
 * Class extending the httpserver library that provides Drush specific
 *   behaviours.
 */

/**
 * Extends the HTTPServer class, handling request routing and environment.
 */
class DrushServer extends HTTPServer {
  // We pass in variables, rather than querying options here, to allow this to
  // potentially be used in other commands.
  public $path;
  public $debug;
  public $env;
  public $site;

  /**
   * Handle routing requests and pass off to Drush.
   *
   * Callers to the server should specify `--format=json` if JSON output is
   * desired. Error output will always be displayed in JSON format.
   */
  function route_request($request) {
    // Check if IP is allowed access to the runserver.
    if (!runserver_check_allowed_ips()) {
      return $this->response(403, json_encode('Access denied.'));
    }
    drush_shell_exec('which drush');
    $drush_command = drush_shell_exec_output();
    $drush_args = ltrim($request->uri, '/');
    $command = sprintf('%s %s --backend', array_shift($drush_command), $drush_args);
    drush_print(dt('Drush Server: Running command !cmd', array('!cmd' => $command)));
    $data = shell_exec($command);
    $output = drush_backend_parse_output($data);
    $response_data = '';
    $status = 200;
    if ($output['error_log']) {
      $header = array('Content-Type' => 'application/json');
      $response_data = json_encode($output['error_log']);
      $status = 500;
    }
    elseif (!$output['output']) {
      $header = array('Content-Type' => 'application/json');
      $response_data = json_encode(dt('Successfully ran the command: !request', array('!request' => $request->uri)));
    }
    else {
      // Check if Drush output can be decoded as JSON and set headers appropriately.
      if (json_decode($output['output'])) {
        $header = array('Content-Type' => 'application/json');
      }
      $response_data = $output['output'];
    }
    return $this->response($status, $response_data, $header, NULL);
  }

  /**
   * Override request done event.
   */
  function request_done($request) {
    drush_print(trim($this->get_log_line($request), "\n"));

    if ($this->debug) {
      drush_print_r($request);
    }
  }
}
