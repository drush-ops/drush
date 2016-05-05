<?php

/**
 * @file
 * Implementation of 'drush' update_status engine for any Drupal version.
 */

namespace Drush\UpdateService;

use Drush\Log\LogLevel;

class StatusInfoDrush implements StatusInfoInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($type, $engine, $config) {
    $this->engine_type = $type;
    $this->engine = $engine;
    $this->engine_config = $config;
  }

  /**
   * {@inheritdoc}
   */
  function lastCheck() {
    $older = 0;

    // Iterate all projects and get the time of the older release info.
    $projects = drush_get_projects();
    foreach ($projects as $project_name => $project) {
      $request = pm_parse_request($project_name, NULL, $projects);
      $url = Project::buildFetchUrl($request);
      $cache_file = drush_download_file_name($url);
      if (file_exists($cache_file)) {
        $ctime = filectime($cache_file);
        $older = (!$older) ? $ctime : min($ctime, $older);
      }
    }

    return $older;
  }

  /**
   * {@inheritdoc}
   */
  function refresh() {
    $release_info = drush_include_engine('release_info', 'updatexml');

    // Clear all caches for the available projects.
    $projects = drush_get_projects();
    foreach ($projects as $project_name => $project) {
      $request = pm_parse_request($project_name, NULL, $projects);
      $release_info->clearCached($request);
    }
  }

  /**
   * Get update information for all installed projects.
   *
   * @return
   *   Array of update status information.
   */
  function getStatus($projects, $check_disabled) {
    // Exclude disabled projects.
    if (!$check_disabled) {
      foreach ($projects as $project_name => $project) {
        if (!$project['status']) {
          unset($projects[$project_name]);
        }
      }
    }
    $available = $this->getAvailableReleases($projects);
    $update_info = $this->calculateUpdateStatus($available, $projects);
    return $update_info;
  }

  /**
   * Obtains release info for projects.
   */
  private function getAvailableReleases($projects) {
    drush_log(dt('Checking available update data ...'), LogLevel::OK);

    $release_info = drush_include_engine('release_info', 'updatexml');

    $available = array();
    foreach ($projects as $project_name => $project) {
      // Discard projects with unknown installation path.
      if ($project_name != 'drupal' && !isset($project['path'])) {
        continue;
      }
      drush_log(dt('Checking available update data for !project.', array('!project' => $project['label'])), LogLevel::OK);
      $request = $project_name . (isset($project['core']) ? '-' . $project['core'] : '');
      $request = pm_parse_request($request, NULL, $projects);
      $project_release_info = $release_info->get($request);
      if ($project_release_info) {
        $available[$project_name] = $project_release_info;
      }
    }

    // Clear any error set by a failed project. This avoid rollbacks.
    drush_clear_error();

    return $available;
  }

  /**
   * Calculates update status for given projects.
   */
  private function calculateUpdateStatus($available, $projects) {
    $update_info = array();
    foreach ($available as $project_name => $project_release_info) {
      // Obtain project 'global' status. NULL status is ok (project published),
      // otherwise it signals something is bad with the project (revoked, etc).
      $project_status = $this->calculateProjectStatus($project_release_info);
      // Discard custom projects.
      if ($project_status == DRUSH_UPDATESTATUS_UNKNOWN) {
        continue;
      }

      // Prepare update info.
      $project = $projects[$project_name];
      $is_core = ($project['type'] == 'core');
      $version = pm_parse_version($project['version'], $is_core);
      // If project version ends with 'dev', this is a dev snapshot.
      $install_type = (substr($project['version'], -3, 3) == 'dev') ? 'dev' : 'official';
      $project_update_info = array(
        'name'             => $project_name,
        'label'            => $project['label'],
        'path'             => isset($project['path']) ? $project['path'] : '',
        'install_type'     => $install_type,
        'existing_version' => $project['version'],
        'existing_major'   => $version['version_major'],
        'status'           => $project_status,
        'datestamp'        => empty($project['datestamp']) ? NULL : $project['datestamp'],
      );

      // If we don't have a project status yet, it means this is
      // a published project and we need to obtain its update status
      // and recommended release.
      if (is_null($project_status)) {
        $this->calculateProjectUpdateStatus($project_release_info, $project_update_info);
      }

      // We want to ship all release info data including all releases,
      // not just the ones selected by calculateProjectUpdateStatus().
      // We use it to allow the user to update to a specific version.
      unset($project_update_info['releases']);
      $update_info[$project_name] = $project_update_info + $project_release_info->getInfo();
    }

    return $update_info;
  }

  /**
   * Obtain the project status in the update service.
   *
   * This is not the update status of the installed version
   * but the project 'global' status (unpublished, revoked, etc).
   *
   * @see update_calculate_project_status().
   */
  private function calculateProjectStatus($project_release_info) {
    $project_status = NULL;

    // If connection to the update service went wrong, or the received xml
    // is malformed, we don't have a UpdateService::Project object.
    if (!$project_release_info) {
      $project_status = DRUSH_UPDATESTATUS_NOT_FETCHED;
    }
    else {
      switch ($project_release_info->getStatus()) {
        case 'insecure':
          $project_status = DRUSH_UPDATESTATUS_NOT_SECURE;
          break;
        case 'unpublished':
        case 'revoked':
          $project_status = DRUSH_UPDATESTATUS_REVOKED;
          break;
        case 'unsupported':
          $project_status = DRUSH_UPDATESTATUS_NOT_SUPPORTED;
          break;
        case 'unknown':
          $project_status = DRUSH_UPDATESTATUS_UNKNOWN;
          break;
      }
    }
    return $project_status;
  }

  /**
   * Obtain the update status of a project and the recommended release.
   *
   * This is a stripped down version of update_calculate_project_status().
   * That function has the same logic in Drupal 6,7,8.
   * Note: in Drupal 6 this is part of update_calculate_project_data().
   *
   * @see update_calculate_project_status().
   */
  private function calculateProjectUpdateStatus($project_release_info, &$project_data) {
    $available = $project_release_info->getInfo();

    /**
     * Here starts the code adapted from update_calculate_project_status().
     * Line 492 in Drupal 7.
     *
     * Changes are:
     *   - Use DRUSH_UPDATESTATUS_* constants instead of DRUSH_UPDATESTATUS_*
     *   - Remove error conditions we already handle
     *   - Remove presentation code ('extra' and 'reason' keys in $project_data)
     *   - Remove "also available" information.
     */

    // Figure out the target major version.
    $existing_major = $project_data['existing_major'];
    $supported_majors = array();
    if (isset($available['supported_majors'])) {
      $supported_majors = explode(',', $available['supported_majors']);
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without supported or recommended.
      $supported_majors[] = $available['default_major'];
    }

    if (in_array($existing_major, $supported_majors)) {
      // Still supported, stay at the current major version.
      $target_major = $existing_major;
    }
    elseif (isset($available['recommended_major'])) {
      // Since 'recommended_major' is defined, we know this is the new XML
      // format. Therefore, we know the current release is unsupported since
      // its major version was not in the 'supported_majors' list. We should
      // find the best release from the recommended major version.
      $target_major = $available['recommended_major'];
      $project_data['status'] = DRUSH_UPDATESTATUS_NOT_SUPPORTED;
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without recommended, so recommend
      // the currently defined "default_major" version.
      $target_major = $available['default_major'];
    }
    else {
      // Malformed XML file? Stick with the current version.
      $target_major = $existing_major;
    }

    // Make sure we never tell the admin to downgrade. If we recommended an
    // earlier version than the one they're running, they'd face an
    // impossible data migration problem, since Drupal never supports a DB
    // downgrade path. In the unfortunate case that what they're running is
    // unsupported, and there's nothing newer for them to upgrade to, we
    // can't print out a "Recommended version", but just have to tell them
    // what they have is unsupported and let them figure it out.
    $target_major = max($existing_major, $target_major);

    $release_patch_changed = '';
    $patch = '';

    foreach ($available['releases'] as $version => $release) {
      // First, if this is the existing release, check a few conditions.
      if ($project_data['existing_version'] === $version) {
        if (isset($release['terms']['Release type']) &&
            in_array('Insecure', $release['terms']['Release type'])) {
          $project_data['status'] = DRUSH_UPDATESTATUS_NOT_SECURE;
        }
        elseif ($release['status'] == 'unpublished') {
          $project_data['status'] = DRUSH_UPDATESTATUS_REVOKED;
        }
        elseif (isset($release['terms']['Release type']) &&
                in_array('Unsupported', $release['terms']['Release type'])) {
          $project_data['status'] = DRUSH_UPDATESTATUS_NOT_SUPPORTED;
        }
      }

      // Otherwise, ignore unpublished, insecure, or unsupported releases.
      if ($release['status'] == 'unpublished' ||
          (isset($release['terms']['Release type']) &&
           (in_array('Insecure', $release['terms']['Release type']) ||
            in_array('Unsupported', $release['terms']['Release type'])))) {
        continue;
      }

      // See if this is a higher major version than our target and discard it.
      // Note: at this point Drupal record it as an "Also available" release.
      if (isset($release['version_major']) && $release['version_major'] > $target_major) {
        continue;
      }

      // Look for the 'latest version' if we haven't found it yet. Latest is
      // defined as the most recent version for the target major version.
      if (!isset($project_data['latest_version'])
          && $release['version_major'] == $target_major) {
        $project_data['latest_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the development snapshot release for this branch.
      if (!isset($project_data['dev_version'])
          && $release['version_major'] == $target_major
          && isset($release['version_extra'])
          && $release['version_extra'] == 'dev') {
        $project_data['dev_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the 'recommended' version if we haven't found it yet (see
      // phpdoc at the top of this function for the definition).
      if (!isset($project_data['recommended'])
          && $release['version_major'] == $target_major
          && isset($release['version_patch'])) {
        if ($patch != $release['version_patch']) {
          $patch = $release['version_patch'];
          $release_patch_changed = $release;
        }
        if (empty($release['version_extra']) && $patch == $release['version_patch']) {
          $project_data['recommended'] = $release_patch_changed['version'];
          $project_data['releases'][$release_patch_changed['version']] = $release_patch_changed;
        }
      }

      // Stop searching once we hit the currently installed version.
      if ($project_data['existing_version'] === $version) {
        break;
      }

      // If we're running a dev snapshot and have a timestamp, stop
      // searching for security updates once we hit an official release
      // older than what we've got. Allow 100 seconds of leeway to handle
      // differences between the datestamp in the .info file and the
      // timestamp of the tarball itself (which are usually off by 1 or 2
      // seconds) so that we don't flag that as a new release.
      if ($project_data['install_type'] == 'dev') {
        if (empty($project_data['datestamp'])) {
          // We don't have current timestamp info, so we can't know.
          continue;
        }
        elseif (isset($release['date']) && ($project_data['datestamp'] + 100 > $release['date'])) {
          // We're newer than this, so we can skip it.
          continue;
        }
      }

      // See if this release is a security update.
      if (isset($release['terms']['Release type'])
          && in_array('Security update', $release['terms']['Release type'])) {
        $project_data['security updates'][] = $release;
      }
    }

    // If we were unable to find a recommended version, then make the latest
    // version the recommended version if possible.
    if (!isset($project_data['recommended']) && isset($project_data['latest_version'])) {
      $project_data['recommended'] = $project_data['latest_version'];
    }

    //
    // Check to see if we need an update or not.
    //

    if (!empty($project_data['security updates'])) {
      // If we found security updates, that always trumps any other status.
      $project_data['status'] = DRUSH_UPDATESTATUS_NOT_SECURE;
    }

    if (isset($project_data['status'])) {
      // If we already know the status, we're done.
      return;
    }

    // If we don't know what to recommend, there's nothing we can report.
    // Bail out early.
    if (!isset($project_data['recommended'])) {
      $project_data['status'] = DRUSH_UPDATESTATUS_UNKNOWN;
      $project_data['reason'] = t('No available releases found');
      return;
    }

    // If we're running a dev snapshot, compare the date of the dev snapshot
    // with the latest official version, and record the absolute latest in
    // 'latest_dev' so we can correctly decide if there's a newer release
    // than our current snapshot.
    if ($project_data['install_type'] == 'dev') {
      if (isset($project_data['dev_version']) && $available['releases'][$project_data['dev_version']]['date'] > $available['releases'][$project_data['latest_version']]['date']) {
        $project_data['latest_dev'] = $project_data['dev_version'];
      }
      else {
        $project_data['latest_dev'] = $project_data['latest_version'];
      }
    }

    // Figure out the status, based on what we've seen and the install type.
    switch ($project_data['install_type']) {
      case 'official':
        if ($project_data['existing_version'] === $project_data['recommended'] || $project_data['existing_version'] === $project_data['latest_version']) {
          $project_data['status'] = DRUSH_UPDATESTATUS_CURRENT;
        }
        else {
          $project_data['status'] = DRUSH_UPDATESTATUS_NOT_CURRENT;
        }
        break;

      case 'dev':
        $latest = $available['releases'][$project_data['latest_dev']];
        if (empty($project_data['datestamp'])) {
          $project_data['status'] = DRUSH_UPDATESTATUS_NOT_CHECKED;
        }
        elseif (($project_data['datestamp'] + 100 > $latest['date'])) {
          $project_data['status'] = DRUSH_UPDATESTATUS_CURRENT;
        }
        else {
          $project_data['status'] = DRUSH_UPDATESTATUS_NOT_CURRENT;
        }
        break;

      default:
        $project_data['status'] = DRUSH_UPDATESTATUS_UNKNOWN;
    }
  }
}

