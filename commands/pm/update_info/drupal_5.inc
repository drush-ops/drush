<?php
// $Id$

function pm_update_filter(&$release) {
  $update = FALSE;
  switch($release['status']) {
    case UPDATE_STATUS_CURRENT:
      $status = dt('OK');
      $release['candidate_version'] = $release['recommended'];
      break;
    case UPDATE_STATUS_NOT_CURRENT:
      $status = dt('Update available');
      $release['candidate_version'] = $release['recommended'];
      $release['updatable'] = TRUE;
      break;
    case UPDATE_STATUS_NOT_SECURE:
      $status = dt('SECURITY UPDATE available');
      $release['candidate_version'] = $release['recommended'];
      $release['updatable'] = TRUE;
      break;
    case UPDATE_STATUS_REVOKED:
      $status = dt('Installed version REVOKED');
      $release['candidate_version'] = $release['recommended'];
      $release['updatable'] = TRUE;
      break;
    case UPDATE_STATUS_NOT_SUPPORTED:
      $status = dt('Installed version not supported');
      $release['candidate_version'] = $release['recommended'];
      $release['updatable'] = TRUE;
      break;
    default:
      $status = dt('Ignored: !reason', array('!reason' => $project['reason']));
      $release['title'] = $release['name'];
      $release['candidate_version'] = dt('Unknown');
      break;
  }
  return $status;
}

function pm_update_last_check() {
  return variable_get('update_status_last', 0);
}

/**
 * Command callback. Refresh update status information.
 */
function _pm_refresh() {
  drush_print(dt("Refreshing update status information ..."));
  update_status_refresh();
  drush_print(dt("Done."));
}

/**
 * Get update information for all installed projects.
 *
 * @return An array containing remote and local versions for all installed projects
 */
function _pm_get_update_info($projects = NULL) {
  // We force a refresh if the cache is not available.
  if (!cache_get('update_status_info', 'cache')) {
    _pm_refresh();
  }
  $info = update_status_get_available();
  $data = update_status_calculate_project_data($info);
  $data = pm_get_project_path($data, 'modules');
  return $data;
}

/**
 * Get project information from drupal.org.
 *
 * @param $projects An array of project names/**
 * Get project information from drupal.org.
 *
 * @param $projects An array of project names
 */
function pm_get_project_info($projects) {
  $info = array();
  $data = array();
  foreach ($projects as $project_name => $project) {
    $url = UPDATE_STATUS_DEFAULT_URL. "/$project_name/". UPDATE_STATUS_CORE_VERSION;
    $xml = drupal_http_request($url);
    $data[] = $xml->data;
  }
  if ($data) {
    $parser = new update_status_xml_parser;
    $info = $parser->parse($data);
  }
  return $info;
}