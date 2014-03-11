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
  $boris->setInspector(new \Boris\ColoredInspector());

  // Boris will never return control to us, but our shutdown
  // handler will still run after the user presses ^D.  Mark
  // this command as completed to avoid a spurious error message.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
  $boris->start();
}
