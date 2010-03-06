<?php
// $Id$

/**
 * Command callback. Displays update status info and allows to update installed projects.
 * Pass specific projects as arguments, otherwise we update all that have candidate releases.
 *
 * This command prompts for confirmation before updating, so it is safe to run just to check on
 * In this case, say at the confirmation prompt.
 */
function drush_pm_updatecode() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  // Get update status information.
  $projects = _pm_get_update_info();

  // Get specific requests
  $requests = func_get_args();

  // Parse out project name and version
  $requests = pm_parse_project_version($requests);

  // Preprocess releases
  if (!empty($requests)) {
    // Force update projects where a specific version is reqested
    foreach ($requests as $name => $request) {
      if (!isset($projects[$name])) {
        // Catch projects with no version data (common for CVS checkouts
        // if you don't have CVS deploy installed).
        $projects[$name] = array(
          'title' => $name,
          'existing_version' => 'Unknown',
          'status'=> DRUSH_PM_NO_VERSION,
        );
      }
      else if (!empty($request['version'])) {
        // Match the requested release
        $release = pm_get_release($request, $projects[$name]);
        if (!$release) {
          $projects[$name]['status'] = DRUSH_PM_REQUESTED_NOT_FOUND;
        }
        else if ($release['version'] == $projects[$name]['existing_version']) {
          $projects[$name]['status'] = DRUSH_PM_REQUESTED_CURRENT;
        }
        else {
          $projects[$name]['status'] = DRUSH_PM_REQUESTED_UPDATE;
        }
        // Set the candidate version to the requested release
        $projects[$name]['candidate_version'] = $release['version'];
      }
    }
  }

  // Table headers.
  $rows[] = array(dt('Name'), dt('Installed version'), dt('Proposed version'), dt('Status'));

  // Process releases, notifying user of status and building a list of proposed updates
  $updateable = pm_project_filter($projects, $rows);

  // Pipe preparation
  if (drush_get_context('DRUSH_PIPE')) {
    $pipe = "";
    foreach($projects as $project){
      $pipe .= $project['name']. " ";
      $pipe .= $project['existing_version']. " ";
      $pipe .= $project['candidate_version']. " ";
      $pipe .= str_replace(' ', '-', pm_update_filter($project)). "\n";
    }
    drush_print_pipe($pipe);
    // Automatically curtail update process if in pipe mode
    $updateable = FALSE;
  }

  $last = pm_update_last_check();
  drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));
  drush_print();
  drush_print(dt("Update status information on all installed and enabled Drupal projects:"));
  drush_print_table($rows, TRUE);
  drush_print();

  // If specific project updates were requested then remove releases for all others
  if (!empty($requests)) {
    foreach ($updateable as $name => $project) {
      if (!isset($requests[$name])) {
        unset($updateable[$name]);
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
 * @param $projects
 *   An array of projects from the drupal.org update service, with an additional
 *   array key candidate_version that specifies the version to be installed.
 */
function pm_update_packages($projects) {
  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  drush_print(dt('Code updates will be made to the following projects:'));
  foreach($projects as $project) {
    $print .= $project['title'] . " [" . $project['name'] . '-' . $project['candidate_version'] . "], ";
  }
  drush_print(substr($print, 0, strlen($print)-2));
  drush_print();
  drush_print(dt("Note: Updated projects can potentially break your site. It is NOT recommended to update production sites without prior testing."));
  drush_print(dt("Note: A backup of your package will be stored to backups directory if it is not managed by a supported version control system."));
  drush_print(dt('Note: If you have made any modifications to any file that belongs to one of these projects, you will have to migrate those modifications after updating.'));
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_die('Aborting.');
  }

  // Now we start the actual updating.
  $updated = array();
  foreach($projects as $project) {
    if (empty($project['path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_NO_PROJECT_PATH', dt('The !project project path is not available, perhaps the !type is enabled but has been deleted from disk.', array('!project' => $project['name'], '!type' => $project['project_type'])));
    }
    drush_log(dt('Starting to update !project code at !dir...', array('!project' => $project['title'], '!dir' => $project['path'])));
    // Create the projects directory and base (parent) directory.
    $project['full_project_path'] = $drupal_root . '/' . $project['path'];
    $project['base_project_path'] = dirname($project['full_project_path']);
    // Check that the directory exists, and is where we expect it to be.
    if (stripos($project['path'], $project['project_type']) === FALSE || !is_dir($project['full_project_path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_PATH_NOT_FOUND', dt('The !project directory could not be found within the !types directory at !full_project_path, perhaps the project is enabled but has been deleted from disk.', array('!project' => $project['name'], '!type' => $project['project_type'], '!full_project_path' => $project['full_project_path'])));
    }

    // Check we have a version control system, and it clears it's pre-flight.
    if (!$version_control = drush_pm_include_version_control($project['full_project_path'])) {
      return FALSE;
    }
    if (!$version_control->pre_update($project)) {
      return FALSE;
    }
    // Add the project to a context so we can roll back if needed.
    $updated[] = $project;
    drush_set_context('DRUSH_PM_UPDATED', $updated);

    if (!package_handler_update_project($project, $project['releases'][$project['candidate_version']])) {
      return drush_set_error('DRUSH_PM_UPDATING_FAILED', dt('Updating project !project failed. Attempting to roll back to previously installed version.', array('!project' => $project['name'])));
    }
    else {
      drush_print(dt('Project !project was updated successfully. Installed version is now !version.', array('!project' => $project['name'], '!version' => $project['candidate_version'])));
      drush_command_invoke_all('pm_post_update', $project['name'], $project['releases'][$project['candidate_version']]);
      $version_control->post_update($project);
    }
  }
  // Clear the cache, since some projects could have moved around.
  drush_drupal_cache_clear_all();
}

function drush_pm_updatecode_rollback() {
  $projects = array_reverse(drush_get_context('DRUSH_PM_UPDATED', array()));
  foreach($projects as $project) {
    drush_log(dt('Rolling back update of !project code ...', array('!project' => $project['title'])));

    // Check we have a version control system, and it clears it's pre-flight.
    if (!$version_control = drush_pm_include_version_control($project['path'])) {
      return FALSE;
    }
    $version_control->rollback($project);
  }
}

function pm_project_filter(&$projects, &$rows) {
  $updateable = array();
  foreach ($projects as $key => $project) {
    if (!$project['title']) {
      continue;
    }
    switch($project['status']) {
      case DRUSH_PM_REQUESTED_UPDATE:
        $status = dt('Specified version available');
        $project['updateable'] = TRUE;
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
        $status = pm_update_filter($project);
        break;
    }

    // Persist candidate_version in $projects (plural).
    if (empty($project['candidate_version'])) {
      $projects[$key]['candidate_version'] = $project['existing_version']; // Default to no change
    }
    else {
      $projects[$key]['candidate_version'] = $project['candidate_version'];
    }
    if (!empty($project['updateable'])) {
      $updateable[$key] = $project;
    }
    $rows[] = array($project['title'], $project['existing_version'], $projects[$key]['candidate_version'], $status);
  }
  return $updateable;
}

/**
 * Set a release to a recommended version (if available), and set as updateable.
 */
function pm_release_recommended(&$project) {
  if (isset($project['recommended'])) {
    $project['candidate_version'] = $project['recommended'];
    $project['updateable'] = TRUE;
  }
}

/**
 * Get the a best release match for a requested update.
 *
 * @param $request A information array for the requested project
 * @param $project A project information array for this project, as returned by an update service from pm_get_project_info()
 */
function pm_get_release($request, $project) {
  $minor = '';
  $version_patch_changed = '';
  if ($request['version']) {
    // The user specified a specific version - try to find that exact version
    foreach($project['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // Straight match
      if (!isset($recommended_version) && $release['version'] == $request['version']) {
        $recommended_version = $version;
      }
    }
  }
  else {
    // No version specified - try to find the best version we can
    foreach($project['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // If we haven't found a recommended version yet, put the dev
      // version as recommended and hope it gets overwritten later.
      // Look for the 'latest version' if we haven't found it yet.
      // Latest version is defined as the most recent version for the
      // default major version.
      if (!isset($latest_version) && $release['version_major'] == $project['default_major']) {
        $latest_version = $version;
      }

      if (!isset($recommended_version) && $release['version_major'] == $project['default_major']) {
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
    return $project['releases'][$recommended_version];
  }
  else if (isset($latest_version)) {
    return $project['releases'][$latest_version];
  }
  else {
    return false;
  }
}
