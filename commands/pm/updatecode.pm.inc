<?php
// $Id$

/**
 * Command callback. Displays update status info and allows to update installed modules.
 * Pass specific projects as arguments, otherwise we update all that have candidate releases.
 *
 * This command prompts for confirmation before updating, so it is safe to run just to check on
 * In this case, say at the confirmation prompt.
 */
function drush_pm_updatecode() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  // Get update status information.
  $releases = _pm_get_update_info();

  // Get specific requests
  $requests = func_get_args();

  // Parse out project name and version
  $requests = pm_parse_project_version($requests);

  // Preprocess releases
  if (!empty($requests)) {
    // Force update projects where a specific version is reqested
    foreach ($requests as $project => $request) {
      if (!isset($releases[$project])) {
        // Catch projects with no version data (common for CVS checkouts
        // if you don't have CVS deploy installed).
        $releases[$project] = array(
          'title' => $project,
          'existing_version' => 'Unknown',
          'status'=> DRUSH_PM_NO_VERSION,
        );
      }
      else if (!empty($request['version'])) {
        // Match the requested release
        $release = pm_get_release($request, $releases[$project]);
        if (!$release) {
          $releases[$project]['status'] = DRUSH_PM_REQUESTED_NOT_FOUND;
        }
        else if ($release['version'] == $releases[$project]['existing_version']) {
          $releases[$project]['status'] = DRUSH_PM_REQUESTED_CURRENT;
        }
        else {
          $releases[$project]['status'] = DRUSH_PM_REQUESTED_UPDATE;
        }
        // Set the candidate version to the requested release
        $releases[$project]['candidate_version'] = $release['version'];
      }
    }
  }

  // Table headers.
  $rows[] = array(dt('Name'), dt('Installed version'), dt('Proposed version'), dt('Status'));

  // Process releases, notifying user of status and building a list of proposed updates
  $updateable = pm_project_filter($releases, $rows);

  $last = pm_update_last_check();
  drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));
  drush_print();
  drush_print(dt("Update status information on all installed and enabled Drupal modules:"));
  drush_print_table($rows, TRUE);
  drush_print();

  // If specific project updates were requested then remove releases for all others
  if (!empty($requests)) {
    foreach ($updateable as $project => $release) {
      if (!isset($requests[$project])) {
        unset($updateable[$project]);
      }
    }
  }

  if (isset($updateable['drupal'])) {
    drush_print("NOTE: A code update for the Drupal core is available. \nDrupal itself can't yet be updated by this tool. Please update Drupal manually.\n");
    unset($updateable['drupal']);
  }

  if (empty($updateable)) {
    return drush_log(dt('No code updates available.'), 'ok');
  }

  // Offer to update to the identified releases
  return pm_update_packages($updateable);
}

/**
 * Update packages according to an array of releases, following interactive
 * confirmation from the user.
 *
 * @param $releases
 *   An array of releases from the drupal.org update service, with an additional
 *   array key candidate_version that specifies the version to be installed.
 */
function pm_update_packages($releases) {
  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  drush_print(dt('Code updates will be made to the following projects:'));
  foreach($releases as $release) {
    $print .= $release['title'] . " [" . $release['name'] . '-' . $release['candidate_version'] . "], ";
  }
  drush_print(substr($print, 0, strlen($print)-2));
  drush_print();
  drush_print(dt("Note: Updated modules can potentially break your site. It's not recommended to update production sites without prior testing."));
  drush_print(dt("Note: A backup of your package will be stored to backups directory if it is not managed by a supported version control system."));
  drush_print(dt('Note: If you have made any modifications to any file that belongs to one of these projects, you will have to migrate those modifications after updating.'));
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_die('Aborting.');
  }

  // Now we start the actual updating.
  $updated = array();
  foreach($releases as $release) {
    drush_log(dt('Starting to update !project code at !dir...', array('!project' => $release['title'], '!dir' => $release['path'])));
    if (empty($release['path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_NO_PROJECT_PATH', dt('The !project project path is not available, perhaps the !type is enabled but has been deleted from disk.', array('!project' => $release['name'], '!type' => $release['project_type'])));
    }
    $release['full_project_path'] = $drupal_root . '/' . $release['path'];
    // Check that the directory exists, and is where we expect it to be.
    if (stripos($release['path'], $release['project_type']) === FALSE || !is_dir($release['full_project_path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_PATH_NOT_FOUND', dt('The !project directory could not be found within the !types directory at !full_project_path, perhaps the project is enabled but has been deleted from disk.', array('!project' => $release['name'], '!type' => $release['project_type'], '!full_project_path' => $release['full_project_path'])));
    }

    // Check we have a version control system, and it clears it's pre-flight.
    if (!$version_control = drush_pm_include_version_control($release['full_project_path'])) {
      return FALSE;
    }
    if (!$version_control->pre_update($release)) {
      return FALSE;
    }
    // Add the project to a context so we can roll back if needed.
    $updated[] = $release;
    drush_set_context('DRUSH_PM_UPDATED', $updated);

    if (!package_handler_update_project($release['name'], $release['releases'][$release['candidate_version']], $release['full_project_path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_FAILED', dt('Updating project !project failed. Attempting to roll back to previously installed version.', array('!project' => $release['name'])));
    }
    else {
      drush_print(dt('Project !project was updated successfully. Installed version is now !version.', array('!project' => $release['name'], '!version' => $release['candidate_version'])));
      drush_command_invoke_all('pm_post_update', $release['name'], $release['releases'][$release['candidate_version']]);
      $version_control->post_update($release);
    }
  }
  // Clear the cache, since some modules could have moved around.
  drush_drupal_cache_clear_all();
}

function drush_pm_updatecode_rollback() {
  $releases = array_reverse(drush_get_context('DRUSH_PM_UPDATED', array()));
  foreach($releases as $release) {
    drush_log(dt('Rolling back update of !project code ...', array('!project' => $release['title'])));

    // Check we have a version control system, and it clears it's pre-flight.
    if (!$version_control = drush_pm_include_version_control($release['path'])) {
      return FALSE;
    }
    $version_control->rollback($release);
  }
}

function pm_project_filter(&$releases, &$rows) {
  $updateable = array();
  foreach ($releases as $key => $release) {
    if (!$release['title']) {
      continue;
    }
    switch($release['status']) {
      case DRUSH_PM_REQUESTED_UPDATE:
        $status = dt('Specified version available');
        $release['updatable'] = TRUE;
        break;
      case DRUSH_PM_REQUESTED_CURRENT:
        $status = dt('Specified version already installed');
        break;
      case DRUSH_PM_NO_VERSION:
        $status = dt('No version information found (if you have a CVS checkout you should install CVS Deploy module)');
        break;
      case DRUSH_PM_REQUESTED_NOT_FOUND:
        $status = dt('Specified version not found');
        break;
      default:
        $status = pm_update_filter($release);
        break;
    }
    if (!$release['candidate_version']) {
      $release['candidate_version'] = $release['existing_version']; // Default to no change
    }
    if ($release['updatable']) {
      $updatable[$key] = $release;
    }
    $rows[] = array($release['title'], $release['existing_version'], $release['candidate_version'], $status);
  }
  return $updatable;
}

/**
 * Set a release to a recommended version (if available), and set as updatable.
 */
function pm_release_recommended(&$release) {
  if (isset($release['recommended'])) {
    $release['candidate_version'] = $release['recommended'];
    $release['updatable'] = TRUE;
  }
}

/**
 * Get the a best release match for a requested update.
 *
 * @param $project A project information array for the requested project
 * @param $info A project information array for this project, as returned by an update service from pm_get_project_info()
 */
function pm_get_release($project, $info) {
  $minor = '';
  $version_patch_changed = '';
  if ($project['version']) {
    // The user specified a specific version - try to find that exact version
    foreach($info['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // Straight match
      if (!isset($recommended_version) && $release['version'] == $project['version']) {
        $recommended_version = $version;
      }
    }
  }
  else {
    // No version specified - try to find the best version we can
    foreach($info['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // If we haven't found a recommended version yet, put the dev
      // version as recommended and hope it gets overwritten later.
      // Look for the 'latest version' if we haven't found it yet.
      // Latest version is defined as the most recent version for the
      // default major version.
      if (!isset($latest_version) && $release['version_major'] == $info['default_major']) {
        $latest_version = $version;
      }

      if (!isset($recommended_version) && $release['version_major'] == $info['default_major']) {
        if ($minor != $release['version_patch']) {
          $minor = $release['version_patch'];
          $version_patch_changed = $version;
        }
        if (empty($release['version_extra']) && $minor == $release['version_patch']) {
          $recommended_version = $version_patch_changed;
        }
        continue;
      }
    }
  }
  if (isset($recommended_version)) {
    return $info['releases'][$recommended_version];
  }
  else if (isset($latest_version)) {
    return $info['releases'][$latest_version];
  }
  else {
    return false;
  }
}
