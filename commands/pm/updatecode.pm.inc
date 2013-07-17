<?php

/**
 * Command callback. Displays update status info and allows to update installed
 * projects.
 * Pass specific projects as arguments, otherwise we update all that have
 * candidate releases.
 *
 * This command prompts for confirmation before updating, so it is safe to run
 * just to check on. In this case, say at the confirmation prompt.
 */
function drush_pm_updatecode() {
  // In --pipe mode, just run pm-updatestatus and exit.
  if (drush_get_context('DRUSH_PIPE')) {
    drush_set_option('strict', 0);
    return drush_invoke('pm-updatestatus');
  }
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  // Get specific requests.
  $requests = pm_parse_arguments(func_get_args(), FALSE);

  // Print report of modules to update, and record
  // result of that function in $update_info.
  $updatestatus_options = array();
  foreach (array('lock', 'unlock', 'lock-message', 'security-only') as $option) {
    $value = drush_get_option($option, FALSE);
    if ($value) {
      $updatestatus_options[$option] = $value;
    }
  }
  $backend_options = array(
    'integrate' => FALSE,
  );
  $values = drush_invoke_process("@self", 'pm-updatestatus', func_get_args(), $updatestatus_options, $backend_options);
  if (!is_array($values) || $values['error_status']) {
    return FALSE;
  }
  $updateable = $values['object'];
  drush_print($values['output']);
  $tmpfile = drush_tempnam('pm-updatecode.');

  $last = pm_update_last_check();
  drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));

  // Prevent update of core if --no-core was specified.
  if (isset($updateable['drupal']) && drush_get_option('no-core', FALSE)) {
    unset($updateable['drupal']);
    drush_print(dt('Skipping core update (--no-core specified).'));
  }

  // If there are any locked projects that were not requested, then remove them.
  foreach ($updateable as $name => $project) {
    if ((isset($project['locked'])) && (!isset($requests[$name]))) {
      unset($updateable[$name]);
    }
  }

  // Do no updates in simulated mode.
  if (drush_get_context('DRUSH_SIMULATE')) {
    return drush_log(dt('No action taken in simulated mode.'), 'ok');
    return TRUE;
  }

  $core_update_available = FALSE;
  if (isset($updateable['drupal'])) {
    $drupal_project = $updateable['drupal'];
    unset($updateable['drupal']);

    // At present we need to update drupal core after non-core projects
    // are updated.
    if (empty($updateable)) {
      return _pm_update_core($drupal_project, $tmpfile);
    }
    // If there are modules other than drupal core enabled, then update them
    // first.
    else {
      $core_update_available = TRUE;
      if ($drupal_project['status'] == UPDATE_NOT_SECURE) {
        drush_print(dt("NOTE: A security update for the Drupal core is available."));
      }
      else {
        drush_print(dt("NOTE: A code update for the Drupal core is available."));
      }
      drush_print(dt("Drupal core will be updated after all of the non-core projects are updated.\n"));
    }
  }

  // If there are no releases to update, then print a final
  // exit message.
  if (empty($updateable)) {
    if (drush_get_option('security-only')) {
      return drush_log(dt('No security updates available.'), 'ok');
    }
    else {
      return drush_log(dt('No code updates available.'), 'ok');
    }
  }

  // Offer to update to the identified releases.
  if (!pm_update_packages($updateable, $tmpfile)) {
    return FALSE;
  }

  // After projects are updated we can update core.
  if ($core_update_available) {
    drush_print();
    return _pm_update_core($drupal_project, $tmpfile);
  }
}

/**
 * Update drupal core, following interactive confirmation from the user.
 *
 * @param $project
 *   The drupal project information from the drupal.org update service,
 *   copied from $update_info['drupal'].  @see drush_pm_updatecode.
 */
function _pm_update_core(&$project, $tmpfile) {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  drush_print(dt('Code updates will be made to drupal core.'));
  drush_print(dt("WARNING:  Updating core will discard any modifications made to Drupal core files, most noteworthy among these are .htaccess and robots.txt.  If you have made any modifications to these files, please back them up before updating so that you can re-create your modifications in the updated version of the file."));
  drush_print(dt("Note: Updating core can potentially break your site. It is NOT recommended to update production sites without prior testing."));
  drush_print();
  if (drush_get_option('notes', FALSE)) {
    drush_print('Obtaining release notes for above projects...');
    $requests = pm_parse_project_version(array('drupal'));
    release_info_print_releasenotes($requests, TRUE, $tmpfile);
  }
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_print(dt('Rolling back all changes. Run again with --no-core to update modules only.'));
    return drush_user_abort();
  }

  // We need write permission on $drupal_root.
  if (!is_writable($drupal_root)) {
    return drush_set_error('DRUSH_PATH_NO_WRITABLE', dt('Drupal root path is not writable.'));
  }

  // Create a directory 'core' if it does not already exist.
  $project['path'] = 'drupal-' . $project['candidate_version'];
  $project['full_project_path'] = $drupal_root . '/' . $project['path'];
  if (!is_dir($project['full_project_path'])) {
    drush_mkdir($project['full_project_path']);
  }

  // Create a list of directories to exclude from the update process.
  $skip_list = array('sites', $project['path']);
  // Add non-writable directories: we can't move them around.
  // We will also use $items_to_test later for $version_control check.
  $items_to_test = drush_scan_directory($drupal_root, '/.*/', array_merge(array('.', '..'), $skip_list), 0, FALSE, 'basename', 0, TRUE);
  foreach (array_keys($items_to_test) as $item) {
    if (is_dir($item) && !is_writable($item)) {
      $skip_list[] = $item;
      unset($items_to_test[$item]);
    }
    elseif (is_link($item)) {
      $skip_list[] = $item;
      unset($items_to_test[$item]);
    }
  }
  $project['skip_list'] = $skip_list;

  // Move all files and folders in $drupal_root to the new 'core' directory
  // except for the items in the skip list
  _pm_update_move_files($drupal_root, $project['full_project_path'], $project['skip_list']);

  // Set a context variable to indicate that rollback should reverse
  // the _pm_update_move_files above.
  drush_set_context('DRUSH_PM_DRUPAL_CORE', $project);

  if (!$version_control = drush_pm_include_version_control($project['full_project_path'])) {
    return FALSE;
  }

  $project['base_project_path'] = dirname($project['full_project_path']);
  // Check we have a version control system, and it clears its pre-flight.
  if (!$version_control->pre_update($project, $items_to_test)) {
    return FALSE;
  }

  // Package handlers want the project directory in project_dir.
  $project['project_dir'] = $project['path'];

  // Update core.
  if (pm_update_project($project, $version_control) === FALSE) {
    return FALSE;
  }

  // Take the updated files in the 'core' directory that have been updated,
  // and move all except for the items in the skip list back to
  // the drupal root
  _pm_update_move_files($project['full_project_path'], $drupal_root, $project['skip_list']);
  drush_delete_dir($project['full_project_path']);

  // Version control engines expect full_project_path to exist and be accurate.
  $project['full_project_path'] = $project['base_project_path'];

  // If there is a backup target, then find items
  // in the backup target that do not exist at the
  // drupal root.  These are to be moved back.
  if (array_key_exists('backup_target', $project)) {
    _pm_update_move_files($project['backup_target'], $drupal_root, $project['skip_list'], FALSE);
    _pm_update_move_files($project['backup_target'] . '/profiles', $drupal_root . '/profiles', array('default'), FALSE);
  }

  pm_update_complete($project, $version_control);

  return TRUE;
}

/**
 * Move some files from one location to another
 */
function _pm_update_move_files($src_dir, $dest_dir, $skip_list, $remove_conflicts = TRUE) {
  $items_to_move = drush_scan_directory($src_dir, '/.*/', array_merge(array('.', '..'), $skip_list), 0, FALSE, 'filename', 0, TRUE);
  foreach ($items_to_move as $filename => $info) {
    if ($remove_conflicts) {
      drush_delete_dir($dest_dir . '/' . basename($filename));
    }
    if (!file_exists($dest_dir . '/' . basename($filename))) {
      $move_result = drush_move_dir($filename,  $dest_dir . '/' . basename($filename));
    }
  }
  return TRUE;
}

/**
 * Update projects according to an array of releases and print the release notes
 * for each project, following interactive confirmation from the user.
 *
 * @param $update_info
 *   An array of projects from the drupal.org update service, with an additional
 *   array key candidate_version that specifies the version to be installed.
 */
function pm_update_packages($update_info, $tmpfile) {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  $print = '';
  $status = array();
  foreach($update_info as $project) {
    $print .= $project['title'] . " [" . $project['name'] . '-' . $project['candidate_version'] . "], ";
    $status[$project['status']] = $project['status'];
  }
  // We print the list of the projects that need to be updated.
  if (isset($status[UPDATE_NOT_SECURE])) {
    if (isset($status[UPDATE_NOT_CURRENT])) {
      $title = (dt('Security and code updates will be made to the following projects:'));
    }
    else {
      $title = (dt('Security updates will be made to the following projects:'));
    }
  }
  else {
    $title = (dt('Code updates will be made to the following projects:'));
  }
  $print = "$title " . (substr($print, 0, strlen($print)-2));
  drush_print($print);
  file_put_contents($tmpfile, "\n\n$print\n\n", FILE_APPEND);

  // Print the release notes for projects to be updated.
  if (drush_get_option('notes', FALSE)) {
    drush_print('Obtaining release notes for above projects...');
    $requests = pm_parse_project_version(array_keys($update_info));
    release_info_print_releasenotes($requests, TRUE, $tmpfile);
  }

  // We print some warnings before the user confirms the update.
  drush_print();
  if (drush_get_option('no-backup', FALSE)) {
    drush_print(dt("Note: You have selected to not store backups."));
  }
  else {
    drush_print(dt("Note: A backup of your project will be stored to backups directory if it is not managed by a supported version control system."));
    drush_print(dt('Note: If you have made any modifications to any file that belongs to one of these projects, you will have to migrate those modifications after updating.'));
  }
  if(!drush_confirm(dt('Do you really want to continue with the update process?'))) {
    return drush_user_abort();
  }

  // Now we start the actual updating.
  foreach($update_info as $project) {
    if (empty($project['path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_NO_PROJECT_PATH', dt('The !project project path is not available, perhaps the !type is enabled but has been deleted from disk.', array('!project' => $project['name'], '!type' => $project['project_type'])));
    }
    drush_log(dt('Starting to update !project code at !dir...', array('!project' => $project['title'], '!dir' => $project['path'])));
    // Define and check the full path to project directory and base (parent) directory.
    $project['full_project_path'] = $drupal_root . '/' . $project['path'];
    if (stripos($project['path'], $project['project_type']) === FALSE || !is_dir($project['full_project_path'])) {
      return drush_set_error('DRUSH_PM_UPDATING_PATH_NOT_FOUND', dt('The !project directory could not be found within the !types directory at !full_project_path, perhaps the project is enabled but has been deleted from disk.', array('!project' => $project['name'], '!type' => $project['project_type'], '!full_project_path' => $project['full_project_path'])));
    }
    if (!$version_control = drush_pm_include_version_control($project['full_project_path'])) {
      return FALSE;
    }
    $project['base_project_path'] = dirname($project['full_project_path']);
    // Check we have a version control system, and it clears its pre-flight.
    if (!$version_control->pre_update($project)) {
      return FALSE;
    }

    // Package handlers want the name of the directory in project_dir.
    // It may be different to the project name for pm-download.
    // Perhaps we want here filename($project['full_project_path']).
    $project['project_dir'] = $project['name'];

    // Run update on one project.
    if (pm_update_project($project, $version_control) === FALSE) {
      return FALSE;
    }
    pm_update_complete($project, $version_control);
  }

  return TRUE;
}

/**
 * Update one project -- a module, theme or Drupal core.
 *
 * @param $project
 *   The project to upgrade.  $project['full_project_path'] must be set
 *   to the location where this project is stored.
 */
function pm_update_project($project, $version_control) {
  // 1. If the version control engine is a proper vcs we need to remove project
  // files in order to not have orphan files after update.
  // 2. If the package-handler is cvs or git, it will remove upstream removed
  // files and no orphans will exist after update.
  // So, we must remove all files previous update if the directory is not a
  // working copy of cvs or git but we don't need to remove them if the version
  // control engine is backup, as it did already move the project out to the
  // backup directory.
  if (($version_control->engine != 'backup') && (drush_get_option('package-handler', 'wget') == 'wget')) {
    // Find and unlink all files but the ones in the vcs control directories.
    $skip_list = array('.', '..');
    $skip_list = array_merge($skip_list, drush_version_control_reserved_files());
    drush_scan_directory($project['full_project_path'], '/.*/', $skip_list, 'unlink', TRUE, 'filename', 0, TRUE);
  }

  // Add the project to a context so we can roll back if needed.
  $updated = drush_get_context('DRUSH_PM_UPDATED');
  $updated[] = $project;
  drush_set_context('DRUSH_PM_UPDATED', $updated);

  if (!package_handler_update_project($project, $project['releases'][$project['candidate_version']])) {
    return drush_set_error('DRUSH_PM_UPDATING_FAILED', dt('Updating project !project failed. Attempting to roll back to previously installed version.', array('!project' => $project['name'])));
  }

  // If the version control engine is a proper vcs we also need to remove
  // orphan directories.
  if (($version_control->engine != 'backup') && (drush_get_option('package-handler', 'wget') == 'wget')) {
    $files = drush_find_empty_directories($project['full_project_path'], $version_control->reserved_files());
    array_map('drush_delete_dir', $files);
  }

  return TRUE;
}

/**
 * Run the post-update hooks after updatecode is complete for one project.
 */
function pm_update_complete($project, $version_control) {
  drush_print(dt('Project !project was updated successfully. Installed version is now !version.', array('!project' => $project['name'], '!version' => $project['candidate_version'])));
  drush_command_invoke_all('pm_post_update', $project['name'], $project['releases'][$project['candidate_version']]);
  $version_control->post_update($project);
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

  // Post rollback, we will do additional repair if the project is drupal core.
  $drupal_core = drush_get_context('DRUSH_PM_DRUPAL_CORE', FALSE);
  if ($drupal_core) {
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
    _pm_update_move_files($drupal_core['full_project_path'], $drupal_root, $drupal_core['skip_list']);
    drush_delete_dir($drupal_core['full_project_path']);
  }
}

