<?php

namespace Drush\Boot;

use Drush\Log\LogLevel;

abstract class BaseBoot implements Boot {

  function __construct() {
  }

  function valid_root($path) {
  }

  function get_version($root) {
  }

  function command_defaults() {
  }

  function enforce_requirement(&$command) {
    drush_enforce_requirement_bootstrap_phase($command);
    drush_enforce_requirement_core($command);
    drush_enforce_requirement_drush_dependencies($command);
  }

  function report_command_error($command) {
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

  function bootstrap_and_dispatch() {
    $phases = $this->bootstrap_init_phases();

    $return = '';
    $command_found = FALSE;
    _drush_bootstrap_output_prepare();
    foreach ($phases as $phase) {
      if (drush_bootstrap_to_phase($phase)) {
        $command = drush_parse_command();
        if (is_array($command)) {
          $command += $this->command_defaults();
          // Insure that we have bootstrapped to a high enough
          // phase for the command prior to enforcing requirements.
          $bootstrap_result = drush_bootstrap_to_phase($command['bootstrap']);
          $this->enforce_requirement($command);

          if ($bootstrap_result && empty($command['bootstrap_errors'])) {
            drush_log(dt("Found command: !command (commandfile=!commandfile)", array('!command' => $command['command'], '!commandfile' => $command['commandfile'])), LogLevel::BOOTSTRAP);

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
      $this->report_command_error($command);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function terminate() {
  }
}
