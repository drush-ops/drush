<?php
// $Id$

/**
 * Command callback. Displays update status info and allows to update installed modules.
 * Pass specific projects as arguments, otherwise we update all that have candidate releases.
 *
 * This command prompts for confirmation before updating, so it is safe to run just to check on
 * In this case, say at the confirmation prompt.
 */
function pm_updatecode() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');
  
  // Get update status information.
  $releases = _pm_get_update_info();

  // Get specific requests
  $command = drush_get_command();
  $requests = $command['arguments'];

  // Parse out project name and version
  $requests = pm_parse_project_version($requests);

  // Preprocess releases
  if (!empty($requests)) {
    // Force update projects where a specific version is reqested
    foreach ($requests as $project => $request) {
      if (!empty($request['version'])) {
        // Match the requested release
        $release = pm_get_release($request, $releases[$project]);
        if ($release['version'] == $releases[$project]['existing_version']) {
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
  $rows[] = array(dt('Name'), dt('Installed version'), dt('Recommended version'), dt('Status'));

  // Process releases, notifying user of status and building a list of proposed updates
  $updateable = array();
  pm_update_filter($releases, $updateable, $rows);

  $last = pm_update_last_check();
  drush_print(dt('Update information last refreshed: ') . ($last  ? format_date($last) : dt('Never')));
  drush_print();
  drush_print(dt("Update status information on all installed and enabled Drupal modules:"));
  drush_print_table($rows, 2, TRUE);
  drush_print();

  // If specific project updates were requested then remove releases for all others
  if (!empty($requests)) {
    foreach ($updateable as $project => $release) {
      if (!isset($requests[$project])) {
        unset($updateable[$project]);
      }
    }
  }

  if (empty($updateable)) {
    return drush_log(dt('No updates available.'), 'ok');
  }

  // Offer to update to the identified releases
  pm_update_packages($updateable);
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
  if (isset($releases['drupal'])) {
    drush_print("NOTE: An update for the Drupal core is available. \nDrupal itself can't yet be updated by this tool. Please update Drupal manually.\n");
    unset($releases['drupal']);
  }

  drush_print(dt('Updates will be made to the following projects:'));
  foreach($releases as $release) {
    $print .= $release['title'] . " [" . $release['name'] . '-' . $release['candidate_version'] . "], ";
  }
  drush_print(substr($print, 0, strlen($print)-2));
  drush_print();
  drush_print(dt("Note: Updated modules can potentially break your site. It's not recommended to update production sites without prior testing."));
  if (module_exists('pm_svn')) {
    drush_print(dt("Note: A backup of your package will be stored to backups directory if no .svn directory is found."));
  }
  drush_print(dt('Note: If you have made any modifications to any file that belongs to one of these projects, you will have to migrate those modifications after updating.'));
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_die('Aborting.');
  }

  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));
  drush_include_engine('version_control', drush_get_option('version-control', 'svn'));

  // Save the date to be used in the backup directory's path name.
  $date = date('YmdHis');

  // Now we start the actual updating.
  foreach($releases as $release) {
    drush_verbose(dt('Starting to update !project ...', array('!project' => $release['title'])));

    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
    
    $source = $drupal_root .'/' . $release['path'];

    $skip_backup = version_control_skip_backup($source);
    if (!count($skip_backup)) {
      $backup_dir = $drupal_root  . '/backup';
      @drush_op('mkdir', $backup_dir, 0777);
      $backup_dir .= '/modules';
      @drush_op('mkdir', $backup_dir, 0777);
      $backup_dir .= "/$date";
      drush_op('mkdir', $backup_dir, 0777);
      $backup_target = $backup_dir . '/'. $release['name'];
      if (!drush_op('rename', $source, $backup_target)) {
        drush_die(dt('Failed to backup project directory !source to !backup_target', array('!source' => $source, '!backup_target' => $backup_target)));
      }
    }

    // Install the new version.
    // $basepath is the dir where the current module is installed. It's one dir up from the
    // place of the project's info files.
    $basepath = explode('/', $release['path']);
    // move a directory up, so we can copy updated dir to parent
    array_pop($basepath);
    $project_parent_path = $drupal_root. '/' . implode('/', $basepath). '/';
    if (!package_handler_update_project($release['name'], $release['releases'][$release['candidate_version']], $project_parent_path)) {
      if (!count($skip_backup)) {
        drush_set_error('DRUSH_PM_UPDATING_FAILED_BACKUP_RESTORE', dt('Updating project !project failed. Restoring previously installed version.', array('!project' => $release['name'])));
        drush_op('rename', $backup_target, $source);
      }
      else {
        drush_set_error('DRUSH_PM_UPDATING_FAILED_NO_BACKUP', dt('Updating project !project failed. Please revert to the previously installed version.', array('!project' => $release['name'])));
      }
    }
    else {
      drush_print(dt('Project !project was updated successfully. Installed version is now !version.', array('!project' => $release['name'], '!version' => $release['candidate_version'])));
      drush_command_invoke_all('pm_post_update', $release['name'], $release['releases'][$release['candidate_version']], $project_parent_path);
      version_control_post_update($release['name'], $release['releases'][$release['candidate_version']], $project_parent_path);
    }
  }
  if ($backup_dir) {
    drush_log(dt("Backups were saved into the directory !backup_dir.", array('!backup_dir' => $backup_dir)), 'ok');
  }
  drush_print(dt("You should now run `drush updatedb`."));
}
