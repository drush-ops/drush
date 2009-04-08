<?php

function pm_update_filter(&$releases, &$updateable, &$rows) {
  foreach ($releases as $release) {
    if (!$release['title']) {
      continue;
    }

    switch($release['status']) {
      case UPDATE_CURRENT:
        $status = dt('Up to date');
        $release['candidate_version'] = $release['recommended'];
        break;
      case UPDATE_NOT_CURRENT:
        $status = dt('Update available');
        $release['candidate_version'] = $release['recommended'];
        $updateable[$release['name']] = $release;
        break;
      case UPDATE_NOT_SECURE:
        $status = dt('SECURITY UPDATE available');
        $release['candidate_version'] = $release['recommended'];
        $updateable[$release['name']] = $release;
        break;
      case UPDATE_REVOKED:
        $status = dt('Intalled version REVOKED');
        $release['candidate_version'] = $release['recommended'];
        $updateable[$project['name']] = $release;
        break;
      case DRUSH_PM_REQUESTED_UPDATE:
        $status = dt('Specified version available');
        $updateable[$release['name']] = $release;
        break;
      case DRUSH_PM_REQUESTED_CURRENT:
        $status = dt('Specified version already installed');
        break;
      case UPDATE_NOT_SUPPORTED:
        $status = dt('Installed version not supported');
        $release['candidate_version'] = $release['recommended'];
        $updateable[$project['name']] = $release;
        break;
      case UPDATE_NOT_CHECKED:
        $status = dt('Unable to check status');
        break;
      case UPDATE_UNKNOWN:
      default:
        $status = dt('Unknown');
        break;
    }

    $rows[] = array($release['title'], $release['existing_version'], $release['candidate_version'], $status);
  }
}

function pm_update_last_check() {
  return variable_get('update_last_check', 0);
}

/**
 * Command callback. Refresh update status information.
 */
function pm_refresh() {
  drush_print(dt("Refreshing update status information ..."));
  update_refresh();
  drush_print(dt("Done."));
}

/**
 * Get update information for all installed projects.
 *
 * @return An array containing remote and local versions for all installed projects
 */
function _pm_get_update_info($projects = NULL) {
  $info = update_get_available();
  $data = update_calculate_project_data($info);
  $data = pm_get_project_path($data, 'includes');
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