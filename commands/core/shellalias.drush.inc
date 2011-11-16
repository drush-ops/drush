<?php

/**
 * @file
 *   Shell alias commands. @see example.drushrc.php for details.
 */

function shellalias_drush_help($section) {
  switch ($section) {
    case 'drush:shell-alias':
      return dt('Print a shell alias record.');
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function shellalias_shell_alias_complete() {
  if ($all = drush_get_context('shell-aliases', array())) {
    return array('values' => array_keys($all));
  }
}

function shellalias_drush_command() {
  $items = array();

  $items['shell-alias'] = array(
    'description' => 'Print all known shell alias records.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'arguments' => array(
      'alias' => 'Shell alias to print',
    ),
    'aliases' => array('sha'),
    'examples' => array(
      'drush shell-alias' => 'List all alias records known to drush.',
      'drush shell-alias pull' => 'Print the value of the shell alias \'pull\'.',
    ),
  );
  return $items;
}

/**
 * Print out the specified shell aliases.
 */
function drush_core_shell_alias($alias = FALSE) {
  $shell_aliases = drush_get_context('shell-aliases', array());
  if (!$alias) {
    drush_print_table(drush_key_value_to_array_table($shell_aliases));
  }
  elseif (isset($shell_aliases[$alias])) {
    drush_print_table(drush_key_value_to_array_table(array($alias => $shell_aliases[$alias])));
  }
}
