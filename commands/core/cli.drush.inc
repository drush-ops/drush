<?php

use Drupal\Component\Assertion\Handle;
use Drush\Psysh\DrushHelpCommand;
use Drush\Psysh\DrushCommand;

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
  $shell = new Psy\Shell();

  if (drush_drupal_major_version() >= 8) {
    // Register the assertion handler so exceptions are thrown instead of errors
    // being triggered. This plays nicer with PsySH.
    Handle::register();
    $shell->setScopeVariables(['container' => \Drupal::getContainer()]);
  }

  // Add drush commands in the shell.
  $commands = [new DrushHelpCommand()];

  foreach (drush_get_commands() as $name => $config) {
    // Ignore some commands that don't make sense inside PsySH.
    if (in_array($name, ['help', 'drush-psysh', 'php-eval', 'core-cli', 'php'])) {
      continue;
    }
    // Don't add hidden commands or aliases.
    if ($config['hidden'] || $name !== $config['command']) {
      continue;
    }

    $commands[] = new DrushCommand($config);
  }

  $shell->addCommands($commands);

  // PsySH will never return control to us, but our shutdown handler will still
  // run after the user presses ^D.  Mark this command as completed to avoid a
  // spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
  $shell->run();
}
