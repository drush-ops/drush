<?php
/**
 * @file
 *   Update.php for provisioned sites.
 *   This file is a derivative of the standard drupal update.php,
 *   which has been modified to allow being run from the command
 *   line.
 */

use Drush\Log\LogLevel;

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
    if (!isset($attributes['default'])) {
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

  $ret[] = update_sql("ALTER TABLE {". $table ."} ADD $column $type");
  if (!empty($default)) {
    $ret[] = update_sql("ALTER TABLE {". $table ."} ALTER $column SET $default");
  }
  if (!empty($not_null)) {
    if (!empty($default)) {
      $ret[] = update_sql("UPDATE {". $table ."} SET $column = $default_val");
    }
    $ret[] = update_sql("ALTER TABLE {". $table ."} ALTER $column SET NOT NULL");
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
    if (!isset($attributes['default'])) {
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

  $ret[] = update_sql("ALTER TABLE {". $table ."} RENAME $column TO ". $column ."_old");
  $ret[] = update_sql("ALTER TABLE {". $table ."} ADD $column_new $type");
  $ret[] = update_sql("UPDATE {". $table ."} SET $column_new = ". $column ."_old");
  if ($default) { $ret[] = update_sql("ALTER TABLE {". $table ."} ALTER $column_new SET $default"); }
  if ($not_null) { $ret[] = update_sql("ALTER TABLE {". $table ."} ALTER $column_new SET NOT NULL"); }
  $ret[] = update_sql("ALTER TABLE {". $table ."} DROP ". $column ."_old");
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
      drush_log(dt("!type !name is incompatible with this release of Drupal, and will be disabled.",
        array("!type" => $result->type, '!name' => $result->name)), LogLevel::WARNING);
    }
  }
  if (!empty($incompatible)) {

    $ret[] = update_sql("UPDATE {system} SET status = 0 WHERE name IN ('". implode("','", $incompatible) ."')");
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
    drush_include_engine('drupal', 'environment');
    $themes = _system_theme_data();
    $modules = module_rebuild_cache();
  }

  if ($type == 'module' && isset($modules[$name])) {
    $file = $modules[$name];
  }
  else if ($type == 'theme' && isset($themes[$name])) {
    $file = $themes[$name];
  }
  if (!isset($file)
      || !isset($file->info['core'])
      || $file->info['core'] != drush_get_drupal_core_compatibility()
      || version_compare(phpversion(), $file->info['php']) < 0) {
    return TRUE;
  }
  return FALSE;
}

/**
 * Perform Drupal 5.x to 6.x updates that are required for update.php
 * to function properly.
 *
 * This function runs when update.php is run the first time for 6.x,
 * even before updates are selected or performed.  It is important
 * that if updates are not ultimately performed that no changes are
 * made which make it impossible to continue using the prior version.
 * Just adding columns is safe.  However, renaming the
 * system.description column to owner is not.  Therefore, we add the
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

    // Create the semaphore table now -- the menu system after 6.15 depends on
    // this table, and menu code runs in updates prior to the table being
    // created in its original update function, system_update_6054().
    $schema['semaphore'] = array(
      'fields' => array(
        'name' => array(
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => ''),
      'value' => array(
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => ''),
      'expire' => array(
        'type' => 'float',
        'size' => 'big',
        'not null' => TRUE),
      ),
     'indexes' => array('expire' => array('expire')),
     'primary key' => array('name'),
    );
    db_create_table($ret, 'semaphore', $schema['semaphore']);
  }

  return $ret;
}

/**
 * Check update requirements and report any errors.
 */
function update_check_requirements() {
  // Check the system module requirements only.
  $requirements = module_invoke('system', 'requirements', 'update');
  $severity = drupal_requirements_severity($requirements);

  // If there are issues, report them.
  if ($severity != REQUIREMENT_OK) {
    foreach ($requirements as $requirement) {
      if (isset($requirement['severity']) && $requirement['severity'] != REQUIREMENT_OK) {
        $message = isset($requirement['description']) ? $requirement['description'] : '';
        if (isset($requirement['value']) && $requirement['value']) {
          $message .= ' (Currently using '. $requirement['title'] .' '. $requirement['value'] .')';
        }
        drush_log($message, LogLevel::WARNING);
      }
    }
  }
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

function update_main_prepare() {
  global $profile;
  // Some unavoidable errors happen because the database is not yet up-to-date.
  // Our custom error handler is not yet installed, so we just suppress them.
  drush_errors_off();

  require_once './includes/bootstrap.inc';
  // Minimum load of components.
  // This differs from the Drupal 6 update.php workflow for compatbility with
  // the Drupal 6 backport of module_implements() caching.
  // @see http://drupal.org/node/557542
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
  require_once './includes/install.inc';
  require_once './includes/file.inc';
  require_once './modules/system/system.install';

  // Load module basics.
  include_once './includes/module.inc';
  $module_list['system']['filename'] = 'modules/system/system.module';
  $module_list['filter']['filename'] = 'modules/filter/filter.module';
  module_list(TRUE, FALSE, FALSE, $module_list);
  module_implements('', FALSE, TRUE);

  drupal_load('module', 'system');
  drupal_load('module', 'filter');

  // Set up $language, since the installer components require it.
  drupal_init_language();

  // Set up theme system for the maintenance page.
  drupal_maintenance_theme();

  // Check the update requirements for Drupal.
  update_check_requirements();

  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  $profile = variable_get('install_profile', 'default');
  // Updates only run reliably if user ID #1 is logged in. For example, node_delete() requires elevated perms in D5/6.
  if (!drush_get_context('DRUSH_USER')) {
    drush_set_option('user', 1);
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_LOGIN);
  }

  // This must happen *after* drupal_bootstrap(), since it calls
  // variable_(get|set), which only works after a full bootstrap.
  _drush_log_update_sql(update_create_batch_table());

  // Turn error reporting back on. From now on, only fatal errors (which are
  // not passed through the error handler) will cause a message to be printed.
  drush_errors_on();

  // Perform Drupal 5.x to 6.x updates that are required for update.php to function properly.
  _drush_log_update_sql(update_fix_d6_requirements());

  // Must unset $theme->status in order to safely rescan and repopulate
  // the system table to ensure we have a full picture of the platform.
  // This is needed because $theme->status is set to 0 in a call to
  // list_themes() done by drupal_maintenance_theme().
  // It is a issue with _system_theme_data() that returns its own cache
  // variable and can be modififed by others. When this is fixed in
  // drupal core we can remove this unset.
  // For reference see: http://drupal.org/node/762754
  $themes = _system_theme_data();
  foreach ($themes as $theme) {
    unset($theme->status);
  }
  drush_get_extensions();

  include_once './includes/batch.inc';
  drupal_load_updates();

  // Disable anything in the {system} table that is not compatible with the current version of Drupal core.
  _drush_log_update_sql(update_fix_compatibility());
}

function update_main() {
  update_main_prepare();

  list($pending, $start) = updatedb_status();

  // Print a list of pending updates for this module and get confirmation.
  if ($pending) {
    // @todo get table header working
    // array_unshift($pending, array(dt('Module'), dt('ID'), dt('Description')));
    drush_print_table($pending, FALSE);
    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      return drush_user_abort();
    }
    // Proceed with running all pending updates.
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
        drush_log(dt('No database updates for module @module', array('@module' => $module)), LogLevel::SUCCESS);
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
    drush_backend_batch_process('updatedb-batch-process');
  }
  else {
    drush_log(dt("No database updates required"), LogLevel::SUCCESS);
  }

  return count($pending);
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

  $function = $module .'_update_'. $number;
  drush_log("Executing $function", LogLevel::SUCCESS);

  if (function_exists($function)) {
    $ret = $function($context['sandbox']);
    $context['results'][$module] = $ret;
    _drush_log_update_sql($ret);
  }

  if (isset($ret['#finished'])) {
    $context['finished'] = $ret['#finished'];
    unset($ret['#finished']);
  }

  if ($context['finished'] == 1 && empty($context['results'][$module]['#abort'])) {
    drupal_set_installed_schema_version($module, $number);
  }

}

function _update_batch_command($id) {
  update_main_prepare();
  drush_batch_command($id);
}

/**
 * Return a 2 item array with
 *  - an array where each item is a 3 item associative array describing a pending update.
 *  - an array listing the first update to run, keyed by module.
 */
function updatedb_status() {
  $return = array();

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

      // Record the starting update number for each module.
      foreach (array_keys($updates) as $update) {
        if ($update > $schema_version) {
          $start[$module] = $update;
          break;
        }
      }
      if (isset($start['system'])) {
        // Ensure system module's updates run first.
        $start = array('system' => $start['system']) + $start;
      }

      // Record any pending updates. Used for confirmation prompt.
      foreach (array_keys($updates) as $update) {
        if ($update > $schema_version) {
          if (class_exists('ReflectionFunction')) {
            // The description for an update comes from its Doxygen.
            $func = new ReflectionFunction($module. '_update_'. $update);
            $description = trim(str_replace(array("\n", '*', '/'), '', $func->getDocComment()));
          }
          if (empty($description)) {
            $description = dt('description not available');
          }

          $return[] = array('module' => ucfirst($module), 'update_id' => $update, 'description' => $description);
        }
      }
    }
  }

  return array($return, $start);
}
