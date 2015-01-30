<?php
/**
 * Commands and options for complete unit tests, to avoid relying on the
 * ever-changing core command set.
 */

/**
 * Implementation of hook_drush_command().
 */
function completetestsite_drush_command() {
  $items['aaaaaaaard-zebra'] = array(
    'description' => 'No-op command, used to test various completions for commands that start the same as other commands.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_completetest_noop',
  );
  return $items;
}

