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

/*
 * Storage filters alter the .yml files on disk after a config-export or before
 * a config-import. See `drush topic docs-config-filter` and config_drush_storage_filters().
 */
function hook_drush_storage_filters() {
  $result = array();
  $module_adjustments = drush_get_option('skip-modules');
  if (!empty($module_adjustments)) {
    if (is_string($module_adjustments)) {
      $module_adjustments = explode(',', $module_adjustments);
    }
    $result[] = new CoreExtensionFilter($module_adjustments);
  }
  return $result;
}

/**
 * @} End of "addtogroup hooks".
 */
