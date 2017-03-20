<?php

/**
 * Adjust the contents of a site alias.
 */
function hook_drush_sitealias_alter(&$alias_record) {
  // If the alias is "remote", but the remote site is
  // the system this command is running on, convert the
  // alias record to a local alias.
  if (isset($alias_record['remote-host'])) {
    $uname = php_uname('n');
    if ($alias_record['remote-host'] == $uname) {
      unset($alias_record['remote-host']);
      unset($alias_record['remote-user']);
    }
  }
}

/**
 * Sql-sanitize example.
 *
 * These plugins sanitize the DB, usually removing personal information.
 *
 * @see \Drush\Commands\sql\SqlSanitizePluginInterface
 */
function sanitize() {}
function messages() {}

/**
 * Add help components to a command.
 */
function hook_drush_help_alter(&$command) {
  if ($command['command'] == 'sql-sync') {
    $command['options']['myoption'] = "Description of modification of sql-sync done by hook";
    $command['sub-options']['sanitize']['my-sanitize-option'] = "Description of sanitization option added by hook (grouped with --sanitize option)";
  }
  if ($command['command'] == 'global-options') {
    // Recommended: don't show global hook options in brief global options help.
    if ($command['#brief'] === FALSE) {
      $command['options']['myglobaloption'] = 'Description of option used globally in all commands (e.g. in a commandfile init hook)';
    }
  }
}

/**
 * @} End of "addtogroup hooks".
 */
