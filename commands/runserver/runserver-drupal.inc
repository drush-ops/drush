<?php

/**
 * @file
 *   Classes extending the httpserver library that provide Drupal specific
 *   behaviours.
 */

/**
 * Extends the HTTPServer class, handling request routing and environment.
 */
class DrupalServer extends HTTPServer {
  // We pass in variables, rather than querying options here, to allow this to
  // potentially be used in other commands.
  public $path, $debug, $env, $site;

  /**
   * This is the equivalent of .htaccess, passing requests to files if they
   * exist, and all other requests to index.php. We also set a number
   * of CGI environment variables here.
   */
  function route_request($request) {
    $cgi_env = $this->env;

    // Handle static files and php scripts accessed directly
    $uri = $request->uri;
    $doc_root = DRUPAL_ROOT;
    $path = $doc_root . $uri;
    if (is_file(realpath($path))) {
      if (preg_match('#\.php$#', $uri)) {
        // SCRIPT_NAME is equal to uri if it does exist on disk
        $cgi_env['SCRIPT_NAME'] = $uri;
        return $this->get_php_response($request, $path, $cgi_env);
      }
      return $this->get_static_response($request, $path);
    }

    // Rewrite clean-urls
    $cgi_env['QUERY_STRING'] = 'q=' . ltrim($uri, '/');
    if ($request->query_string != "") {
      $cgi_env['QUERY_STRING'] .= '&' . $request->query_string;
    }

    $cgi_env['SCRIPT_NAME'] = '/index.php';
    $cgi_env['HTTP_HOST'] = $cgi_env['SERVER_NAME'] = $this->site;

    return $this->get_php_response($request, $doc_root . '/index.php', $cgi_env);
  }

  /**
   * Override get started event.
   */
  function listening() {
    if (!empty($this->browse)) {
      drush_start_browser($this->browse);
    }
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
