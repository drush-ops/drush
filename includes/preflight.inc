<?php

/**
 * @file
 * Preflight, postflight and shutdown code.
 */

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Cache\CommandCache;
use Drush\Drush;
use Drush\Log\LogLevel;
use League\Container\Container;
use Robo\Contract\BuilderAwareInterface;
use Robo\Robo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * The main Drush function.
 *
 * This function is still called by drush-launcher. It is no longer used by
 * Drush itself.
 */
function drush_main() {
  require dirname(__DIR__) . '/drush.php';
}

function drush_init_register_command_files($container, $commandFiles) {
  foreach ($commandFiles as $sourcePath => $className) {
    if (!class_exists($className)) {
      include $sourcePath;
    }
    $classAlias = str_replace('\\', '', $className);
    // Add and fetch our class from the container to apply the inductors
    $container->share($classAlias, $className);
    $commandFileInstance = $container->get($classAlias);
    if ($commandFileInstance instanceof BuilderAwareInterface) {
        $builder = $container->get('collectionBuilder', [$commandFileInstance]);
        $commandFileInstance->setBuilder($builder);
    }
    drush_add_command_instance($container, $commandFileInstance);
  }
}

function drush_add_command_instance($container, $commandInstance, $includeAllPublicMethods = true) {
  if ($commandInstance instanceof Symfony\Component\Console\Command\Command) {
    drush_add_command_to_application($container, $commandInstance);
    return;
  }
  drush_create_commands_from_command_instance($container, $commandInstance);
}

function drush_create_commands_from_command_instance($container, $commandInstance, $includeAllPublicMethods = true) {
  $application = $container->get('application');
  $commandFactory = $container->get('commandFactory');
  $commandList = $commandFactory->createCommandsFromClass($commandInstance, $includeAllPublicMethods);
  foreach ($commandList as $command) {
    drush_add_command_to_application($container, $command);
  }
}

function drush_add_command_to_application($container, $command) {
  $application = $container->get('application');
  $commandName = $command->getName();
  $drushAlias = strtr($commandName, ':', '-');
  if ($commandName != $drushAlias) {
    $aliases = $command->getAliases();
    $command->setAliases(array_unique(array_merge($aliases, [$drushAlias])));
  }
  $application->add($command);
}

/**
 * Look for instances of arguments or parameters that
 * start with "~/".  Replace these with "$HOME/".
 *
 * Note that this function is called _after_ Drush does
 * its redispatch checks; tildes are passed through
 * unmodified on a redispatch, and are only expanded when
 * a command is handled locally.
 */
function drush_preflight_tilde_expansion(&$command) {
  // Skip tilde expansion for commands that explicitly
  // turn it off via $command['tilde-expansion'] = FALSE.
  if ($command['tilde-expansion']) {
    $cli =& drush_get_context('cli');
    $match = '#^~/#';
    $replacement = drush_server_home() . '/';
    foreach ($cli as $key => $value) {
      if (is_string($value) && preg_match($match, $value)) {
        $cli[$key] = preg_replace($match, $replacement, $value);
      }
    }
    $command['arguments'] = array_map(function($value) use($match, $replacement) { return is_string($value) ? preg_replace($match, $replacement, $value) : $value; } , $command['arguments']);
  }
}

/**
 * We set this context to let the shutdown function know we reached the end of drush_main().
 *
 * @see drush_main()
 */
function drush_postflight() {
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
}

/**
 * Shutdown function for use while Drush and Drupal are bootstrapping and to return any
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
  // Avoid doing anything if our container has not been initialized yet.
  if (!\Drush\Drush::hasContainer()) {
    return;
  }

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

  // @todo Allow the bootstrap objects to register shutdown handlers on
  // the bootstrap manager, and remove them when the bootstrap phase succeeds.
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if (drush_get_context('DRUSH_BOOTSTRAPPING')) {
    switch ($phase) {
      case DRUSH_BOOTSTRAP_DRUPAL_FULL :
        ob_end_clean();
        _drush_log_drupal_messages();
        $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
        $message .= dt("Hint: This error can only occur once the database connection has already been successfully initiated, therefore this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
        $message .= dt('More information may be available by running `drush status`');
        drush_set_error($message);
        break;
    }
  }

  if (\Drush\Drush::backend()) {
    drush_backend_output();
  }

  // This way drush_return_status() will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
  // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
  register_shutdown_function('drush_return_status');
}

/**
 * Shutdown function to save code coverage data.
 */
function drush_coverage_shutdown() {
  if ($file_name = drush_get_context('DRUSH_CODE_COVERAGE', FALSE)) {
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();

    // If coverage dump file contains anything, merge in the old data before
    // saving. This happens if the current drush command invoked another drush
    // command.
    if (file_exists($file_name) && $content = file_get_contents($file_name)) {
      $merge_data = unserialize($content);
      if (is_array($merge_data)) {
        foreach ($merge_data as $file => $lines) {
          if (!isset($data[$file])) {
            $data[$file] = $lines;
          }
          else {
            foreach ($lines as $num => $executed) {
              if (!isset($data[$file][$num])) {
                $data[$file][$num] = $executed;
              }
              else {
                $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
              }
            }
          }
        }
      }
    }

    file_put_contents($file_name, serialize($data));
  }
}

function drush_return_status() {
  // If a specific exit code was set, then use it.
  $exit_code = drush_get_context('DRUSH_EXIT_CODE');
  if (empty($exit_code)) {
    $exit_code = (drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS;
  }

  exit($exit_code);
}
