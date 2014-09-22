<?php

/**
 * @file
 * Implementation of 'drupal' update_info engine for Drupal 6.
 */

/**
 * Returns a human readable message based on update status of a project.
 *
 * It also may alter the project object and set $project['updateable']
 * and $project['candidate_version'].
 *
 * @see pm_release_recommended()
 *
 * Project statuses in Drupal 6 are:
 * - UPDATE_NOT_SECURE
 * - UPDATE_REVOKED
 * - UPDATE_NOT_SUPPORTED
 * - UPDATE_NOT_CURRENT
 * - UPDATE_CURRENT
 * - UPDATE_NOT_CHECKED
 * - UPDATE_UNKNOWN
 * - UPDATE_NOT_FETCHED
 *
 */
function pm_update_filter(&$project) {
  switch($project['status']) {
    case UPDATE_NOT_SECURE:
      $status = dt('SECURITY UPDATE available');
      pm_release_recommended($project);
      break;
    case UPDATE_REVOKED:
      $status = dt('Installed version REVOKED');
      pm_release_recommended($project);
      break;
    case UPDATE_NOT_SUPPORTED:
      $status = dt('Installed version not supported');
      pm_release_recommended($project);
      break;
    case UPDATE_NOT_CURRENT:
      $status = dt('Update available');
      pm_release_recommended($project);
      break;
    case UPDATE_NOT_CHECKED:
      $status = dt('Unable to check status');
      break;
    case UPDATE_CURRENT:
      $status = dt('Up to date');
      $project['candidate_version'] = $project['recommended'];
      break;
    case UPDATE_UNKNOWN:
    case UPDATE_NOT_FETCHED:
    default:
      $status = dt('Unknown');
      break;
  }
  return $status;
}

/**
 * Returns time of last check of available updates.
 */
function pm_update_last_check() {
  return variable_get('update_last_check', 0);
}

/**
 * Command callback. Refresh update status information.
 */
function _pm_refresh() {
  drush_print(dt("Refreshing update status information ..."));
  update_refresh();
  drush_print(dt("Done."));
}

/**
 * Get update information for all installed projects.
 *
 * @return An array containing remote and local versions for all
 *   installed projects.
 */
function _pm_get_update_info($projects) {
  // We force a refresh if the cache is not available.
  if (!cache_get('update_available_releases', 'cache_update')) {
    _pm_refresh();
  }

  $info = update_get_available(TRUE);

  // Force to invalidate some update_status caches that are only cleared
  // when visiting update status report page.
  if (function_exists('_update_cache_clear')) {
    _update_cache_clear('update_project_data');
    _update_cache_clear('update_project_projects');
  }

  $data = update_calculate_project_data($info);
  foreach ($data as $project_name => $project) {
    // Discard custom projects.
    if ($project['status'] == UPDATE_UNKNOWN) {
      unset($data[$project_name]);
      continue;
    }
    // Discard projects with unknown installation path.
    if ($project_name != 'drupal' && !isset($projects[$project_name]['path'])) {
      unset($data[$project_name]);
      continue;
    }
    // Allow to update disabled projects.
    if (in_array($project['project_type'], array('disabled-module', 'disabled-theme'))) {
      $data[$project_name]['project_type'] = substr($project['project_type'], strpos($project['project_type'], '-') + 1);
    }
    // Add some info from the project to $data.
    $data[$project_name] += array(
      'path'  => isset($projects[$project_name]['path']) ? $projects[$project_name]['path'] : '',
      'label' => $projects[$project_name]['label'],
    );
  }

  return $data;
}

