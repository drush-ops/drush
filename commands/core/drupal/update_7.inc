<?php
// $Id$
/**
 * @file
 *   Update.php for provisioned sites.
 *   This file is a derivative of the standard drupal update.php,
 *   which has been modified to allow being run from the command
 *   line.
 */

/**
 * Global flag to identify update.php run, and so avoid various unwanted
 * operations, such as hook_init() and hook_exit() invokes, css/js preprocessing
 * and translation, and solve some theming issues. This flag is checked on several
 * places in Drupal code (not just update.php).
 */
define('MAINTENANCE_MODE', 'update');

/**
 * Add a column to a database using syntax appropriate for PostgreSQL.
 * Save result of SQL commands in $ret array.
 *
 * Note: when you add a column with NOT NULL and you are not sure if there are
 * already rows in the table, you MUST also add DEFAULT. Otherwise PostgreSQL
 * won't work when the table is not empty, and db_add_column() will fail.
 * To have an empty string as the default, you must use: 'default' => "''"
 * in the $attributes array. If NOT NULL and DEFAULT are set the PostgreSQL
 * version will set values of the added column in old rows to the
 * DEFAULT value.
 *
 * @param $ret
 *   Array to which results will be added.
 * @param $table
 *   Name of the table, without {}
 * @param $column
 *   Name of the column
 * @param $type
 *   Type of column
 * @param $attributes
 *   Additional optional attributes. Recognized attributes:
 *     not null => TRUE|FALSE
 *     default  => NULL|FALSE|value (the value must be enclosed in '' marks)
 * @return
 *   nothing, but modifies $ret parameter.
 */
function db_add_column(&$ret, $table, $column, $type, $attributes = array()) {
  if (array_key_exists('not null', $attributes) and $attributes['not null']) {
    $not_null = 'NOT NULL';
  }
  if (array_key_exists('default', $attributes)) {
    if (is_null($attributes['default'])) {
      $default_val = 'NULL';
      $default = 'default NULL';
    }
    elseif ($attributes['default'] === FALSE) {
      $default = '';
    }
    else {
      $default_val = "$attributes[default]";
      $default = "default $attributes[default]";
    }
  }

  $ret[] = update_sql("ALTER TABLE {" . $table . "} ADD $column $type");
  if (!empty($default)) {
    $ret[] = update_sql("ALTER TABLE {" . $table . "} ALTER $column SET $default");
  }
  if (!empty($not_null)) {
    if (!empty($default)) {
      $ret[] = update_sql("UPDATE {" . $table . "} SET $column = $default_val");
    }
    $ret[] = update_sql("ALTER TABLE {" . $table . "} ALTER $column SET NOT NULL");
  }
}

/**
 * Change a column definition using syntax appropriate for PostgreSQL.
 * Save result of SQL commands in $ret array.
 *
 * Remember that changing a column definition involves adding a new column
 * and dropping an old one. This means that any indices, primary keys and
 * sequences from serial-type columns are dropped and might need to be
 * recreated.
 *
 * @param $ret
 *   Array to which results will be added.
 * @param $table
 *   Name of the table, without {}
 * @param $column
 *   Name of the column to change
 * @param $column_new
 *   New name for the column (set to the same as $column if you don't want to change the name)
 * @param $type
 *   Type of column
 * @param $attributes
 *   Additional optional attributes. Recognized attributes:
 *     not null => TRUE|FALSE
 *     default  => NULL|FALSE|value (with or without '', it won't be added)
 * @return
 *   nothing, but modifies $ret parameter.
 */
function db_change_column(&$ret, $table, $column, $column_new, $type, $attributes = array()) {
  if (array_key_exists('not null', $attributes) and $attributes['not null']) {
    $not_null = 'NOT NULL';
  }
  if (array_key_exists('default', $attributes)) {
    if (is_null($attributes['default'])) {
      $default_val = 'NULL';
      $default = 'default NULL';
    }
    elseif ($attributes['default'] === FALSE) {
      $default = '';
    }
    else {
      $default_val = "$attributes[default]";
      $default = "default $attributes[default]";
    }
  }

  $ret[] = update_sql("ALTER TABLE {" . $table . "} RENAME $column TO " . $column . "_old");
  $ret[] = update_sql("ALTER TABLE {" . $table . "} ADD $column_new $type");
  $ret[] = update_sql("UPDATE {" . $table . "} SET $column_new = " . $column . "_old");
  if ($default) {
    $ret[] = update_sql("ALTER TABLE {" . $table . "} ALTER $column_new SET $default");
  }
  if ($not_null) {
    $ret[] = update_sql("ALTER TABLE {" . $table . "} ALTER $column_new SET NOT NULL");
  }
  $ret[] = update_sql("ALTER TABLE {" . $table . "} DROP " . $column . "_old");
}

/**
 * Perform one update and store the results which will later be displayed on
 * the finished page.
 *
 * An update function can force the current and all later updates for this
 * module to abort by returning a $ret array with an element like:
 * $ret['#abort'] = array('success' => FALSE, 'query' => 'What went wrong');
 * The schema version will not be updated in this case, and all the
 * aborted updates will continue to appear on update.php as updates that
 * have not yet been run.
 *
 * @param $module
 *   The module whose update will be run.
 * @param $number
 *   The update number to run.
 * @param $context
 *   The batch context array
 */
function update_do_one($module, $number, &$context) {
  // If updates for this module have been aborted
  // in a previous step, go no further.
  if (!empty($context['results'][$module]['#abort'])) {
    return;
  }

  try {
    $function = $module . '_update_' . $number;
    if (function_exists($function)) {
      $ret = $function($context['sandbox']);
    }
  }
  catch (Exception $e) {
    drush_set_error('DRUPAL_EXCEPTION', var_export($e, TRUE));
  }

  if (isset($ret['#finished'])) {
    $context['finished'] = $ret['#finished'];
    unset($ret['#finished']);
  }

  if (!isset($context['results'][$module])) {
    $context['results'][$module] = array();
  }
  if (!isset($context['results'][$module][$number])) {
    $context['results'][$module][$number] = array();
  }
  $context['results'][$module][$number] = array_merge($context['results'][$module][$number], $ret);

  if (!empty($ret['#abort'])) {
    $context['results'][$module]['#abort'] = TRUE;
  }
  // Record the schema update if it was completed successfully.
  if ($context['finished'] == 1 && empty($context['results'][$module]['#abort'])) {
    drupal_set_installed_schema_version($module, $number);
  }

  $context['message'] = 'Updating ' . check_plain($module) . ' module';
}

/**
 * Create the batch table.
 *
 * This is part of the Drupal 5.x to 6.x migration.
 */
function update_create_batch_table() {

  // If batch table exists, update is not necessary
  if (db_table_exists('batch')) {
    return;
  }

  $schema['batch'] = array(
    'fields' => array(
      'bid'       => array('type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE),
      'token'     => array('type' => 'varchar', 'length' => 64, 'not null' => TRUE),
      'timestamp' => array('type' => 'int', 'not null' => TRUE),
      'batch'     => array('type' => 'text', 'not null' => FALSE, 'size' => 'big')
    ),
    'primary key' => array('bid'),
    'indexes' => array('token' => array('token')),
  );

  $ret = array();
  db_create_table($ret, 'batch', $schema['batch']);
  return $ret;
}

/**
 * Disable anything in the {system} table that is not compatible with the
 * current version of Drupal core.
 */
function update_fix_compatibility() {
  $ret = array();
  $incompatible = array();
  $query = db_query("SELECT name, type, status FROM {system} WHERE status = 1 AND type IN ('module','theme')");
  while ($result = db_fetch_object($query)) {
    if (update_check_incompatibility($result->name, $result->type)) {
      $incompatible[] = $result->name;
    }
  }
  if (!empty($incompatible)) {
    $ret[] = update_sql("UPDATE {system} SET status = 0 WHERE name IN ('" . implode("','", $incompatible) . "')");
  }
  return $ret;
}

/**
 * Helper function to test compatibility of a module or theme.
 */
function update_check_incompatibility($name, $type = 'module') {
  static $themes, $modules;

  // Store values of expensive functions for future use.
  if (empty($themes) || empty($modules)) {
    $themes = _system_theme_data();
    $modules = module_rebuild_cache();
  }

  if ($type == 'module' && isset($modules[$name])) {
    $file = $modules[$name];
  }
  elseif ($type == 'theme' && isset($themes[$name])) {
    $file = $themes[$name];
  }
  if (!isset($file)
      || !isset($file->info['core'])
      || $file->info['core'] != DRUPAL_CORE_COMPATIBILITY
      || version_compare(phpversion(), $file->info['php']) < 0
      || ($type == 'module' && empty($file->info['files']))) {
    return TRUE;
  }
  return FALSE;
}

/**
 * Perform Drupal 5.x to 6.x updates that are required for update.php
 * to function properly.
 *
 * This function runs when update.php is run the first time for 6.x,
 * even before updates are selected or performed. It is important
 * that if updates are not ultimately performed that no changes are
 * made which make it impossible to continue using the prior version.
 * Just adding columns is safe. However, renaming the
 * system.description column to owner is not. Therefore, we add the
 * system.owner column and leave it to system_update_6008() to copy
 * the data from description and remove description. The same for
 * renaming locales_target.locale to locales_target.language, which
 * will be finished by locale_update_6002().
 */
function update_fix_d6_requirements() {
  $ret = array();

  if (drupal_get_installed_schema_version('system') < 6000 && !variable_get('update_d6_requirements', FALSE)) {
    $spec = array('type' => 'int', 'size' => 'small', 'default' => 0, 'not null' => TRUE);
    db_add_field($ret, 'cache', 'serialized', $spec);
    db_add_field($ret, 'cache_filter', 'serialized', $spec);
    db_add_field($ret, 'cache_page', 'serialized', $spec);
    db_add_field($ret, 'cache_menu', 'serialized', $spec);

    db_add_field($ret, 'system', 'info', array('type' => 'text'));
    db_add_field($ret, 'system', 'owner', array('type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''));
    if (db_table_exists('locales_target')) {
      db_add_field($ret, 'locales_target', 'language', array('type' => 'varchar', 'length' => 12, 'not null' => TRUE, 'default' => ''));
    }
    if (db_table_exists('locales_source')) {
      db_add_field($ret, 'locales_source', 'textgroup', array('type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => 'default'));
      db_add_field($ret, 'locales_source', 'version', array('type' => 'varchar', 'length' => 20, 'not null' => TRUE, 'default' => 'none'));
    }
    variable_set('update_d6_requirements', TRUE);

    // Create the cache_block table. See system_update_6027() for more details.
    $schema['cache_block'] = array(
      'fields' => array(
        'cid'        => array('type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''),
        'data'       => array('type' => 'blob', 'not null' => FALSE, 'size' => 'big'),
        'expire'     => array('type' => 'int', 'not null' => TRUE, 'default' => 0),
        'created'    => array('type' => 'int', 'not null' => TRUE, 'default' => 0),
        'headers'    => array('type' => 'text', 'not null' => FALSE),
        'serialized' => array('type' => 'int', 'size' => 'small', 'not null' => TRUE, 'default' => 0)
      ),
      'indexes' => array('expire' => array('expire')),
      'primary key' => array('cid'),
    );
    db_create_table($ret, 'cache_block', $schema['cache_block']);
  }

  return $ret;
}

/**
 * Add the update task list to the current page.
 */
function update_task_list($active = NULL) {
  // Default list of tasks.
  $tasks = array(
    'info' => 'Overview',
    'select' => 'Review updates',
    'run' => 'Run updates',
    'finished' => 'Review log',
  );

  drupal_set_content('left', theme('task_list', $tasks, $active));
}

/**
 * Check update requirements and report any errors.
 */
function update_check_requirements() {
  global $db_url, $databases;
  $requirements = array();

  // If we will rewrite the settings.php then we need to make sure it is
  // writeable.
  if (empty($databases) && !empty($db_url) && is_string($db_url)) {
    $requirements = install_check_requirements('', FALSE);
  }
  $warnings = FALSE;

  // Check the system module requirements only.
  $requirements += module_invoke('system', 'requirements', 'update');
  $severity = drupal_requirements_severity($requirements);

  // If there are issues, report them.
  if ($severity != REQUIREMENT_OK) {
    foreach ($requirements as $requirement) {
      if (isset($requirement['severity']) && $requirement['severity'] != REQUIREMENT_OK) {
        $message = isset($requirement['description']) ? $requirement['description'] : '';
        if (isset($requirement['value']) && $requirement['value']) {
          $message .= ' (Currently using ' . $requirement['title'] . ' ' . $requirement['value'] . ')';
        }
        $warnings = TRUE;
        drupal_set_message($message, 'warning');
      }
    }
  }
  return $warnings;
}

/**
 * Converts Drupal 6 $db_url to Drupal 7 $databases array.
 */
function update_check_d7_settings() {
  global $db_url, $databases;

  if (empty($databases) && !empty($db_url) && is_string($db_url)) {
    $url = parse_url($db_url);
    $driver = substr($db_url, 0, strpos($db_url, '://'));
    if ($driver == 'mysqli') {
      $driver = 'mysql';
    }
    $databases['default']['default']['driver'] = $driver;
    $databases['default']['default']['database'] = substr($url['path'], 1);
    foreach (array('user' => 'username', 'pass' => 'password', 'host' => 'host', 'port' => 'port') as $old_key => $new_key) {
      $databases['default']['default'][$new_key] =  isset($url[$old_key]) ? urldecode($url[$old_key]) : '';
    }
    $conf_path = conf_path();
    file_put_contents($conf_path .'/settings.php', "\n" . '$databases = '. var_export($databases, TRUE) . ';', FILE_APPEND);
  }
}

function update_main() {
  // Some unavoidable errors happen because the database is not yet up-to-date.
  // Our custom error handler is not yet installed, so we just suppress them.
  drush_errors_off();

  require_once DRUPAL_ROOT . '/includes/bootstrap.inc';

  // We only load DRUPAL_BOOTSTRAP_CONFIGURATION for the update requirements
  // check to avoid reaching the PHP memory limit.
  // Minimum load of components.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);

  require_once DRUPAL_ROOT . '/includes/install.inc';
  require_once DRUPAL_ROOT . '/includes/file.inc';
  require_once DRUPAL_ROOT . '/modules/system/system.install';

  // Load module basics.
  include_once DRUPAL_ROOT . '/includes/module.inc';
  $module_list['system']['filename'] = 'modules/system/system.module';
  $module_list['filter']['filename'] = 'modules/filter/filter.module';
  module_list(TRUE, FALSE, $module_list);
  drupal_load('module', 'system');
  drupal_load('module', 'filter');

  // Set up $language, since the installer components require it.
  drupal_init_language();

  // Set up theme system for the maintenance page.
  drupal_maintenance_theme();

  // Check the update requirements for Drupal.
  $warnings = update_check_requirements();

  // Allow the database system to work even though the registry has not
  // been created yet.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
  include_once DRUPAL_ROOT . '/includes/install.inc';
  drupal_install_init_database();
  spl_autoload_unregister('drupal_autoload_class');
  spl_autoload_unregister('drupal_autoload_interface');
  // The new {blocked_ips} table is used in Drupal 7 to store a list of
  // banned IP addresses. If this table doesn't exist then we are still
  // running on a Drupal 6 database, so suppress the unavoidable errors
  // that occur.
  try {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_ACCESS);
  }
  catch (Exception $e) {
    if (db_table_exists('blocked_ips')) {
      throw $e;
    }
  }
  
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  drupal_maintenance_theme();
  drush_errors_on();

  drupal_load_updates();

  _drush_log_update_sql(update_fix_d6_requirements());
  _drush_log_update_sql(update_fix_compatibility());

  $start = array();
  $start['system'] = array();
  $modules = drupal_get_installed_schema_version(NULL, FALSE, TRUE);
  foreach ($modules as $module => $schema_version) {
    $updates = drupal_get_schema_versions($module);
    // Skip incompatible module updates completely, otherwise test schema versions.
    if (!update_check_incompatibility($module) && $updates !== FALSE && $schema_version >= 0) {
      // module_invoke returns NULL for nonexisting hooks, so if no updates
      // are removed, it will == 0.
      $last_removed = module_invoke($module, 'update_last_removed');
      if ($schema_version < $last_removed) {
        drush_set_error('PROVISION_DRUPAL_UPDATE_FAILED', dt( $module .' module can not be updated. Its schema version is '. $schema_version .'. Updates up to and including '. $last_removed .' have been removed in this release. In order to update '. $module .' module, you will first <a href="http://drupal.org/upgrade">need to upgrade</a> to the last version in which these updates were available.'));
        continue;
      }

      $updates = drupal_map_assoc($updates);
      
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

          $pending[] = array("$update - ". trim($description));
        }
      }

      $updates[] = 'No updates available';
      $default = $schema_version;
      foreach (array_keys($updates) as $update) {
        if ($update > $schema_version) {
          $default = $update;
          break;
        }
      }
      $start[$module] = $default;
    }
  }
  
  // Print a list of pending updates for this module and get confirmation.
  if (!empty($pending)) {
    drush_print(dt('The following updates are pending:'));
    drush_print();
    array_unshift($pending, array($module . ' module'));
    drush_print_table($pending, 0, TRUE);
    drush_print();
    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      drush_die('Aborting.');
    }
  }

  // Proceed with running all pending updates.
  if (sizeof($start)) {
    $operations = array();
    foreach ($start as $module => $version) {
      drupal_set_installed_schema_version($module, $version - 1);
      $updates = drupal_get_schema_versions($module);
      $max_version = max($updates);
      if ($version <= $max_version) {
        drush_log(dt('Updating module @module from schema version @start to schema version @max', array('@module' => $module, '@start' => $version - 1, '@max' => $max_version)));
        foreach ($updates as $update) {
          if ($update >= $version) {
            $operations[] = array('_update_do_one', array($module, $update));
          }
        }
      }
      else {
        drush_log(dt('No database updates for module @module', array('@module' => $module)), 'success');
      }
    }
    $batch = array(
      'operations' => $operations,
      'title' => 'Updating',
      'init_message' => 'Starting updates',
      'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
      'finished' => 'update_finished',
    );
    batch_set($batch);
    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    batch_process();
  }
  else {
    drush_log(dt('No outstanding database updates'));
  }
}

/**
 * A simplified version of the batch_do_one function from update.php
 * 
 * This does not mess with sessions and the like, as it will be used
 * from the command line
 */
function _update_do_one($module, $number, &$context) {
  // If updates for this module have been aborted
  // in a previous step, go no further.
  if (!empty($context['results'][$module]['#abort'])) {
    return;
  }

  try {
    $function = $module .'_update_'. $number;
    if (function_exists($function)) {
      drush_log("Running $function");
      $ret = $function($context['sandbox']);
    }
    _drush_log_update_sql($ret);
  }
  catch (Exception $e) {
    drush_log(var_dump($e, TRUE));
  }

  if (isset($ret['#finished'])) {
    $context['finished'] = $ret['#finished'];
    unset($ret['#finished']);
  }

  if ($context['finished'] == 1 && empty($context['results'][$module]['#abort'])) {
    drupal_set_installed_schema_version($module, $number);
  }

}


function update_finished($success, $results, $operations) {
  // clear the caches in case the data has been updated.
  drupal_flush_all_caches();

  drupal_set_session('update_results', $results);
  drupal_set_session('update_success', $success);
  drupal_set_session('updates_remaining', $operations);

  // Now that the update is done, we can disable site maintenance if it was
  // previously turned off.
  if (isset($_SESSION['site_offline']) && $_SESSION['site_offline'] == FALSE) {
    variable_set('site_offline', FALSE);
    unset($_SESSION['site_offline']);
  }
}

