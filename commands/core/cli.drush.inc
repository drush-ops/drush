<?php

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
    $shell->setScopeVariables(['container' => \Drupal::getContainer()]);
  }

  // Psysh will never return control to us, but our shutdown handler will still
  // run after the user presses ^D.  Mark this command as completed to avoid a
  // spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
  $shell->run();
}
