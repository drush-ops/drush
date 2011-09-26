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
 - If you run multiple servers simultaniously, you will need to assign each a unique port.
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
      'php-cgi' => 'Name of the php-cgi binary. If it is not on your current $PATH you should include the full path. You can include command line parameters to pass into php-cgi.',
      'conf-inject' => 'Key-value array of variables to override in the $conf array for the running site. By default disables drupal_http_request_fails to avoid errors on Windows (which supports only one connection at a time). Note that as this is a key-value array, it can only be specified in a drushrc or alias file, and not on the command line.',
      'default-server' => 'A default addr:port/path to use for any values not specified as an argument.',
      'user' => 'If opening a web browser, automatically log in as this user (user ID or username)',
      'watchdog' => 'Collect and integrate watchdog messages from each request into the log',
      'dns' => 'Resolve hostnames/IPs using DNS/rDNS (if possible) to determine binding IPs and/or human friendly hostnames for URLs and browser.',
    ),
    'aliases' => array('rs'),
    'examples' => array(
      'drush rs 8080' => 'Start runserver on 127.0.0.1, port 8080.',
      'drush rs 10.0.0.28:80' => 'Start runserver on 10.0.0.28, port 80.',
      'drush rs --php-cgi=php5-cgi --dns localhost:8888/user' => 'Start runserver on localhost (using rDNS to determine binding IP), port 8888, and open /user in browser. Use "php5-cgi" as the php-cgi binary.',
      'drush rs /' => 'Start runserver on default IP/port (127.0.0.1, port 8888), and open / in browser.',
      'drush rs --default-server=127.0.0.1:8080/ -' => 'Use a default (would be specified in your drushrc) that starts runserver on port 8080, and opens a browser to the front page. Set path to a single hyphen path in argument to prevent opening browser for this session.',
      'drush rs --watchdog :9000/admin' => 'Start runserver on 127.0.0.1, port 9000, and open /admin in browser, including any watchdog messages for the session in the log. Note that you need a colon when you specify port and path, but no IP.',
    ),
  );
  return $items;
}

/**
 * Validate callback for runserver command.
 */
function drush_core_runserver_validate() {
  if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    return drush_set_error('RUNSERVER_PHP_VERSION', dt('The runserver command requires php 5.3, which could not be found.'));
  }
  if (!drush_shell_exec('which ' . drush_get_option('php-cgi', 'php-cgi'))) {
    return drush_set_error('RUNSERVER_PHP_CGI', dt('The runserver command requires the php-cgi binary, which could not be found.'));
  }
}

/**
 * Callback for runserver command.
 */
function drush_core_runserver($uri = NULL) {
  global $user;

  // Fetch httpserver to our /lib directory, if needed.
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

  // Include the library and our class that extends it.
  require_once $httpserverfile;
  require_once 'runserver-drupal.inc';

  // We pass in the currently logged in user (if set via the --user option),
  // which will automatically log this user in the browser during the first
  // request.
  if (drush_get_option('user', FALSE)) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);
  }

  // Determine active configuration.
  $drush_default = array(
    'host' => '127.0.0.1',
    'port' => '8888',
    'path' => '',
  );
  $user_default = runserver_parse_uri(drush_get_option('default-server', '127.0.0.1:8888'));
  $uri = runserver_parse_uri($uri) + $user_default + $drush_default;
  if (ltrim($uri['path'], '/') == '-') {
    // Allow a path of a single hyphen to clear a default path.
    $uri['path'] = '';
  }

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

  // We delete any registered files here, since they are not caught by Ctrl-C.
  _drush_delete_registered_files();

  // Create a new server instance and start it running.
  $server = new DrupalServer(array(
    'addr' => $addr,
    'port' => $uri['port'],
    'path' => $uri['path'],
    'hostname' => $hostname,
    'site' => drush_get_context('DRUSH_DRUPAL_SITE', 'default'),
    'server_id' => 'Drush runserver',
    'php_cgi' => drush_get_option('php-cgi', 'php-cgi') . ' --define auto_prepend_file="' . DRUSH_BASE_PATH . '/commands/runserver/runserver-prepend.php"',
    'conf_inject' => drush_get_option('conf-inject', array('drupal_http_request_fails' => FALSE)),
    'user' => $user,
    'watchdog' => drush_get_option('watchdog', FALSE),
    'debug' => drush_get_context('DRUSH_DEBUG'),
  ));
  $server->run_forever();
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
  $first_part = substr($uri, 0, strpos($uri, '/'));
  if (ip2long($first_part)) {
    // 'IP/path' shorthand, insert a schema to allow parsing.
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
    else if (ip2long($uri['path'])) {
      // IP only shorthand.
      $uri['host'] = $uri['path'];
      unset($uri['path']);
    }
  }
  if (isset($uri['host']) && $uri['host'] == 'placeholder-hostname') {
    unset($uri['host']);
  }
  return $uri;
}