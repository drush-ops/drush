#!/usr/bin/env php
<?php

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 5.3.0, or newer.
 */

require dirname(__FILE__) . '/includes/preflight.inc';

if (drush_preflight_prepare() === FALSE) {
  exit(1);
}
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
  $return = '';
  // Start code coverage collection.
  if ($coverage_file = drush_get_option('drush-coverage', FALSE)) {
    drush_set_context('DRUSH_CODE_COVERAGE', $coverage_file);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    register_shutdown_function('drush_coverage_shutdown');
  }

  /* Set up bootstrap object, so that
   * - 'early' files can bootstrap when needed.
   * - bootstrap constants are available.
   */
  $bootstrap_class = drush_get_option('bootstrap_class', 'Drush\Boot\DrupalBoot');
  $bootstrap = new $bootstrap_class;
  drush_set_context('DRUSH_BOOTSTRAP_OBJECT', $bootstrap);
  $bootstrap->preflight();

  // Process initial global options such as --debug.
  _drush_preflight_global_options();

  $return = '';
  drush_preflight();
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
        $bootstrap = drush_get_context('DRUSH_BOOTSTRAP_OBJECT');
        $return = $bootstrap->bootstrap_and_dispatch();
      }
    }
  }
  drush_postflight();

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
}
