<?php

namespace Drush\Boot;

class DrupalBoot implements Boot {

  function __construct() {
    // @todo Find current version of Drupal and return more specific subclass. For now, there is only 1.
  }

  function preflight() {
    // We need our constants before commandfile searching like DRUSH_BOOTSTRAP_DRUPAL_LOGIN.
    require_once __DIR__ . '/bootstrap.inc';
    require_once __DIR__ . '/command.inc';
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

/**
 * Validate whether a bootstrap phase can be reached.
 *
 * This function will validate the settings that will be used
 * during the actual bootstrap process, and allow commands to
 * progressively bootstrap to the highest level that can be reached.
 *
 * This function will only run the validation function once, and
 * store the result from that execution in a local static. This avoids
 * validating phases multiple times.
 *
 * @param phase
 *   The bootstrap phase to validate to.
 *   Any of the following constants :
 *     DRUSH_BOOTSTRAP_DRUSH                = Only Drush.
 *     DRUSH_BOOTSTRAP_DRUPAL_ROOT          = Find a valid Drupal root.
 *     DRUSH_BOOTSTRAP_DRUPAL_SITE          = Find a valid Drupal site.
 *     DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION = Load the site's settings.
 *     DRUSH_BOOTSTRAP_DRUPAL_DATABASE      = Initialize the database.
 *     DRUSH_BOOTSTRAP_DRUPAL_FULL          = Initialize Drupal fully.
 *     DRUSH_BOOTSTRAP_DRUPAL_LOGIN         = Log into Drupal with a valid user.
 *
 * @return
 *   True if bootstrap is possible, False if the validation failed.
 *
 */
function drush_bootstrap_validate($phase) {
  static $phases;
  static $result_cache = array();
  if (!$phases) {
    $phases = _drush_bootstrap_phases(TRUE);
  }
  static $phase_index = 0;
  // Check to see if anyone has changed --root or --uri
  _drush_bootstrap_select_drupal_site();
  if (!array_key_exists($phase, $result_cache)) {
    drush_set_context('DRUSH_BOOTSTRAP_ERRORS', array());
    drush_set_context('DRUSH_BOOTSTRAP_VALUES', array());

    while ($phase >= $phase_index && isset($phases[$phase_index])) {
      $current_phase = $phases[$phase_index] . '_validate';
      if (function_exists($current_phase)) {
        $result_cache[$phase_index] = $current_phase();
      }
      else {
        $result_cache[$phase_index] = TRUE;
      }
      drush_set_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', $phase_index);
      unset($phases[$phase_index++]);
    }
  }
  return $result_cache[$phase];
}
