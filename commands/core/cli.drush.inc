<?php

/**
 * Implements hook_drush_command().
 */
function cli_drush_command() {
  $items['docs-repl'] = array(
    'description' => 'repl.md',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array(drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH) . '/docs/repl.md'),
  );
  return $items;
}
