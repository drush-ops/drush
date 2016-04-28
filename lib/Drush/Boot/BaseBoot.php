<?php

namespace Drush\Boot;

use Drush\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;


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
    $this->drush_enforce_requirement_composer_autoloader($command);
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

            // Load the autoload files for any commandfiles that need them.
            // This is only for non-composer-managed sites.  In composer-managed
            // sites, we expect that all extensions are required from the
            // site's composer.json file, in which case their autoloaders will
            // already have been included safely.
            commandfiles_cache()->load_autoload_files();

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
   * Check to see if this Drupal site is using Composer, and
   * if it is, we will also check to see if we loaded any foreign
   * autoload files.  If we do, then fail fast with an error,
   * and give the use some advice about how to repair their configuration.
   *
   * We run this test regardless of bootstrap level, because Drush
   * will load Drupal's autoload file early, if it exists.
   */
  function drush_enforce_requirement_composer_autoloader(&$command) {
    // n.b. DRUSH_SELECTED_DRUPAL_ROOT should be DRUSH_SELECTED_ROOT or something
    $root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
    if (!empty($root)) {
      // Check to see if we have a composer.json file.  We will only look in
      // two locations:  1) the root, and 2) the parent directory above the root.
      foreach (array('', '/..') as $search_dir) {
        $dir = realpath($root . $search_dir);
        if (file_exists($dir . "/composer.json")) {
          // We are running in the context of a site that has a
          // composer.json file.  If the current command has a
          // foreign autoloader, then we will fail right here.
          // It does not work to load multiple autoloaders that were
          // independently evaluated; if two components have
          // the same dependency, but each selected a different version,
          // then highly unpredictable things can happen.
          $foreign_autoload_file = commandfiles_cache()->find_autoload_file_for_extension($command['commandfile']);
          if (!empty($foreign_autoload_file)) {
            $foreign_autoload_files = commandfiles_cache()->find_autoload_files();
            $project_names = array_keys($foreign_autoload_files);
            array_unshift($project_names, "drush/drush");
            $composer_dir = $root;
            if (file_exists($root . "/../composer.json")) {
              $composer_dir = dirname($composer_dir);
            }
            $hints = array("    cd $composer_dir");
            foreach ($project_names as $project_name) {
              $hints[] = "    composer global require $project_name";
            }
            $command['bootstrap_errors']['COMPOSER_AUTOLOADER_ERROR'] = dt("The command !command cannot be used with the site at !root, because it uses a separate autoload file. To use this command, first run:\n!hints", array('!command' => $command['command'], '!root' => $root, '!hints' => implode("\n", $hints)));
            return FALSE;
          }
          else {
            // If the command that was selected does not need an autoload
            // file, but there are other commandfiles that do, then we will
            // erase those other commandfiles from our cached list, so that
            // none of their hooks run.
            commandfiles_cache()->prevent_dependency_hell();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function terminate() {
  }
}
