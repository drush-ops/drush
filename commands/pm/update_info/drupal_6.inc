<?php

function pm_update_filter(&$project) {
  $update = FALSE;
  switch($project['status']) {
    case UPDATE_CURRENT:
      $status = dt('Up to date');
      $project['candidate_version'] = $project['recommended'];
      break;
    case UPDATE_NOT_CURRENT:
      $status = dt('Update available');
      pm_release_recommended($project);
      break;
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
    case UPDATE_NOT_CHECKED:
      $status = dt('Unable to check status');
      break;
    default:
      $status = dt('Unknown');
      break;
  }
  return $status;
}

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
 * @return An array containing remote and local versions for all installed projects
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
    // Allow to update disabled projects.
    if (in_array($project['project_type'], array('disabled-module', 'disabled-theme'))) {
      $data[$project_name]['project_type'] = substr($project['project_type'], strpos($project['project_type'], '-') + 1);
    }
    // Set project label (title + name).
    $data[$project_name]['label'] = $projects[$project_name]['label'];
    // Set 'includes' key to all extensions. By default it only contain enabled
    // extensions and _pm_get_project_path() needs all of them.
    $data[$project_name]['includes'] = drupal_map_assoc($projects[$project_name]['extensions']);
  }
  $data = _pm_get_project_path($data, 'includes');

  return $data;
}

/**
 * Get project information from drupal.org.
 *
 * @param $projects An array of project names
 */
function pm_get_project_info($projects) {
  $info = array();
  $data = array();
  foreach ($projects as $project_name => $project) {
    $url = UPDATE_DEFAULT_URL. "/$project_name/". drush_drupal_major_version() . '.x';
    $xml = drupal_http_request($url);
    $data[] = $xml->data;
  }
  if ($data) {
    include_once drupal_get_path('module', 'update') .'/update.fetch.inc';
    $parser = new update_xml_parser;
    $info = $parser->parse($data);
  }
  return $info;
}
