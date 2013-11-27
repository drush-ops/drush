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
    drush_shell_exec('which drush');
    $drush_command = drush_shell_exec_output();
    $drush_args = ltrim($request->uri, '/');
    $command = sprintf('%s %s --backend', array_shift($drush_command), $drush_args);
    drush_print(dt('Drush Server: Running command !cmd', array('!cmd' => $command)));
    $data = shell_exec($command);
    $output = drush_backend_parse_output($data);
    if ($output['error_log']) {
      $local_file = drush_save_data_to_temp_file(json_encode($output['error_log']));
    }
    elseif (!$output['output']) {
      $local_file = drush_save_data_to_temp_file(json_encode(dt('Successfully ran the command: !request', array('!request' => $request->uri))));
    }
    else {
      $local_file = drush_save_data_to_temp_file($output['output']);
    }
    return $this->get_static_response($request, $local_file);
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
