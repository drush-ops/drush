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
  public $site, $path, $conf_inject, $user, $watchdog, $debug, $first_request_complete;

  /**
   * This is the equivalent of .htaccess, passing requests to files if they
   * exist, and all other requests to index.php. We also set a number
   * of CGI environment variables here.
   */
  function route_request($request) {
    $cgi_env = array();

    // We pass in the effective base_url to our auto_prepend_script via the cgi
    // environment. This allows Drupal to generate working URLs to this http
    // server, whilst finding the correct multisite from the HTTP_HOST header.
    $cgi_env['RUNSERVER_BASE_URL'] = 'http://' . $this->addr . ':' . $this->port;

    // We pass in an array of $conf overrides using the same approach.
    // By default we set drupal_http_request_fails to FALSE, as the httpserver
    // is unable to process simultaneous requests on some systems.
    // This is available as an option for developers to pass in their own
    // favorite $conf overrides (e.g. disabling css aggregation).
    $conf_inject = $this->conf_inject;
    $cgi_env['RUNSERVER_CONF'] = urlencode(serialize($conf_inject));

    // We pass in the specified user (if set) - should be a fully loaded user
    // object. This will automatically log this user in the browser during the
    // first request (but not subsequent requests, to allow logging out).
    if (!empty($this->user) && $this->user->uid && $this->first_request_complete !== TRUE) {
      $this->first_request_complete = TRUE;
      $cgi_env['RUNSERVER_USER'] = urlencode(serialize($this->user));
    }

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
    drush_print(dt('HTTP server listening on !addr, port !port (see http://!hostname:!port/), serving site !site...', array('!addr' => $this->addr, '!hostname' => $this->hostname, '!port' => $this->port, '!site' => $this->site)));
    if (!empty($this->path)) {
      drush_start_browser($this->path);
    }
  }

  /**
   * Override request done event.
   */
  function request_done($request) {
    drush_print(trim($this->get_log_line($request), "\n"));
    $headers = $request->response->headers;
    if ($this->watchdog && isset($headers['X-Runserver-Watchdog'])) {
      $results = unserialize(urldecode($headers['X-Runserver-Watchdog']));
      foreach ($results as $result) {
        $result = (object)$result;
        $result->uid = $result->user->uid;
        $result = core_watchdog_format_result($result, TRUE);
        drush_print("Watchdog: {$result->date} ({$result->severity}, {$result->type}, {$result->user->name}) {$result->message}", 2);
      }
    }
    if ($this->debug) {
      drush_print_r($request);
    }
  }
}
