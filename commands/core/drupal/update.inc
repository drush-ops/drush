<?php
/**
 * @file
 *   Update.php for provisioned sites.
 *   This file is a derivative of the standard drupal update.php,
 *   which has been modified to allow being run from the command
 *   line.
 */

use Drush\Log\LogLevel;

/**
 * Drupal's update.inc has functions that are in previous update_X.inc files
 * for example, update_check_incompatibility() which can prove useful when
 * enabling modules.
 */
require_once DRUSH_DRUPAL_CORE . '/includes/update.inc';

use Drupal\Core\Utility\Error;
use Drupal\Core\Entity\EntityStorageException;
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
function drush_update_do_one($module, $number, $dependency_map,  &$context) {
  $function = $module . '_update_' . $number;

  // If this update was aborted in a previous step, or has a dependency that
  // was aborted in a previous step, go no further.
  if (!empty($context['results']['#abort']) && array_intersect($context['results']['#abort'], array_merge($dependency_map, array($function)))) {
    return;
  }

  $context['log'] = FALSE;

  \Drupal::moduleHandler()->loadInclude($module, 'install');

  $ret = array();
  if (function_exists($function)) {
    try {
      if ($context['log']) {
        Database::startLog($function);
      }

      drush_log("Executing " . $function);
      $ret['results']['query'] = $function($context['sandbox']);
      $ret['results']['success'] = TRUE;
    }
    // @TODO We may want to do different error handling for different exception
    // types, but for now we'll just print the message.
    catch (Exception $e) {
      $ret['#abort'] = array('success' => FALSE, 'query' => $e->getMessage());
      drush_set_error('DRUPAL_EXCEPTION', $e->getMessage());
    }

    if ($context['log']) {
      $ret['queries'] = Database::getLog($function);
    }
  }
  else {
    $ret['#abort'] = array('success' => FALSE);
    drush_set_error('DRUSH_UPDATE_FUNCTION_NOT_FOUND', dt('Update function @function not found', array('@function' => $function)));
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
    // Record this function in the list of updates that were aborted.
    $context['results']['#abort'][] = $function;
  }

  // Record the schema update if it was completed successfully.
  if ($context['finished'] == 1 && empty($ret['#abort'])) {
    drupal_set_installed_schema_version($module, $number);
  }

  $context['message'] = 'Performing ' . $function;
}

/**
 * Clears caches and rebuilds the container.
 *
 * This is called in between regular updates and post updates. Do not use
 * drush_drupal_cache_clear_all() as the cache clearing and container rebuild
 * must happen in the same process that the updates are run in.
 *
 * Drupal core's update.php uses drupal_flush_all_caches() directly without
 * explicitly rebuilding the container as the container is rebuilt on the next
 * HTTP request of the batch.
 *
 * @see drush_drupal_cache_clear_all()
 * @see \Drupal\system\Controller\DbUpdateController::triggerBatch()
 */
function drush_update_cache_rebuild() {
  drupal_flush_all_caches();
  \Drupal::service('kernel')->rebuildContainer();
}

function update_main() {
  // In D8, we expect to be in full bootstrap.
  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);

  require_once DRUPAL_ROOT . '/core/includes/install.inc';
  require_once DRUPAL_ROOT . '/core/includes/update.inc';
  drupal_load_updates();
  update_fix_compatibility();

  // Check requirements before updating.
  if (!drush_update_check_requirements()) {
    if (!drush_confirm(dt('Requirements check reports errors. Do you wish to continue?'))) {
        return drush_user_abort();
    }
  }

  // Pending hook_update_N() implementations.
  $pending = update_get_update_list();

  // Pending hook_post_update_X() implementations.
  $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateInformation();

  $start = array();

  $change_summary = [];
  if (drush_get_option('entity-updates', FALSE)) {
    $change_summary = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();
  }

  // Print a list of pending updates for this module and get confirmation.
  if (count($pending) || count($change_summary) || count($post_updates)) {
    drush_print(dt('The following updates are pending:'));
    drush_print();

    foreach ($change_summary as $entity_type_id => $changes) {
      drush_print($entity_type_id . ' entity type : ');
      foreach ($changes as $change) {
        drush_print(strip_tags($change), 2);
      }
    }

    foreach (array('update', 'post_update') as $update_type) {
      $updates = $update_type == 'update' ? $pending : $post_updates;
      foreach ($updates as $module => $updates) {
        if (isset($updates['start'])) {
          drush_print($module . ' module : ');
          if (!empty($updates['pending'])) {
            $start += [$module => array()];

            $start[$module] = array_merge($start[$module], $updates['pending']);
            foreach ($updates['pending'] as $update) {
              drush_print(strip_tags($update), 2);
            }
          }
          drush_print();
        }
      }
    }

    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      return drush_user_abort();
    }

    drush_update_batch($start);
  }
  else {
    drush_log(dt("No database updates required"), LogLevel::SUCCESS);
  }

  return count($pending) + count($change_summary) + count($post_updates);
}

/**
 * Check update requirements and report any errors.
 */
function drush_update_check_requirements() {
  $continue = TRUE;

  \Drupal::moduleHandler()->resetImplementations();
  $requirements = update_check_requirements();
  $severity = drupal_requirements_severity($requirements);

  // If there are issues, report them.
  if ($severity != REQUIREMENT_OK) {
    if ($severity === REQUIREMENT_ERROR) {
      $continue = FALSE;
    }
    foreach ($requirements as $requirement) {
      if (isset($requirement['severity']) && $requirement['severity'] != REQUIREMENT_OK) {
        $message = isset($requirement['description']) ? $requirement['description'] : '';
        if (isset($requirement['value']) && $requirement['value']) {
          $message .= ' (Currently using '. $requirement['title'] .' '. $requirement['value'] .')';
        }
        $log_level = $requirement['severity'] === REQUIREMENT_ERROR ? LogLevel::ERROR : LogLevel::WARNING;
        drush_log($message, $log_level);
      }
    }
  }

  return $continue;
}

function _update_batch_command($id) {
  // In D8, we expect to be in full bootstrap.
  drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_FULL);

  drush_batch_command($id);
}

/**
 * Start the database update batch process.
 */
function drush_update_batch() {
  $start = drush_get_update_list();
  // Resolve any update dependencies to determine the actual updates that will
  // be run and the order they will be run in.
  $updates = update_resolve_dependencies($start);

  // Store the dependencies for each update function in an array which the
  // batch API can pass in to the batch operation each time it is called. (We
  // do not store the entire update dependency array here because it is
  // potentially very large.)
  $dependency_map = array();
  foreach ($updates as $function => $update) {
    $dependency_map[$function] = !empty($update['reverse_paths']) ? array_keys($update['reverse_paths']) : array();
  }

  $operations = array();

  foreach ($updates as $update) {
    if ($update['allowed']) {
      // Set the installed version of each module so updates will start at the
      // correct place. (The updates are already sorted, so we can simply base
      // this on the first one we come across in the above foreach loop.)
      if (isset($start[$update['module']])) {
        drupal_set_installed_schema_version($update['module'], $update['number'] - 1);
        unset($start[$update['module']]);
      }
      // Add this update function to the batch.
      $function = $update['module'] . '_update_' . $update['number'];
      $operations[] = array('drush_update_do_one', array($update['module'], $update['number'], $dependency_map[$function]));
    }
  }

  // Apply post update hooks.
  $post_updates = \Drupal::service('update.post_update_registry')->getPendingUpdateFunctions();
  if ($post_updates) {
    $operations[] = ['drush_update_cache_rebuild', []];
    foreach ($post_updates as $function) {
      $operations[] = ['update_invoke_post_update', [$function]];
    }
  }

  // Lastly, perform entity definition updates, which will update storage
  // schema if needed. If module update functions need to work with specific
  // entity schema they should call the entity update service for the specific
  // update themselves.
  // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyEntityUpdate()
  // @see \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface::applyFieldUpdate()
  if (drush_get_option('entity-updates', FALSE) &&  \Drupal::entityDefinitionUpdateManager()->needsUpdates()) {
    $operations[] = array('drush_update_entity_definitions', array());
  }

  $batch['operations'] = $operations;
  $batch += array(
    'title' => 'Updating',
    'init_message' => 'Starting updates',
    'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
    'finished' => 'drush_update_finished',
    'file' => 'includes/update.inc',
  );
  batch_set($batch);
  \Drupal::service('state')->set('system.maintenance_mode', TRUE);
  drush_backend_batch_process('updatedb-batch-process');
  \Drupal::service('state')->set('system.maintenance_mode', FALSE);
}

/**
 * Apply entity schema updates.
 */
function drush_update_entity_definitions(&$context) {
  try {
    \Drupal::entityDefinitionUpdateManager()->applyUpdates();
  }
  catch (EntityStorageException $e) {
    watchdog_exception('update', $e);
    $variables = Error::decodeException($e);
    unset($variables['backtrace']);
    // The exception message is run through
    // \Drupal\Component\Utility\SafeMarkup::checkPlain() by
    // \Drupal\Core\Utility\Error::decodeException().
    $ret['#abort'] = array('success' => FALSE, 'query' => t('%type: !message in %function (line %line of %file).', $variables));
    $context['results']['core']['update_entity_definitions'] = $ret;
    $context['results']['#abort'][] = 'update_entity_definitions';
  }
}

// Copy of protected \Drupal\system\Controller\DbUpdateController::getModuleUpdates.
function drush_get_update_list() {
  $return = array();
  $updates = update_get_update_list();
  foreach ($updates as $module => $update) {
    $return[$module] = $update['start'];
  }

  return $return;
}

/**
 * Process and display any returned update output.
 *
 * @see \Drupal\system\Controller\DbUpdateController::batchFinished()
 * @see \Drupal\system\Controller\DbUpdateController::results()
 */
function drush_update_finished($success, $results, $operations) {

  if (!drush_get_option('cache-clear', TRUE)) {
    drush_log(dt("Skipping cache-clear operation due to --cache-clear=0 option."), LogLevel::WARNING);
  }
  else {
    drupal_flush_all_caches();
  }

  foreach ($results as $module => $updates) {
    if ($module != '#abort') {
      foreach ($updates as $number => $queries) {
        foreach ($queries as $query) {
          // If there is no message for this update, don't show anything.
          if (empty($query['query'])) {
            continue;
          }

          if ($query['success']) {
            drush_log(strip_tags($query['query']));
          }
          else {
            drush_set_error(dt('Failed: ') . strip_tags($query['query']));
          }
        }
      }
    }
  }
}

/**
 * Return a 2 item array with
 *  - an array where each item is a 3 item associative array describing a pending update.
 *  - an array listing the first update to run, keyed by module.
 */
function updatedb_status() {
  $pending = update_get_update_list();

  $return = array();
  // Ensure system module's updates run first.
  $start['system'] = array();

  foreach (\Drupal::entityDefinitionUpdateManager()->getChangeSummary() as $entity_type_id => $changes) {
    foreach ($changes as $change) {
      $return[] = array(
        'module' => dt('@type entity type', array('@type' => $entity_type_id)), 'update_id' => '', 'description' => strip_tags($change));
    }
  }

  // Print a list of pending updates for this module and get confirmation.
  foreach ($pending as $module => $updates) {
    if (isset($updates['start']))  {
      foreach ($updates['pending'] as $update_id => $description) {
        // Strip cruft from front.
        $description = str_replace($update_id . ' -   ', '', $description);
        $return[] = array('module' => ucfirst($module), 'update_id' => $update_id, 'description' => $description);
      }
      if (isset($updates['start'])) {
        $start[$module] = $updates['start'];
      }
    }
  }

  return array($return, $start);
}

/**
 * Apply pending entity schema updates.
 */
function entity_updates_main() {
  $change_summary = \Drupal::entityDefinitionUpdateManager()->getChangeSummary();
  if (!empty($change_summary)) {
    drush_print(dt('The following updates are pending:'));
    drush_print();

    foreach ($change_summary as $entity_type_id => $changes) {
      drush_print($entity_type_id . ' entity type : ');
      foreach ($changes as $change) {
        drush_print(strip_tags($change), 2);
      }
    }

    if (!drush_confirm(dt('Do you wish to run all pending updates?'))) {
      return drush_user_abort();
    }

    $operations[] = array('drush_update_entity_definitions', array());


    $batch['operations'] = $operations;
    $batch += array(
      'title' => 'Updating',
      'init_message' => 'Starting updates',
      'error_message' => 'An unrecoverable error has occurred. You can find the error message below. It is advised to copy it to the clipboard for reference.',
      'finished' => 'drush_update_finished',
      'file' => 'includes/update.inc',
    );
    batch_set($batch);
    \Drupal::service('state')->set('system.maintenance_mode', TRUE);
    drush_backend_batch_process('updatedb-batch-process');
    \Drupal::service('state')->set('system.maintenance_mode', FALSE);
  }
  else {
    drush_log(dt("No entity schema updates required"), LogLevel::SUCCESS);
  }
}
