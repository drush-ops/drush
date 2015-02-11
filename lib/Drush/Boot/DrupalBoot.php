<?php

namespace Drush\Boot;

class DrupalBoot implements Boot {

  function __construct() {
  }

  // @todo We could split this into separate classes for Drupal 8, and Drupal 7 + 6,
  // and give each one its own 'valid_root' implementation.  To do this, just add
  // another candidate in drush_preflight_get_bootstrap_candidates().
  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Drupal 8 root. Additional check for the presence of core/composer.json to
      // grant it is not a Drupal 7 site with a base folder named "core".
      $candidate = 'core/includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/misc/drupal.js') && file_exists($path . '/core/core.services.yml')) {
        return $candidate;
      }
      // Drupal 7 root.
      $candidate = 'includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js')) {
        return $candidate;
      }
    }
    return FALSE;
  }

  function preflight() {
    // We need our constants before commandfile searching like DRUSH_BOOTSTRAP_DRUPAL_LOGIN.
    require_once __DIR__ . '/bootstrap.inc';
    require_once __DIR__ . '/command.inc';

    drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE);
  }

  function command_defaults() {
    return array(
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    );
  }

  function bootstrap_and_dispatch() {
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

            // Prevent a '1' at the end of the output.
            if ($return === TRUE) {
              $return = '';
            }

            if (drush_get_context('DRUSH_DEBUG') && !drush_get_context('DRUSH_QUIET')) {
              // @todo Create version independant wrapper around Drupal timers. Use it.
              drush_print_timers();
            }
            break;
          }
        }
      }
      else {
        break;
      }
    }

    if (!$command_found) {
      // If we reach this point, command doesn't fit requirements or we have not
      // found either a valid or matching command.

      // If no command was found check if it belongs to a disabled module.
      if (!$command) {
        $command = drush_command_belongs_to_disabled_module();
      }

      // Set errors related to this command.
      $args = implode(' ', drush_get_arguments());
      if (isset($command) && is_array($command)) {
        foreach ($command['bootstrap_errors'] as $key => $error) {
          drush_set_error($key, $error);
        }
        drush_set_error('DRUSH_COMMAND_NOT_EXECUTABLE', dt("The drush command '!args' could not be executed.", array('!args' => $args)));
      }
      elseif (!empty($args)) {
        drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The drush command '!args' could not be found.  Run `drush cache-clear drush` to clear the commandfile cache if you have installed new extensions.", array('!args' => $args)));
      }
      // Set errors that occurred in the bootstrap phases.
      $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
      foreach ($errors as $code => $message) {
        drush_set_error($code, $message);
      }
    }
    return $return;
  }
}
