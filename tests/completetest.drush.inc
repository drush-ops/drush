<?php
/**
 * @file
 *   Commands and options for complete unit tests, to avoid relying on the
 *   ever-changing core command set.
 */

/**
 * Implementation of hook_drush_command().
 */
function completetest_drush_command() {
  $commands = array(
    'aaaaaaaard',
    'aaaaaaaard-ant',
    'aaaaaaaard-vark',
    'aaaaaaaard-wolf',
    'zzzzzzzzebra',
  );
  foreach ($commands as $command) {
    $items[$command] = array(
      'description' => 'No-op command, used to test various completions for commands that start the same as other commands.',
      'bootstrap' => DRUSH_BOOTSTRAP_NONE,
      'callback' => 'drush_completetest_noop',
    );
  }
  $items['aaaaaaaard']['arguments'] = array('name' => 'Name');
  $items['aaaaaaaard']['options'] = array(
    'ears' => 'Ears',
    'eyes' => 'Eyes',
    'nose' => 'Nose',
    'legs' => 'Legs',
  );
  return $items;
}

function drush_completetest_noop() {
  // No-op.
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of completions.
 */
function completetest_completetest_noop_complete() {
  return array(
    'values' => array(
      'aardvark',
      'aardwolf',
      'zebra',
    ),
  );
}
