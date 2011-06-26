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
define('DRUSH_HTTPSERVER_VERSION', '354b9142bf0cfd73063e28604d11288304531cd6');

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
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'arguments' => array(
      'addr:port' => 'Host IP address and port number to bind to (default 127.0.0.1:8888). The IP is optional, in which case just pass in the numeric port.',
    ),
    'options' => array(
      'php-cgi' => 'Name of the php-cgi binary. If it is not on your current $PATH you should include the full path. You can include command line parameters to pass into php-cgi.',
      'conf-inject' => 'Key-value array of variables to override in the $conf array for the running site. By default disables drupal_http_request_fails to avoid errors on Windows (which supports only one connection at a time). Note that as this is a key-value array, it can only be specified in a drushrc or alias file, and not on the command line.',
    ),
    'aliases' => array('rs'),
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
function drush_core_runserver($addrport = '8888') {
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
  
  // Determine configuration.
  if (is_numeric($addrport)) {
    $addr = '127.0.0.1';
    $port = $addrport;
  }
  else {
    $addrport = explode(':', $addrport);
    if (count($addrport) !== 2 && is_numeric($addrport[1])) {
      return drush_set_error('RUNSERVER_INVALID_ADDRPORT', dt('Invalid address/port argument - should be either numeric (port only), or in the "host:port" format..'));
    }
    $addr = $addrport[0];
    $port = $addrport[1];
  }
  
  // We delete any registered files here, since they are not caught by Ctrl-C.
  _drush_delete_registered_files();
  
  // Create a new server instance and start it running.
  $server = new DrupalServer(array(
    'addr' => $addr,
    'port' => $port,
    'serverid' => 'Drush runserver',
    'php_cgi' => drush_get_option('php-cgi', 'php-cgi') . ' --define auto_prepend_file="' . DRUSH_BASE_PATH . '/commands/runserver/runserver-prepend.php"',
  ));
  $server->run_forever();
}
