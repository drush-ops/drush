#!/usr/bin/env php
<?php

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 5.2.0, or newer.
 */

require(dirname(__FILE__) . '/includes/bootstrap.inc');

drush_bootstrap_prepare();
exit(drush_main());

/**
 * The main Drush function.
 *
 * - Parses the command line arguments, configuration files and environment.
 * - Prepares and executes a Drupal bootstrap, if possible,
 * - Dispatches the given command.
 *
 * function_exists('drush_main') may be used by modules to detect whether
 * they are being called from drush.  See http://drupal.org/node/1181308
 *
 * @return
 *   Whatever the given command returns.
 */
function drush_main() {
  $phases = _drush_bootstrap_phases(FALSE, TRUE);

  $return = '';
  $command_found = FALSE;

  foreach ($phases as $phase) {
    if (drush_bootstrap_to_phase($phase)) {
      // If applicable swaps in shell alias value (or executes it).
      drush_shell_alias_replace();

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

  drush_bootstrap_finish();

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
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
