<?php

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
  // Updates only run reliably if user ID #1 is logged in. For example, node_delete() requires elevated perms in D5/6.
  if (!drush_get_context('DRUSH_USER')) {
    drush_set_option('user', 1);
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);
  }

  include_once './includes/install.inc';
  drupal_load_updates();

  update_fix_schema_version();
  update_fix_watchdog_115();
  update_fix_watchdog();
  update_fix_sessions();
  $has_updates = FALSE;

  $start = array();
  foreach (module_list() as $module) {

    $updates = drupal_get_schema_versions($module);
    if ($updates !== FALSE) {

      $pending[$module] = array();
      $updates = drupal_map_assoc($updates);

      $schema_version = drupal_get_installed_schema_version($module);
      $default = $schema_version;

      foreach (array_keys($updates) as $update) {
        if ($update > $default) {
          $start[$module] = $update;
          break;
        }
      }

      // Record any pending updates. Used for confirmation prompt.
      foreach (array_keys($updates) as $update) {
        if ($update > $schema_version) {
          if (class_exists('ReflectionFunction')) {
            // The description for an update comes from its Doxygen.
            $func = new ReflectionFunction($module. '_update_'. $update);
            $description = str_replace(array("\n", '*', '/'), '', $func->getDocComment());
          }
          if (empty($description)) {
            $description = dt('description not available');
          }

          $pending[$module][] = array("$update - ". trim($description));
          $has_updates = TRUE;
        }
      }
    }
  }

  // Print a list of pending updates for this module and get confirmation.
  if ($has_updates) {
    drush_print(dt('The following updates are pending:'));
    drush_print();
    foreach ($pending as $module => $updates) {
      if (sizeof($updates)) {
        array_unshift($updates, array($module . ' module'));
        drush_print_table($updates, TRUE);
        drush_print();
      }
    }
    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      return drush_user_abort();
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
        drush_log(dt('No database updates for @module module', array('@module' => $module)), 'success');
      }
    }
  }
  else {
    drush_log(dt("No database updates required"), 'success');
  }
}


