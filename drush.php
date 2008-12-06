#!/usr/bin/env php
<?php
// $Id$

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 4.3.0, PHP CLI 5.x, or newer.
 */

// Terminate immediately unless invoked as a command line script
if (!drush_verify_cli()) {
  die('drush.php is designed to run via the command line.');
}

require_once dirname(__FILE__) . '/includes/environment.inc';
require_once dirname(__FILE__) . '/includes/command.inc';
require_once dirname(__FILE__) . '/includes/drush.inc';

exit(drush_bootstrap($GLOBALS['argc'], $GLOBALS['argv']));

/**
 * Verify that we are running PHP through the command line interface.
 *
 * This function is useful for making sure that code cannot be run via the web server,
 * such as a function that needs to write files to which the web server should not have
 * access to.
 *
 * @return
 *   A boolean value that is true when PHP is being run through the command line,
 *   and false if being run through cgi or mod_php.
 */
function drush_verify_cli() {
  if (php_sapi_name() == 'cgi') {
    return (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0);
  }

  return (php_sapi_name() == 'cli');
}

/**
 * The main Drush function.
 *
 * - Parses the command line arguments, configuration files and environment.
 * - Prepares and executes a Drupal bootstrap, if possible,
 * - Dispatches the given command.
 *
 * @return
 *   Whatever the given command returns.
 */
function drush_bootstrap($argc, $argv) {
  // Parse command line options and arguments.
  $GLOBALS['args'] = drush_parse_args($argv, array('c', 'h', 'u', 'r', 'l', 'i'));

  $path = drush_cwd();

  // Load available .drushrc file(s). Allows you to provide defaults for options and variables.
  drush_load_config($path);

  // Define basic options as constants.
  define('DRUSH_VERBOSE',     drush_get_option(array('v', 'verbose'), FALSE));
  define('DRUSH_AFFIRMATIVE', drush_get_option(array('y', 'yes'), FALSE));
  define('DRUSH_SIMULATE',    drush_get_option(array('s', 'simulate'), FALSE));

  define('DRUSH_URI',         drush_get_option(array('l', 'uri'), drush_site_uri($path)));
  define('DRUSH_USER',        drush_get_option(array('u', 'user'), 0));

  // Preliminary check on command descriptor
  list($command, $arguments) = drush_parse_command($GLOBALS['args']['commands']);

  if ($drupal_root = drush_get_option(array('r', 'root'), drush_locate_root($path))) {

    drush_drupal_set_environment($drupal_root);

    // Bootstrap Drupal.
    if (drush_drupal_bootstrap($drupal_root, $command['bootstrap'])) {
      /**
       * Allow the drushrc.php file to override $conf settings.
       * This is a separate variable because the $conf array gets initialized to an empty array,
       * in the drupal bootstrap process, and changes in settings.php would wipe out the drushrc.php
       * settings
       */
      if (is_array($GLOBALS['override'])) {
        $GLOBALS['conf'] = array_merge($GLOBALS['conf'], $GLOBALS['override']);
      }

      // Login the specified user (if given).
      if (DRUSH_USER) {
        drush_drupal_login(DRUSH_USER);
      }

      // Now we can use all of Drupal.
    }
  }

  if (DRUSH_SIMULATE) {
    drush_print('SIMULATION MODE IS ENABLED. NO ACTUAL ACTION WILL BE TAKEN. SYSTEM WILL REMAIN UNCHANGED.');
  }

  // Dispatch the command(s).
  $output = drush_dispatch($GLOBALS['args']['commands']);

  // prevent a '1' at the end of the outputs
  if ($output === TRUE) {
    $output = '';
  }

  // TODO: Terminate with the correct exit status.
  return $output;
}

/**
 * Sets up various constants and $_SERVER entries used by
 * Drupal, essentially mimicking a webserver environment.
 *
 * @param string
 *   Path to Drupal installation root.
 */
function drush_drupal_set_environment($drupal_root) {
  define('DRUSH_DRUPAL_ROOT', $drupal_root);
  // Possibly temporary. See http://drupal.org/node/312421.
  define('DRUPAL_ROOT', DRUSH_DRUPAL_ROOT);

  // Fake the necessary HTTP headers that Drupal needs:
  if (DRUSH_URI) {
    $drupal_base_url = parse_url(DRUSH_URI);
    $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];
    $_SERVER['PHP_SELF'] = $drupal_base_url['path'] . '/index.php';
  }
  else {
    $_SERVER['HTTP_HOST'] = 'default';
    $_SERVER['PHP_SELF'] = '/index.php';
  }
  $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  $_SERVER['REQUEST_METHOD']  = NULL;
  $_SERVER['SERVER_SOFTWARE'] = NULL;
  $_SERVER['HTTP_USER_AGENT'] = NULL;
}

/**
 * Shutdown function for use while Drupal is bootstrapping.
 */
function drush_shutdown() {
  if (!defined('DRUSH_DRUPAL_BOOTSTRAPPED')) {
    $site_path = drush_site_path();
    $message = "E: Drush was not able to start (bootstrap) Drupal.\n";
    $message .= "Hint: This error often occurs when Drush is trying to bootstrap a site\n";
    $message .= "that has not been installed or does not have a configured database.\n";
    $message .= "Drush was looking for the site in '$site_path'. You can select another site\n";
    $message .= "with a working database setup by specifying the URI to use with the --uri\n";
    $message .= "parameter on the command line or \$options['uri'] in your drushrc.php file.\n";
    die($message);
  }
}

/**
 * Bootstrap Drupal.
 *
 * @param string
 *   path to Drupal installation root.
 *
 * @param mixed
 *   NULL for a full bootstrap or any of the Drupal bootstrap sequence constants.
 *   These depend on the Drupal major version.
 *
 * @return
 *   TRUE if Drupal successfully bootstrapped to the given state.
 */
function drush_drupal_bootstrap($drupal_root, $bootstrap = NULL) {
  // Change to Drupal root dir.
  chdir($drupal_root);

  require_once DRUSH_DRUPAL_BOOTSTRAP;

  if (($conf_path = conf_path()) && !file_exists("./$conf_path/settings.php")) {
    return FALSE;
  }

  // The bootstrap can fail silently, so we catch that in a shutdown function.
  register_shutdown_function('drush_shutdown');
  if (is_null($bootstrap)) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  }
  else {
    drupal_bootstrap($bootstrap);
  }

  // The bootstrap succeeded.
  define('DRUSH_DRUPAL_BOOTSTRAPPED', TRUE);
  return TRUE;
}

/**
 * Log the given user in to a bootstrapped Drupal site.
 *
 * @param mixed
 *   Numeric user id or user name.
 *
 * @return boolean
 *   TRUE if user was logged in, otherwise FALSE.
 */
function drush_drupal_login($drush_user) {
  global $user;
  $user = module_invoke('user', 'load', is_numeric($drush_user) ? array('uid' => $drush_user) : array('name' => $drush_user));

  if (empty($user)) {
    if (is_numeric($drush_user)) {
      drush_die(dt('Could not login with user ID #%user.', array('%user' => $drush_user)));
    }
    else {
      drush_die(dt('Could not login with user account `%user\'.', array('%user' => $drush_user)));
    }
    return FALSE;
  }

  return TRUE;
}
