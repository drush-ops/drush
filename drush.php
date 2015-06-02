#!/usr/bin/env php
<?php

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 5.4.5, or newer.
 */

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
 * they are being called from Drush.  See http://drupal.org/node/1181308
 * and http://drupal.org/node/827478
 *
 * @return mixed
 *   Whatever the given command returns.
 */
function drush_main() {
  // Load Drush core include files, and parse command line arguments.
  require dirname(__FILE__) . '/includes/preflight.inc';
  if (drush_preflight_prepare() === FALSE) {
    return(1);
  }
  // Start code coverage collection.
  if ($coverage_file = drush_get_option('drush-coverage', FALSE)) {
    drush_set_context('DRUSH_CODE_COVERAGE', $coverage_file);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    register_shutdown_function('drush_coverage_shutdown');
  }

  // Load the global Drush configuration files, and global Drush commands.
  // Find the selected site based on --root, --uri or cwd
  // Preflight the selected site, and load any configuration and commandfiles associated with it.
  // Select and return the bootstrap class.
  $bootstrap = drush_preflight();

  // Reset our bootstrap phase to the beginning
  drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE);

  $return = '';
  if (!drush_get_error()) {
    if ($file = drush_get_option('early', FALSE)) {
      require_once $file;
      $function = 'drush_early_' . basename($file, '.inc');
      if (function_exists($function)) {
        if ($return = $function()) {
          // If the function returns FALSE, we continue and attempt to bootstrap
          // as normal. Otherwise, we exit early with the returned output.
          if ($return === TRUE) {
            $return = '';
          }
        }
      }
    }
    else {
      // Do any necessary preprocessing operations on the command,
      // perhaps handling immediately.
      $command_handled = drush_preflight_command_dispatch();
      if (!$command_handled) {
        $return = $bootstrap->bootstrap_and_dispatch();
      }
    }
  }
  // TODO: Get rid of global variable access here, and just trust
  // the bootstrap object returned from drush_preflight().  This will
  // require some adjustments to Drush bootstrapping.
  // See: https://github.com/drush-ops/drush/pull/1303
  if ($bootstrap = drush_get_bootstrap_object()) {
    $bootstrap->terminate();
  }
  drush_postflight();

  // How strict are we?  If we are very strict, turn 'ok' into 'error'
  // if there are any warnings in the log.
  if (($return == 0) && (drush_get_option('strict') > 1) && drush_log_has_errors()) {
    $return = 1;
  }

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
}
