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
 * Returns (and optionally stores) extra requirements that only apply during
 * particular parts of the update.php process.
 */
function update_extra_requirements($requirements = NULL) {
  static $extra_requirements = array();
  if (isset($requirements)) {
    $extra_requirements += $requirements;
  }
  return $extra_requirements;
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
function drush_update_do_one($module, $number, &$context) {
  // If updates for this module have been aborted
  // in a previous step, go no further.
  if (!empty($context['results'][$module]['#abort'])) {
    return;
  }

  $context['log'] = FALSE;

  $ret = array();
  $function = $module . '_update_' . $number;
  if (function_exists($function)) {
    try {
      if ($context['log']) {
        Database::startLog($function);
      }

      drush_log("Executing " . $function);
      $ret['results']['query'] = $function($context['sandbox']);
      $ret['results']['success'] = TRUE;

      // @TODO Remove this block after all updates have been converted to
      // return only strings.
      if (is_array($ret['results']['query'])) {
        $ret = $ret['results']['query'];
      }
    }
    // @TODO We may want to do different error handling for different exception
    // types, but for now we'll just print the message.
    catch (Exception $e) {
      $ret['#abort'] = array('success' => FALSE, 'query' => $e->getMessage());
      drush_set_error('DRUPAL_EXCEPTION', $e->getMessage());
    }

    if ($context['log']) {
      $ret['queries'] = Database::getLog($function);
#      _drush_log_update_sql($ret);
    }
  }

  // @TODO Remove this block after all updates have been converted to return
  // only strings.
  if (isset($ret['#finished'])) {
    $context['finished'] = $ret['#finished'];
    unset($ret['#finished']);
  }

  if (isset($context['sandbox']['#finished'])) {
    $context['finished'] = $context['sandbox']['#finished'];
    unset($context['sandbox']['#finished']);
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
 * Check update requirements and report any errors.
 */
function update_check_requirements() {
  $warnings = FALSE;
  
  // Check the system module and update.php requirements only.
  $requirements = system_requirements('update');
  $requirements += update_extra_requirements();

  // If there are issues, report them.
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
  return $warnings;
}


function update_main_prepare() {
  // Some unavoidable errors happen because the database is not yet up-to-date.
  // Our custom error handler is not yet installed, so we just suppress them.
  drush_errors_off();


  // We prepare a minimal bootstrap for the update requirements check to avoid
  // reaching the PHP memory limit.
  require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
  require_once DRUPAL_ROOT . '/includes/update.inc';
  require_once DRUPAL_ROOT . '/includes/common.inc';
  require_once DRUPAL_ROOT . '/includes/entity.inc';
  include_once DRUPAL_ROOT . '/includes/unicode.inc';

  update_prepare_d7_bootstrap();
  drupal_bootstrap(DRUPAL_BOOTSTRAP_SESSION);

  require_once DRUPAL_ROOT . '/includes/install.inc';
  require_once DRUPAL_ROOT . '/includes/file.inc';
  require_once DRUPAL_ROOT . '/modules/system/system.install';

  // Load module basics.
  include_once DRUPAL_ROOT . '/includes/module.inc';
  $module_list['system']['filename'] = 'modules/system/system.module';
  module_list(TRUE, FALSE, FALSE, $module_list);
  drupal_load('module', 'system');

  // Reset the module_implements() cache so that any new hook implementations
  // in updated code are picked up.
  module_implements('', FALSE, TRUE);

  // Set up $language, since the installer components require it.
  drupal_language_initialize();

  // Set up theme system for the maintenance page.
  drupal_maintenance_theme();

  // Check the update requirements for Drupal.
  update_check_requirements();

  // update_fix_d7_requirements() needs to run before bootstrapping beyond path.  
  // So bootstrap to DRUPAL_BOOTSTRAP_LANGUAGE then include unicode.inc.
  drupal_bootstrap(DRUPAL_BOOTSTRAP_LANGUAGE);
  
  update_fix_d7_requirements();
  
  // Now proceed with a full bootstrap.
  
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  drupal_maintenance_theme();
  
  drush_errors_on();

  // Rescan and repopulate the system table to ensure we have a full picture
  // of the platform.
  drush_get_themes();
  drush_get_modules();

  include_once DRUPAL_ROOT . '/includes/batch.inc';
  drupal_load_updates();

  update_fix_compatibility();

   // Change query-strings on css/js files to enforce reload for all users.
  _drupal_flush_css_js();
  // Flush the cache of all data for the update status module.
  if (db_table_exists('cache_update')) {
    cache_clear_all('*', 'cache_update', TRUE);
  }

  module_list(TRUE, FALSE, TRUE);
}



function update_main() {
  update_main_prepare();

  $pending = update_get_update_list();


  $start = array();
  // Print a list of pending updates for this module and get confirmation.
  if (sizeof($pending)) {
    drush_print(dt('The following updates are pending:'));
    drush_print();
    foreach ($pending as $module => $updates) {
      if (sizeof($updates)) {
        drush_print($module . ' module : ');
        if (isset($updates['start'])) {
          $start[$module] = $updates['start'];
        }
        foreach ($updates['pending'] as $update) {
          drush_print($update, 2);
        }
        drush_print();
      }
    }
    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      drush_die('Aborting.');
    }

    drush_update_batch($start);
  }
  else {
    drush_log(dt("No database updates required"), 'success');
  }

}

function _update_batch_command($id) {
  update_main_prepare();
  drush_batch_command($id);
}

/**
 * Start the database update batch process.
 *
 * @param $start
 *   An array of all the modules and which update to start at.
 * @param $redirect
 *   Path to redirect to when the batch has finished processing.
 * @param $url
 *   URL of the batch processing page (should only be used for separate
 *   scripts like update.php).
 * @param $batch
 *   Optional parameters to pass into the batch API.
 * @param $redirect_callback
 *   (optional) Specify a function to be called to redirect to the progressive
 *   processing page.
 */
function drush_update_batch($start) {
  variable_set('maintenance_mode', TRUE);

  $operations = array();
  // Set the installed version so updates start at the correct place.
  foreach ($start as $module => $version) {
    drupal_set_installed_schema_version($module, $version - 1);
    $updates = drupal_get_schema_versions($module);
    $max_version = max($updates);
    if ($version <= $max_version) {
      foreach ($updates as $update) {
        if ($update >= $version) {
          $operations[] = array('drush_update_do_one', array($module, $update));
        }
      }
    }
  }
  $batch =& batch_get();
  $batch['operations'] = $operations;
  $batch += array(
    'title' => 'Updating',
    'init_message' => 'Starting updates',
    'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
    'finished' => 'drush_update_finished',
    'file' => 'includes/update.inc',
  );
  batch_set($batch);
  drush_backend_batch_process('updatedb-batch-process');
}



function drush_update_finished($success, $results, $operations) {
  // Clear the caches in case the data has been updated.
  drupal_flush_all_caches();
  variable_set('site_offline', FALSE);
}

