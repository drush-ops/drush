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
 * - Runs "early" option code, if set (see global options).
 * - Parses the command line arguments, configuration files and environment.
 * - Prepares and executes a Drupal bootstrap, if possible,
 * - Dispatches the given command.
 *
 * function_exists('drush_main') may be used by modules to detect whether
 * they are being called from drush.  See http://drupal.org/node/1181308
 * and http://drupal.org/node/827478
 *
 * @return
 *   Whatever the given command returns.
 */
function drush_main() {
  $return = '';
  if ($file = drush_get_option('early', FALSE)) {
    require_once($file);
    $function = 'drush_early_' . basename($file, '.inc');
    if (function_exists($function)) {
      if ($return = $function()) {
        // If the function returns FALSE, we continue and attempt to bootstrap
        // as normal. Otherwise, we exit early with the returned output.
        if ($return === TRUE) {
          $return = '';
        }
        drush_bootstrap_finish();
        return $return;
      }
    }
  }

  // Process initial global options such as --debug.
  _drush_bootstrap_global_options();

  $return = '';
  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUSH);
  if (!drush_get_error()) {
    // Process a remote command if 'remote-host' option is set.
    $command_handled = drush_remote_command();
    if (!$command_handled) {
      $return = _drush_bootstrap_and_dispatch();
    }
  }
  drush_bootstrap_finish();

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
}

function _drush_bootstrap_and_dispatch() {
  $phases = _drush_bootstrap_phases(FALSE, TRUE);

  $return = '';
  $command_found = FALSE;
  _drush_bootstrap_output_prepare();
  foreach ($phases as $phase) {
    if (drush_bootstrap_to_phase($phase)) {
      $command = drush_parse_command();
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
    // Set errors that occurred in the bootstrap phases.
    $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
    foreach ($errors as $code => $message) {
      drush_set_error($code, $message);
    }
  }
  return $return;
}
