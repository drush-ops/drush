#!/usr/bin/env php
<?php

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 5.2.0, or newer.
 */
// Terminate immediately unless invoked as a command line script
if (!drush_verify_cli()) {
  die('drush is designed to run via the command line.');
}

// Check supported version of PHP.
define('DRUSH_MINIMUM_PHP', '5.2.0');
if (version_compare(phpversion(), DRUSH_MINIMUM_PHP) < 0) {
  die('Your command line PHP installation is too old. Drush requires at least PHP ' . DRUSH_MINIMUM_PHP . "\n");
}

define('DRUSH_BASE_PATH', dirname(__FILE__));


define('DRUSH_REQUEST_TIME', microtime(TRUE));

require_once DRUSH_BASE_PATH . '/includes/environment.inc';
require_once DRUSH_BASE_PATH . '/includes/command.inc';
require_once DRUSH_BASE_PATH . '/includes/drush.inc';
require_once DRUSH_BASE_PATH . '/includes/backend.inc';
require_once DRUSH_BASE_PATH . '/includes/batch.inc';
require_once DRUSH_BASE_PATH . '/includes/context.inc';
require_once DRUSH_BASE_PATH . '/includes/sitealias.inc';

drush_set_context('argc', $GLOBALS['argc']);
drush_set_context('argv', $GLOBALS['argv']);

// Set an error handler and a shutdown function
set_error_handler('drush_error_handler');
register_shutdown_function('drush_shutdown');

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
  $phases = _drush_bootstrap_phases(FALSE, TRUE);
  drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE);

  // We need some global options processed at this early stage. Namely --debug.
  drush_parse_args();
  _drush_bootstrap_global_options();

  $return = '';
  $command_found = FALSE;

  foreach ($phases as $phase) {
    if (drush_bootstrap_to_phase($phase)) {
      $command = drush_parse_command();

      // Process a remote command if 'remote-host' option is set.
      if (drush_remote_command()) {
        $command_found = TRUE;
        break;
      }

      if (is_array($command)) {
        $bootstrap_result = drush_bootstrap_to_phase($command['bootstrap']);
        drush_enforce_requirement_bootstrap_phase($command);
        drush_enforce_requirement_core($command);
        drush_enforce_requirement_drupal_dependencies($command);
        drush_enforce_requirement_drush_dependencies($command);

        if ($bootstrap_result && empty($command['bootstrap_errors'])) {
          drush_log(dt("Found command: !command (commandfile=!commandfile)", array('!command' => $command['command'], '!commandfile' => $command['commandfile'])), 'bootstrap');

          $command_found = TRUE;
          // Dispatch the command(s).
          $return = drush_dispatch($command);

          // prevent a '1' at the end of the output
          if ($return === TRUE) {
            $return = '';
          }

          if (drush_get_context('DRUSH_DEBUG') && !drush_get_context('DRUSH_QUIET')) {
            drush_print_timers();
          }
          drush_log(dt('Peak memory usage was !peak', array('!peak' => drush_format_size(memory_get_peak_usage()))), 'memory');
          break;
        }
      }
    }
    else {
      break;
    }
  }

  if (!$command_found) {
    // If we reach this point, we have not found either a valid or matching command.
    $args = implode(' ', drush_get_arguments());
    if (isset($command) && is_array($command)) {
      foreach ($command['bootstrap_errors'] as $key => $error) {
        drush_set_error($key, $error);
      }
      drush_set_error('DRUSH_COMMAND_NOT_EXECUTABLE', dt("The drush command '!args' could not be executed.", array('!args' => $args)));
    }
    elseif (!empty($args)) {
      drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The drush command '!args' could not be found.", array('!args' => $args)));
    }
    // Set errors that ocurred in the bootstrap phases.
    $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
    foreach ($errors as $code => $message) {
      drush_set_error($code, $message);
    }
  }

  // We set this context to let the shutdown function know we reached the end of drush_main();
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
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
 * The command will exit with '1' if it was successfully executed, and the
 * result of drush_get_error() if it wasn't.
 */
function drush_shutdown() {
  // Mysteriously make $user available during sess_write(). Avoids a NOTICE.
  global $user;

  if (!drush_get_context('DRUSH_EXECUTION_COMPLETED', FALSE) && !drush_get_context('DRUSH_USER_ABORT', FALSE)) {
    $php_error_message = '';
    if ($error = error_get_last()) {
      $php_error_message = "\n" . dt('Error: !message in !file, line !line', array('!message' => $error['message'], '!file' => $error['file'], '!line' => $error['line']));
    }
    // We did not reach the end of the drush_main function,
    // this generally means somewhere in the code a call to exit(),
    // was made. We catch this, so that we can trigger an error in
    // those cases.
    drush_set_error("DRUSH_NOT_COMPLETED", dt("Drush command terminated abnormally due to an unrecoverable error.!message", array('!message' => $php_error_message)));
    // Attempt to give the user some advice about how to fix the problem
    _drush_postmortem();
  }

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
  elseif (drush_get_context('DRUSH_QUIET')) {
    ob_end_clean();
  }

  // If we are in pipe mode, emit the compact representation of the command, if available.
  if (drush_get_context('DRUSH_PIPE')) {
    drush_pipe_output();
  }

  /**
   * For now, drush skips end of page processing on D7. Doing so could write
   * cache entries to module_implements and lookup_cache that don't match web requests.
   */
  // if (drush_drupal_major_version() >= 7 && function_exists('drupal_page_footer')) {
    // drupal_page_footer();
  // }

  // this way drush_return_status will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
  // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
  register_shutdown_function('drush_return_status');
}

function drush_return_status() {
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
      $message = dt('Could not login with user ID #!user.', array('!user' => $drush_user));
      if ($drush_user === 0) {
        $message .= ' ' . dt('This is typically caused by importing a MySQL database dump from a faulty tool which re-numbered the anonymous user ID in the users table. See !link for help recovering from this situation.', array('!link' => 'http://drupal.org/node/1029506'));
      }
    }
    else {
      $message = dt('Could not login with user account `!user\'.', array('!user' => $drush_user));
    }
    return drush_set_error('DRUPAL_USER_LOGIN_FAILED', $message);
  }
  else {
    $name = $user->name ? $user->name : variable_get('anonymous', t('Anonymous'));
    drush_log(dt('Successfully logged into Drupal as !name', array('!name' => $name . " (uid=$user->uid)")), 'bootstrap');
  }

  return TRUE;
}
