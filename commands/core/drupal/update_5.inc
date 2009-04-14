<?php
// $Id$

/**
 * @file
 *   Update.php for provisioned sites.
 *   This file is a derivative of the standard drupal update.php,
 *   which has been modified to allow being run from the command
 *   line.
 */


ob_start();
include_once("update.php");
ob_end_clean();

function update_main() {
  include_once './includes/install.inc';
  drupal_load_updates();

  update_fix_schema_version();
  update_fix_watchdog_115();
  update_fix_watchdog();
  update_fix_sessions();

  $start = array();
  foreach (module_list() as $module) {
    $updates = drupal_get_schema_versions($module);
    if ($updates !== FALSE) {
      $updates = drupal_map_assoc($updates);
      $updates[] = 'No updates available';
      $default = drupal_get_installed_schema_version($module);
      foreach (array_keys($updates) as $update) {
        if ($update > $default) {
          $default = $update;
          break;
        }
      }
      $start[$module] = $default;
    }
  }

  $update_results = array();
  foreach ($start as $module => $version) {
    drupal_set_installed_schema_version($module, $version - 1);
    $updates = drupal_get_schema_versions($module);
    $max_version = max($updates);
    if ($version <= $max_version) {
      drush_log(dt('Updating module @module from schema version @start to schema version @max', array('@module' => $module, '@start' => $version - 1, '@max' => $max_version)));
      foreach ($updates as $update) {
        $finished = FALSE;
        if ($update >= $version) {
          while (!$finished) {
            // do update
            $ret = module_invoke($module, 'update_' . $update);
            _drush_log_update_sql($ret);
            $finished = 1;
            if (isset($ret['#finished'])) {
              $finished = $ret['#finished'];
              unset($ret['#finished']);
            }
          }
          drupal_set_installed_schema_version($module, $update);
        }
      }
    }
    else {
      drush_log(dt('No updates for @module module', array('@module' => $module)), 'success');
    }
  }
}


