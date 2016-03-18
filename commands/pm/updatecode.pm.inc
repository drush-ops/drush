<?php

/**
 * @file
 * pm-updatecode command implementation.
 */

use Drush\Log\LogLevel;

/**
 * Command callback. Displays update status info and allows to update installed projects.
 *
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

  $update_status = drush_get_engine('update_status');

  // Get specific requests.
  $requests = pm_parse_arguments(func_get_args(), FALSE);

  // Print report of modules to update, and record
  // result of that function in $update_info.
  $updatestatus_options = array();
  foreach (array('lock', 'unlock', 'lock-message', 'update-backend', 'check-disabled', 'security-only') as $option) {
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
    return drush_set_error('pm-updatestatus failed.');
  }
  $last = $update_status->lastCheck();
  drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));
  drush_print($values['output']);

  $update_info = $values['object'];

  // Prevent update of core if --no-core was specified.
  if (isset($update_info['drupal']) && drush_get_option('no-core', FALSE)) {
    unset($update_info['drupal']);
    drush_print(dt('Skipping core update (--no-core specified).'));
  }

  // Remove locked and non-updateable projects.
  foreach ($update_info as $name => $project) {
    if ((isset($project['locked']) && !isset($requests[$name])) || (!isset($project['updateable']) || !$project['updateable'])) {
      unset($update_info[$name]);
    }
  }

  // Do no updates in simulated mode.
  if (drush_get_context('DRUSH_SIMULATE')) {
    return drush_log(dt('No action taken in simulated mode.'), LogLevel::OK);
    return TRUE;
  }

  $tmpfile = drush_tempnam('pm-updatecode.');

  $core_update_available = FALSE;
  if (isset($update_info['drupal'])) {
    $drupal_project = $update_info['drupal'];
    unset($update_info['drupal']);

    // At present we need to update drupal core after non-core projects
    // are updated.
    if (empty($update_info)) {
      return _pm_update_core($drupal_project, $tmpfile);
    }
    // If there are modules other than drupal core enabled, then update them
    // first.
    else {
      $core_update_available = TRUE;
      if ($drupal_project['status'] == DRUSH_UPDATESTATUS_NOT_SECURE) {
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
  if (empty($update_info)) {
    if (drush_get_option('security-only')) {
      return drush_log(dt('No security updates available.'), LogLevel::OK);
    }
    else {
      return drush_log(dt('No code updates available.'), LogLevel::OK);
    }
  }

  // Offer to update to the identified releases.
  if (!pm_update_packages($update_info, $tmpfile)) {
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
 *
 * @return bool
 *   Success or failure. An error message will be logged.
 */
function _pm_update_core(&$project, $tmpfile) {
  $release_info = drush_get_engine('release_info');

  drush_print(dt('Code updates will be made to drupal core.'));
  drush_print(dt("WARNING:  Updating core will discard any modifications made to Drupal core files, most noteworthy among these are .htaccess and robots.txt.  If you have made any modifications to these files, please back them up before updating so that you can re-create your modifications in the updated version of the file."));
  drush_print(dt("Note: Updating core can potentially break your site. It is NOT recommended to update production sites without prior testing."));
  drush_print();
  if (drush_get_option('notes', FALSE)) {
    drush_print('Obtaining release notes for above projects...');
    #TODO# Build the $request array from info in $project.
    $request = pm_parse_request('drupal');
    $release_info->get($request)->getReleaseNotes(NULL, TRUE, $tmpfile);
  }
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_print(dt('Rolling back all changes. Run again with --no-core to update modules only.'));
    return drush_user_abort();
  }

  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  $project['full_project_path'] = $drupal_root;

  // We need write permission on $drupal_root.
  if (!is_writable($drupal_root)) {
    return drush_set_error('DRUSH_PATH_NO_WRITABLE', dt('Drupal root path is not writable.'));
  }

  // The upgrade process for core requires to remove files from the old core first, but not removing any site-local files.
  // Drupal documentation specifies a set of directories that comprise the core, but isn't specific about the exact list of files in the root.
  // The code below takes a safe approach.
  // - Check and back-up all files in the root.
  // - Don't delete any files in the root.  Instead allow the new files to overwrite them.  This does mean that if a file is removed from
  //   core, it will stay around locally.  However this is likely to be rare, and seems hard to avoid.

  if (drush_drupal_major_version() >= 8) {
    // See https://api.drupal.org/api/drupal/core!UPGRADE.txt/8
    $items_to_delete = array('core', 'vendor');
  }
  else {
    // See https://api.drupal.org/api/drupal/UPGRADE.txt/7
    $items_to_delete = array('includes', 'misc', 'modules', 'scripts', 'themes', 'profiles/minimal', 'profiles/standard', 'profiles/testing');
  }

  $items_to_test = array_flip($items_to_delete);
  foreach (scandir($drupal_root) as $file) {
    if (is_file("$drupal_root/$file")) {
      $items_to_test[$file] = 1;
    }
  }

  if (!$version_control = drush_pm_include_version_control($project['full_project_path'])) {
    return FALSE;
  }

  // Check we have a version control system, and it clears its pre-flight.
  if (!$version_control->pre_update($project, $items_to_test)) {
    return FALSE;
  }

  foreach ($items_to_delete as $dir) {
    if (!drush_delete_dir($dir)) {
      return FALSE;
    }
  }

  // Update core.
  if (pm_update_project($project, $version_control) === FALSE) {
    return FALSE;
  }

  pm_update_finish($project, $version_control);

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
  $release_info = drush_get_engine('release_info');

  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  $print = '';
  $status = array();
  foreach($update_info as $project) {
    $print .= $project['title'] . " [" . $project['name'] . '-' . $project['candidate_version'] . "], ";
    $status[$project['status']] = $project['status'];
  }
  // We print the list of the projects that need to be updated.
  if (isset($status[DRUSH_UPDATESTATUS_NOT_SECURE])) {
    if (isset($status[DRUSH_UPDATESTATUS_NOT_CURRENT])) {
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
    #TODO# Build the $request array from info in $project.
    foreach (array_keys($update_info) as $project_name) {
      $request = pm_parse_request($project_name);
      $release_info->get($request)->getReleaseNotes(NULL, TRUE, $tmpfile);
    }
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

    // Check we have a version control system, and it clears its pre-flight.
    if (!$version_control->pre_update($project)) {
      return FALSE;
    }

    // Run update on one project.
    if (pm_update_project($project, $version_control) === FALSE) {
      return FALSE;
    }
    pm_update_finish($project, $version_control);
  }

  return TRUE;
}

/**
 * Update one project -- a module, theme or Drupal core.
 *
 * @param $project
 *   The project to upgrade.  $project['full_project_path'] must be set
 *   to the location where this project is stored.
 * @return bool
 *   Success or failure. An error message will be logged.
 */
function pm_update_project($project, $version_control) {
  // Add the project to a context so we can roll back if needed.
  $updated = drush_get_context('DRUSH_PM_UPDATED');
  $updated[] = $project;
  drush_set_context('DRUSH_PM_UPDATED', $updated);

  if (!package_handler_update_project($project, $project['releases'][$project['candidate_version']])) {
    return drush_set_error('DRUSH_PM_UPDATING_FAILED', dt('Updating project !project failed. Attempting to roll back to previously installed version.', array('!project' => $project['name'])));
  }

  return TRUE;
}

/**
 * Run the post-update hooks after updatecode is finished for one project.
 */
function pm_update_finish($project, $version_control) {
  drush_print(dt('Project !project was updated successfully. Installed version is now !version.', array('!project' => $project['name'], '!version' => $project['candidate_version'])));
  drush_command_invoke_all('pm_post_update', $project['name'], $project['releases'][$project['candidate_version']], $project);
  $version_control->post_update($project);
}

/**
 * Rollback the update process.
 */
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

