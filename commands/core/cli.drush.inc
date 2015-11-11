<?php

use Drupal\Component\Assertion\Handle;
use Drush\Psysh\DrushHelpCommand;
use Drush\Psysh\DrushCommand;
use Drush\Psysh\Shell;

/**
 * Implements hook_drush_command().
 */
function cli_drush_command() {
  $items['core-cli'] = array(
    'description' => 'Open an interactive shell on a Drupal site.',
    'remote-tty' => TRUE,
    'aliases' => array('php'),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_cli_core_cli() {
  $shell = new Shell();

  if (drush_drupal_major_version() >= 8) {
    // Register the assertion handler so exceptions are thrown instead of errors
    // being triggered. This plays nicer with PsySH.
    Handle::register();
    $shell->setScopeVariables(['container' => \Drupal::getContainer()]);
  }

  // Add drush commands in the shell.
  $commands = [new DrushHelpCommand()];

  foreach (drush_commands_categorize(_drush_core_cli_get_commands()) as $category_data) {
    $category_title = (string) $category_data['title'];
    foreach ($category_data['commands'] as $command_config) {
      $command = new DrushCommand($command_config);
      $command->setCategory($category_title);
      $commands[] = $command;
    }
  }

  $shell->addCommands($commands);

  // PsySH will never return control to us, but our shutdown handler will still
  // run after the user presses ^D.  Mark this command as completed to avoid a
  // spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
  $shell->run();
}

/**
 * Returns a filtered list of Drush commands used for CLI commands.
 *
 * @return array
 */
function _drush_core_cli_get_commands() {
  $commands = drush_get_commands();
  $ignored_commands = ['help', 'drush-psysh', 'php-eval', 'core-cli', 'php'];

  foreach ($commands as $name => $config) {
    // Ignore some commands that don't make sense inside PsySH, are hidden, or
    // are aliases.
    if (in_array($name, $ignored_commands) || !empty($config['hidden']) || ($name !== $config['command'])) {
      unset($commands[$name]);
    }
  }

  return $commands;
}
