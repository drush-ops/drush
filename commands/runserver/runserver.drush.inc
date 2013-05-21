<?php

/**
 * @file
 *   Built in http server commands.
 *
 *   This uses an excellent php http server library developed by Jesse Young
 *   with support from the Envaya project.
 *   See https://github.com/youngj/httpserver/ and http://envaya.org/.
 */

/**
 * Supported version of httpserver. This is displayed in the manual install help.
 */
define('DRUSH_HTTPSERVER_VERSION', '41dd2b7160b8cbd25d7b5383e3ffc6d8a9a59478');

/**
 * Directory name for httpserver. This is displayed in the manual install help.
 */
define('DRUSH_HTTPSERVER_DIR_BASE', 'youngj-httpserver-');

/**
 * Base URL for automatic download of supported version of httpserver.
 */
define('DRUSH_HTTPSERVER_BASE_URL', 'https://github.com/youngj/httpserver/tarball/');

/**
 * Implementation of hook_drush_help().
 */
function runserver_drush_help($section) {
  switch ($section) {
    case 'meta:runserver:title':
      return dt("Runserver commands");
    case 'drush:runserver':
      return dt("Runs a lightweight built in http server for development.
 - Don't use this for production, it is neither scalable nor secure for this use.
 - If you run multiple servers simultaneously, you will need to assign each a unique port.
 - Use Ctrl-C or equivalent to stop the server when complete.");
  }
}

/**
 * Implementation of hook_drush_command().
 */
function runserver_drush_command() {
  $items = array();

  $items['runserver'] = array(
    'description' => 'Runs a lightweight built in http server for development.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'arguments' => array(
      'addr:port/path' => 'Host IP address and port number to bind to and path to open in web browser. Format is addr:port/path, default 127.0.0.1:8888, all elements optional. See examples for shorthand.',
    ),
    'options' => array(
      'server' => 'Which http server to use - either: "cgi" for a CGI based httpserver (default, requires php 5.3 and php-cgi binary) or "builtin" for php 5.4 built in http server.',
      'php-cgi' => 'Name of the php-cgi binary. If it is not on your current $PATH you should include the full path. You can include command line parameters to pass into php-cgi.',
      'variables' => 'Key-value array of variables to override in the $conf array for the running site. By default disables drupal_http_request_fails to avoid errors on Windows (which supports only one connection at a time). Comma delimited list of name=value pairs (or array in drushrc).',
      'default-server' => 'A default addr:port/path to use for any values not specified as an argument.',
      'user' => 'If opening a web browser, automatically log in as this user (user ID or username).',
      'browser' => 'If opening a web browser, which browser to user (defaults to operating system default).',
      'dns' => 'Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.',
    ),
    'aliases' => array('rs'),
    'examples' => array(
      'drush rs 8080' => 'Start runserver on 127.0.0.1, port 8080.',
      'drush rs 10.0.0.28:80' => 'Start runserver on 10.0.0.28, port 80.',
      'drush rs [::1]:80' => 'Start runserver on IPv6 localhost ::1, port 80.',
      'drush rs --php-cgi=php5-cgi --dns localhost:8888/user' => 'Start runserver on localhost (using rDNS to determine binding IP), port 8888, and open /user in browser. Use "php5-cgi" as the php-cgi binary.',
      'drush rs /' => 'Start runserver on default IP/port (127.0.0.1, port 8888), and open / in browser.',
      'drush rs --default-server=127.0.0.1:8080/ -' => 'Use a default (would be specified in your drushrc) that starts runserver on port 8080, and opens a browser to the front page. Set path to a single hyphen path in argument to prevent opening browser for this session.',
      'drush rs --server=builtin :9000/admin' => 'Start builtin php 5.4 runserver on 127.0.0.1, port 9000, and open /admin in browser. Note that you need a colon when you specify port and path, but no IP.',
    ),
  );
  return $items;
}

/**
 * Validate callback for runserver command.
 */
function drush_core_runserver_validate() {
  $php_cgi = drush_shell_exec('which ' . drush_get_option('php-cgi', 'php-cgi'));
  $builtin = version_compare(PHP_VERSION, '5.4.0') >= 0;
  $server = drush_get_option('server', FALSE);
  if (!$server) {
    // No server specified, try and find a valid server option, preferring cgi.
    if ($php_cgi) {
      $server = 'cgi';
      drush_set_option('server', 'cgi');
    }
    else if ($builtin) {
      $server = 'builtin';
      drush_set_option('server', 'builtin');
    }
    else {
      return drush_set_error('RUNSERVER_PHP_CGI54', dt('The runserver command requires either the php-cgi binary, or php 5.4 (or higher). Neither could not be found.'));
    }
  }
  else if ($server == 'cgi' && !$php_cgi) {
    // Validate user specified cgi server option.
    return drush_set_error('RUNSERVER_PHP_CGI', dt('The runserver command with the "cgi" server requires the php-cgi binary, which could not be found.'));
  }
  else if ($server == 'builtin' && !$builtin) {
    // Validate user specified builtin server option.
    return drush_set_error('RUNSERVER_PHP_CGI', dt('The runserver command with the "builtin" server requires php 5.4 (or higher), which could not be found.'));
  }

  // Update with detected configuration.
  $server = drush_get_option('server', FALSE);
  if ($server == 'cgi') {
    // Fetch httpserver cgi based server to our /lib directory, if needed.
    $lib = drush_get_option('lib', DRUSH_BASE_PATH . '/lib');
    $httpserverfile = $lib . '/' . DRUSH_HTTPSERVER_DIR_BASE . substr(DRUSH_HTTPSERVER_VERSION, 0, 7) . '/httpserver.php';
    if (!drush_file_not_empty($httpserverfile)) {
      // Download and extract httpserver, and confirm success.
      drush_lib_fetch(DRUSH_HTTPSERVER_BASE_URL . DRUSH_HTTPSERVER_VERSION);
      if (!drush_file_not_empty($httpserverfile)) {
        // Something went wrong - the library is still not present.
        return drush_set_error('RUNSERVER_HTTPSERVER_LIB_NOT_FOUND', dt("The runserver command needs a copy of the httpserver library in order to function, and the attempt to download this file automatically failed. To continue you will need to download the package from !url, extract it into the !lib directory, such that httpserver.php exists at !httpserverfile.", array('!version' => DRUSH_HTTPSERVER_VERSION, '!url' => DRUSH_HTTPSERVER_BASE_URL . DRUSH_HTTPSERVER_VERSION, '!httpserverfile' => $httpserverfile, '!lib' => $lib)));
      }
    }
  }

  // Check we have a valid server.
  if (!in_array($server, array('cgi', 'builtin'))) {
    return drush_set_error('RUNSERVER_INVALID', dt('Invalid server specified.'));
  }
}

/**
 * Callback for runserver command.
 */
function drush_core_runserver($uri = NULL) {
  global $user, $base_url;

  // Determine active configuration.
  $drush_default = array(
    'host' => '127.0.0.1',
    'port' => '8888',
    'path' => '',
  );
  $user_default = runserver_parse_uri(drush_get_option('default-server', '127.0.0.1:8888'));
  $uri = runserver_parse_uri($uri);
  if (!$uri) {
    return $uri;
  }
  // Populate defaults.
  $uri = $uri + $user_default + $drush_default;
  if (ltrim($uri['path'], '/') == '-') {
    // Allow a path of a single hyphen to clear a default path.
    $uri['path'] = '';
  }
  // Remove any leading slashes from the path, since that is what url() expects.
  $path = ltrim($uri['path'], '/');

  // Determine and set the new URI.
  $hostname = $addr = $uri['host'];
  if (drush_get_option('dns', FALSE)) {
    if (ip2long($hostname)) {
      $hostname = gethostbyaddr($hostname);
    }
    else {
      $addr = gethostbyname($hostname);
    }
  }
  drush_set_context('DRUSH_URI', 'http://' . $hostname . ':' . $uri['port']);


  // We pass in the currently logged in user (if set via the --user option),
  // which will automatically log this user in the browser during the first
  // request.
  if (drush_get_option('user', FALSE)) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);
  }

  // We delete any registered files here, since they are not caught by Ctrl-C.
  _drush_delete_registered_files();

  // We set the effective base_url, since we have now detected the current site,
  // and need to ensure generated URLs point to our runserver host.
  // We also pass in the effective base_url to our auto_prepend_script via the
  // CGI environment. This allows Drupal to generate working URLs to this http
  // server, whilst finding the correct multisite from the HTTP_HOST header.
  $base_url = 'http://' . $addr . ':' . $uri['port'];
  $env['RUNSERVER_BASE_URL'] = $base_url;

  // We pass in an array of $conf overrides using the same approach.
  // By default we set drupal_http_request_fails to FALSE, as the httpserver
  // is unable to process simultaneous requests on some systems.
  // This is available as an option for developers to pass in their own
  // favorite $conf overrides (e.g. disabling css aggregation).
  $current_override = drush_get_option_list('variables', array('drupal_http_request_fails' => FALSE));
  foreach ($current_override as $name => $value) {
    if (is_numeric($name) && (strpos($value, '=') !== FALSE)) {
      list($name, $value) = explode('=', $value, 2);
    }
    $override[$name] = $value;
  }
  $env['RUNSERVER_CONF'] = urlencode(serialize($override));

  // We log in with the specified user ID (if set) via the password reset URL.
  $user_message = '';
  if ($user->uid) {
    $options = array('query' => array('destination' => $path));
    $browse = url(user_pass_reset_url($user) . '/login', $options);
    $user_message = ', logged in as ' . $user->name;
  }
  else {
    $browse = url($path);
  }

  drush_print(dt('HTTP server listening on !addr, port !port (see http://!hostname:!port/!path), serving site !site!user...', array('!addr' => $addr, '!hostname' => $hostname, '!port' => $uri['port'], '!path' => $path, '!site' => drush_get_context('DRUSH_DRUPAL_SITE', 'default'), '!user' => $user_message)));
  if (drush_get_option('server', FALSE) == 'builtin') {
    // Start php 5.4 builtin server.
    // Store data used by runserver-prepend.php in the shell environment.
    foreach ($env as $key => $value) {
      putenv($key . '=' . $value);
    }
    if (!empty($uri['path'])) {
      // Start a browser if desired. Include a 2 second delay to allow the
      // server to come up.
      drush_start_browser($browse, 2);
    }
    // Start the server using 'php -S'.
    $php = drush_get_option('php');
    drush_shell_exec_interactive($php . ' -S ' . $addr . ':' . $uri['port'] . ' --define auto_prepend_file="' . DRUSH_BASE_PATH . '/commands/runserver/runserver-prepend.php"');
  }
  else {
    // Include the library and our class that extends it.
    $lib = drush_get_option('lib', DRUSH_BASE_PATH . '/lib');
    $httpserverfile = $lib . '/' . DRUSH_HTTPSERVER_DIR_BASE . substr(DRUSH_HTTPSERVER_VERSION, 0, 7) . '/httpserver.php';
    require_once $httpserverfile;
    require_once 'runserver-drupal.inc';
    // Create a new httpserver instance and start it running.
    $server = new DrupalServer(array(
      'addr' => $addr,
      'port' => $uri['port'],
      'browse' => $browse,
      'hostname' => $hostname,
      'site' => drush_get_context('DRUSH_DRUPAL_SITE', 'default'),
      'server_id' => 'Drush runserver',
      'php_cgi' => drush_get_option('php-cgi', 'php-cgi') . ' --define auto_prepend_file="' . DRUSH_BASE_PATH . '/commands/runserver/runserver-prepend.php"',
      'env' => $env,
      'debug' => drush_get_context('DRUSH_DEBUG'),
    ));
    $server->run_forever();
  }
}

/**
 * Parse a URI or partial URI (including just a port, host IP or path).
 *
 * @param $uri
 *   String that can contain partial URI.
 * @return array
 *   URI array as returned by parse_url.
 */
function runserver_parse_uri($uri) {
  if ($uri[0] == ':') {
    // ':port/path' shorthand, insert a placeholder hostname to allow parsing.
    $uri = 'placeholder-hostname' . $uri;
  }
  // FILTER_VALIDATE_IP expects '[' and ']' to be removed from IPv6 addresses.
  // We check for colon from the right, since IPv6 addresses contain colons.
  $to_path = trim(substr($uri, 0, strpos($uri, '/')), '[]');
  $to_port = trim(substr($uri, 0, strrpos($uri, ':')), '[]');
  if (filter_var(trim($uri, '[]'), FILTER_VALIDATE_IP) || filter_var($to_path, FILTER_VALIDATE_IP) || filter_var($to_port, FILTER_VALIDATE_IP)) {
    // 'IP', 'IP/path' or 'IP:port' shorthand, insert a schema to allow parsing.
    $uri = 'http://' . $uri;
  }
  $uri = parse_url($uri);
  if (empty($uri)) {
    return drush_set_error('RUNSERVER_INVALID_ADDRPORT', dt('Invalid argument - should be in the "host:port/path" format, numeric (port only) or non-numeric (path only).'));
  }
  if (count($uri) == 1 && isset($uri['path'])) {
    if (is_numeric($uri['path'])) {
      // Port only shorthand.
      $uri['port'] = $uri['path'];
      unset($uri['path']);
    }
  }
  if (isset($uri['host']) && $uri['host'] == 'placeholder-hostname') {
    unset($uri['host']);
  }
  return $uri;
}
