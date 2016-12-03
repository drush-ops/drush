<?php

namespace Drush\Boot;

use Drush\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use Symfony\Component\Console\Input\ArgvInput;

abstract class BaseBoot implements Boot, LoggerAwareInterface {
  use LoggerAwareTrait;

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
            $this->logger->log(LogLevel::BOOTSTRAP, dt("Found command: !command (commandfile=!commandfile)", array('!command' => $command['command'], '!commandfile' => $command['commandfile'])));

            $command_found = TRUE;
            // Dispatch the command(s).
            $return = drush_dispatch($command);

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

    // TODO: If we could not find a legacy Drush command, try running a
    // command via the Symfony application. See also drush_main() in preflight.inc;
    // ultimately, the Symfony application should be called from there.
    if (!$command_found && isset($command) && empty($command['bootstrap_errors'])) {
      $container = \Drush::getContainer();
      $application = $container->get('application');
      $args = drush_get_arguments();
      if (count($args)) {
        $name = $args[0];
        if ($this->hasRegisteredSymfonyCommand($application, $name)) {
          $command_found = true;
          $input = drush_symfony_input();
          $this->logger->log(LogLevel::BOOTSTRAP, dt("Dispatching with Symfony application as a fallback, since no native Drush command was found. (Set DRUSH_SYMFONY environment variable to skip Drush dispatch.)"));
          $application->run($input);
        }
      }
    }

    if (!$command_found) {
      // If we reach this point, command doesn't fit requirements or we have not
      // found either a valid or matching command.
      $this->report_command_error($command);
    }

    // Prevent a '1' at the end of the output.
    if ($return === TRUE) {
      $return = '';
    }

    return $return;
  }

  protected function hasRegisteredSymfonyCommand($application, $name) {
    try {
      $application->find($name);
      return true;
    }
    catch (\InvalidArgumentException $e) {
      return false;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function terminate() {
  }
}
