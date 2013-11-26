<?php

/**
 * @file
 *   Classes extending the httpserver library that provide Drush specific
 *   behaviours.
 */

/**
 * Extends the HTTPServer class, handling request routing and environment.
 */
class DrushServer extends HTTPServer {
  // We pass in variables, rather than querying options here, to allow this to
  // potentially be used in other commands.
  public $path, $debug, $env, $site;

  /**
   * This is the equivalent of .htaccess, passing requests to files if they
   * exist, and all other requests to index.php. We also set a number
   * of CGI environment variables here.
   */
  function route_request($request) {
    $drush_command = ltrim($request->uri, '/');
    $command = sprintf('/usr/bin/drush %s --backend --root=%s', $drush_command, DRUPAL_ROOT);
    $data = shell_exec($command);
    $output = drush_backend_parse_output($data);
    $error_log = '';
    if ($output['error_log']) {
      foreach ($output['error_log'] as $type => $value) {
        $error_log .= $type . ': ';
        $error_log .= implode('; ', $value);
        $error_log .= "\n";
      }
      $local_file = drush_save_data_to_temp_file($error_log);
    }
    elseif (!$output['output']) {
      $local_file = drush_save_data_to_temp_file(dt('There was an error processing the request: !request', array('!request' => $request->uri)));
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
