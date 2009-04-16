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

define('DRUSH_BASE_PATH', dirname(__FILE__));
require_once DRUSH_BASE_PATH . '/includes/environment.inc';
require_once DRUSH_BASE_PATH . '/includes/command.inc';
require_once DRUSH_BASE_PATH . '/includes/drush.inc';
require_once DRUSH_BASE_PATH . '/includes/backend.inc';
require_once DRUSH_BASE_PATH . '/includes/context.inc';

drush_set_context('argc', $GLOBALS['argc']);
drush_set_context('argv', $GLOBALS['argv']);
exit(drush_main());

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
  return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
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
function drush_main() {
  $phases = _drush_bootstrap_phases();

  foreach ($phases as $phase) {
    if (drush_bootstrap($phase)) {
      $command = drush_parse_command();
      if (is_array($command)) {
        if ($command['bootstrap'] == $phase) {
          drush_log(dt("Found command: !command", array('!command' => $command['command'])), 'bootstrap');
          // Dispatch the command(s).
          // After this point the drush_shutdown function will run,
          // exiting with the correct exit code.
          return drush_dispatch($command);
        }
      }
    }
    else {
      break;
    }
  }
  $args = implode(" ", drush_get_arguments());
  // If we reach this point, we have not found a valid command.
  drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The command 'drush.php !args' could not be executed.", array('!args' => $args)));
}

/**
 * Shutdown function for use while Drupal is bootstrapping and to return any
 * registered errors.
 *
 * The shutdown command checks whether certain options are set to reliably
 * detect and log some common Drupal initialization errors.
 *
 * If the command is being executed with the --backend option, the script
 * will return a json string containing the options and log information
 * used by the script.
 * 
 * The command will exit with '1' if it was succesfully executed, and the 
 * result of drush_get_error() if it wasn't.
 */
function drush_shutdown() {
  // Mysteriously make $user available during sess_write(). Avoids a NOTICE.
  global $user; 
  
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if (drush_get_context('DRUSH_BOOTSTRAPPING')) {
    switch ($phase) {
      case DRUSH_BOOTSTRAP_DRUPAL_FULL :
        ob_end_clean();
        _drush_log_drupal_messages();
        drush_set_error('DRUSH_DRUPAL_BOOTSTRAP_ERROR');
        break;
    }
  }

  if (drush_get_context('DRUSH_BACKEND')) {
    drush_backend_output();
  }

  exit((drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS);
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
  if (drush_drupal_major_version() >= 7) {
    $user = is_numeric($drush_user) ? user_load($drush_user) : user_load_by_name($drush_user);
  }
  else {
    $user = user_load(is_numeric($drush_user) ? array('uid' => $drush_user) : array('name' => $drush_user));
  }

  if (empty($user)) {
    if (is_numeric($drush_user)) {
      $message = dt('Could not login with user ID #%user.', array('%user' => $drush_user));
    }
    else {
      $message = dt('Could not login with user account `%user\'.', array('%user' => $drush_user));
    }
    return drush_set_error('DRUPAL_USER_LOGIN_FAILED', $message);
  }

  return TRUE;
}

