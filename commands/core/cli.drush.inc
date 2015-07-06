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
  $boris = new \Boris\Boris(drush_sitealias_bootstrapped_site_name() . '> ');
  // Use the Symfony var-dumper to inspect variables.
  $boris->setInspector(new \Drush\Boris\VarDumperInspector());

  if (drush_drupal_major_version() >= 8) {
    // Set a local $container variable for Drupal 8.
    $boris->setLocal(['container' => \Drupal::getContainer()]);
  }

  // Boris will never return control to us, but our shutdown
  // handler will still run after the user presses ^D.  Mark
  // this command as completed to avoid a spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
  $boris->start();
}
