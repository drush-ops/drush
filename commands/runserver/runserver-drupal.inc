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
  public $http_host;

  /**
   * This is the equivalent of .htaccess, passing requests to files if they
   * exist, and all other requests to index.php. We also set a number
   * of CGI environment variables here.  
   */
  function route_request($request) {
    $cgi_env = array();

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

    // We pass in the effective base_url to our auto_prepend_script via the cgi
    // environment. This allows Drupal to generate working URLs to this http
    // server, whilst finding the correct multisite from the HTTP_HOST header.
    $cgi_env['RUNSERVER_BASE_URL'] = 'http://localhost:' . $this->port;
    
    // We pass in an array of $conf overrides using the same approach.
    // By default we set drupal_http_request_fails to FALSE, as the httpserver
    // is unable to process simultanious requests on some systems.
    // This is available as an option for developers to pass in their own
    // favorite $conf overrides (e.g. disabling css aggregation). 
    $conf_inject = drush_get_option('conf-inject', array('drupal_http_request_fails' => FALSE));
    $cgi_env['RUNSERVER_CONF'] = urlencode(serialize($conf_inject));

    // Rewrite clean-urls
    $cgi_env['QUERY_STRING'] = 'q=' . ltrim($uri, '/');
    if ($request->query_string != "") {
      $cgi_env['QUERY_STRING'] .= '&' . $request->query_string;
    }

    $cgi_env['SCRIPT_NAME'] = '/index.php';
    $cgi_env['HTTP_HOST'] = $cgi_env['SERVER_NAME'] = $this->http_host;

    return $this->get_php_response($request, $doc_root . '/index.php', $cgi_env);
  }
}
