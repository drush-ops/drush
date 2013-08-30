<?php

/**
 * Implementation of drush_COMMAND_validate().
 */
function drush_pm_download_validate() {
  // Accomodate --select to the values accepted by release_info_fetch().
  $select = drush_get_option('select', 'auto');
  if ($select === TRUE) {
    drush_set_option('select', 'always');
  }
  else if ($select === FALSE) {
    drush_set_option('select', 'never');
  }

  // Validate the user specified destination directory.
  $destination = drush_get_option('destination');
  if (!empty($destination)) {
    $destination = rtrim($destination, DIRECTORY_SEPARATOR);
    if (!is_dir($destination)) {
      drush_print(dt("The directory !destination does not exist.", array('!destination' => $destination)));
      if (!drush_get_context('DRUSH_SIMULATE')) {
        if (drush_confirm(dt('Would you like to create it?'))) {
          drush_mkdir($destination, TRUE);
        }
        if (!is_dir($destination)) {
          return drush_set_error('DRUSH_PM_NO_DESTINATION', dt('Unable to create destination directory !destination.', array('!destination' => $destination)));
        }
      }
    }
    if (!is_writable($destination)) {
      return drush_set_error('DRUSH_PM_NO_DESTINATION', dt('Destination directory !destination is not writable.', array('!destination' => $destination)));
    }
    // Ignore --use-site-dir, if given.
    if (drush_get_option('use-site-dir', FALSE)) {
      drush_set_option('use-site-dir', FALSE);
    }
  }

  // Validate --variant or enforce a sane default.
  $variant = drush_get_option('variant', FALSE);
  if ($variant) {
    $variants = array('full', 'projects', 'profile-only');
    if (!in_array($variant, $variants)) {
      return drush_set_error('DRUSH_PM_PROFILE_INVALID_VARIANT', dt('Invalid variant !variant. Valid values: !variants.', array('!variant' => $variant, '!variants' => implode(', ', $variants))));
    }
  }
  // 'full' and 'projects' variants are only valid for wget package handler.
  $package_handler = drush_get_option('package-handler', 'wget');
  if (($package_handler != 'wget') && ($variant != 'profile-only')) {
    $new_variant = 'profile-only';
    if ($variant) {
      drush_log(dt('Variant !variant is incompatible with !ph package-handler.', array('!variant' => $variant, '!ph' => $package_handler)), 'warning');
    }
  }
  // If we are working on a drupal root, full variant is not an option.
  else if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
    if ((!$variant) || (($variant == 'full') && (!isset($new_variant)))) {
      $new_variant = 'projects';
    }
    if ($variant == 'full') {
      drush_log(dt('Variant full is not a valid option within a Drupal root.'), 'warning');
    }
  }

  if (isset($new_variant)) {
    drush_set_option('variant', $new_variant);
    if ($variant) {
      drush_log(dt('Switching to --variant=!variant.', array('!variant' => $new_variant)), 'ok');
    }
  }
}

/**
 * Command callback. Download Drupal core or any project.
 */
function drush_pm_download() {
  if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
    $requests = array('drupal');
  }

  // Parse out project name and version.
  $requests = pm_parse_project_version($requests);

  // Get release history for each request and download the project.
  $source = drush_get_option('source', RELEASE_INFO_DEFAULT_URL);
  $restrict_to = drush_get_option('dev', FALSE) ? 'dev' : '';
  $select = drush_get_option('select', 'auto');
  $all = drush_get_option('all', FALSE);
  foreach ($requests as $name => $request) {
    $request['status url'] = $source;
    $release = release_info_fetch($request, $restrict_to, $select, $all);
    if ($release == FALSE) {
      // Stop working on the first failure. Return silently on user abort.
      if (drush_get_context('DRUSH_USER_ABORT', FALSE)) {
        return FALSE;
      }
      // Signal that the command failed for all other problems.
      return drush_set_error('DRUSH_DOWNLOAD_FAILED', dt("Could not download requested project(s)."));
    }

    // Determine the name of the directory that will contain the project.
    // We face here all the asymetries to make it smooth for package handlers.
    // For Drupal core: --drupal-project-rename or drupal-x.y
    if (($request['project_type'] == 'core') ||
        (($request['project_type'] == 'profile') && (drush_get_option('variant', 'full') == 'full'))) {
      // Avoid downloading core into existing core.
      if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
        if (strpos(realpath(drush_get_option('destination')), DRUPAL_ROOT) !== FALSE) {
          return drush_set_error('DRUSH_PM_DOWNLOAD_TRANSLATIONS_FORBIDDEN', dt('It\'s forbidden to download !project core into an existing core.', array('!project' => $request['name'])));
        }
      }

      if ($rename = drush_get_option('drupal-project-rename', FALSE)) {
        if ($rename === TRUE) {
          $request['project_dir'] = $request['name'];
        }
        else {
          $request['project_dir'] = $rename;
        }
      }
      else {
        // Set to drupal-x.y, the expected name for .tar.gz contents.
        // Explicitly needed for cvs package handler.
        $request['project_dir'] = strtolower(strtr($release['name'], ' ', '-'));
      }
    }
    // For the other project types we want the project name. Including core
    // variant for profiles.  Note those come with drupal-x.y in the .tar.gz.
    else {
      $request['project_dir'] = $request['name'];
    }

    // Download the project to a temporary location.
    $request['base_project_path'] = drush_tempdir();
    $request['full_project_path'] = $request['base_project_path'] .'/'. $request['project_dir'];
    drush_log(dt('Downloading project !name to !dir ...', array('!name' => $request['name'], '!dir' => $request['base_project_path'])));
    if (!package_handler_download_project($request, $release)) {
      drush_log(dt('Error downloading !name', array('!name' => $request['name']), 'error'));
      continue;
    }

    // Determine the install location for the project.  User provided
    // --destination has preference.
    $destination = drush_get_option('destination');
    if (!empty($destination)) {
      if (!file_exists($destination)) {
        drush_mkdir($destination);
      }
      $request['project_install_location'] = realpath($destination);
    }
    else {
      $request['project_install_location'] = _pm_download_destination($request['project_type']);
    }

    // If user did not provide --destination, then call the
    // download-destination-alter hook to give the chance to any commandfiles
    // to adjust the install location or abort it.
    if (empty($destination)) {
      $result = drush_command_invoke_all_ref('drush_pm_download_destination_alter', $request, $release);
      if (array_search(FALSE, $result, TRUE) !== FALSE) {
        return FALSE;
      }
    }

    // Load version control engine and detect if (the parent directory of) the
    // project install location is under a vcs.
    if (!$version_control = drush_pm_include_version_control($request['project_install_location'])) {
      continue;
    }

    $request['project_install_location'] .= '/' . $request['project_dir'];

    if ($version_control->engine == 'backup') {
      // Check if install location already exists.
      if (is_dir($request['project_install_location'])) {
        if (drush_confirm(dt('Install location !location already exists. Do you want to overwrite it?', array('!location' => $request['project_install_location'])))) {
          drush_delete_dir($request['project_install_location'], TRUE);
        }
        else {
          drush_log(dt("Skip installation of !project to !dest.", array('!project' => $request['name'], '!dest' => $request['project_install_location'])), 'warning');
          continue;
        }
      }
    }
    else {
      // Find and unlink all files but the ones in the vcs control directories.
      $skip_list = array('.', '..');
      $skip_list = array_merge($skip_list, drush_version_control_reserved_files());
      drush_scan_directory($request['project_install_location'], '/.*/', $skip_list, 'unlink', TRUE, 'filename', 0, TRUE);
    }

    // Copy the project to the install location.
    if (drush_op('_drush_recursive_copy', $request['full_project_path'], $request['project_install_location'])) {
      drush_log(dt("Project !project (!version) downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $request['project_install_location'])), 'success');
      $request['base_project_path'] = basename($request['project_install_location']);
      $request['full_project_path'] = $request['project_install_location'];

      // If the version control engine is a proper vcs we also need to remove
      // orphan directories.
      if ($version_control->engine != 'backup') {
        $empty_dirs = drush_find_empty_directories($request['full_project_path'], $version_control->reserved_files());
        foreach ($empty_dirs as $empty_dir) {
          // Some VCS files are read-only on Windows (e.g., .svn/entries).
          drush_delete_dir($empty_dir, TRUE);
        }
      }

      // Post download actions.
      package_handler_post_download($request, $release);
      drush_command_invoke_all('drush_pm_post_download', $request, $release);
      $version_control->post_download($request);

      // Print release notes if --notes option is set.
      if (drush_get_option('notes') && !drush_get_context('DRUSH_PIPE')) {
        release_info_print_releasenotes(array($name . '-' . $release['version']), FALSE);
      }

      // Inform the user about available modules a/o themes in the downloaded project.
      drush_pm_extensions_in_project($request);
    }
    else {
      // We don't `return` here in order to proceed with downloading additional projects.
      drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt("Project !project (!version) could not be downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $request['project_install_location'])));
    }

    // Notify about this project.
    if (drush_notify_allowed('pm-download')) {
      $msg = dt('Project !project (!version) downloaded to !install.', array(
        '!project' => $name,
        '!version' => $release['version'],
        '!install' => $request['project_install_location'],
      ));
      drush_notify_send(drush_notify_command_message('pm-download', $msg));
    }
  }
}

/**
 * Implementation of hook_drush_pm_download_destination_alter().
 *
 * Built-in download-destination-alter hook. This particular version of
 * the hook will move modules that contain only drush commands to
 * /usr/share/drush/commands if it exists, or $HOME/.drush if the
 * site-wide location does not exist.
 */
function pm_drush_pm_download_destination_alter(&$project, $release) {
  // A module is a pure drush command if it has no .module and contain
  // .drush.inc files.  Skip this test for drush itself, though; we do
  // not want to download drush to the ~/.drush folder.
  if (($project['project_type'] == 'module') && ($project['name'] != 'drush')) {
    $drush_command_files = drush_scan_directory($project['full_project_path'], '/.*\.drush.inc/');
    if (!empty($drush_command_files)) {
      $module_files = drush_scan_directory($project['full_project_path'], '/.*\.module/');
      if (empty($module_files)) {
        $install_dir = drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES');
        if (!is_dir($install_dir) || !is_writable($install_dir)) {
          $install_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
        }
        // Make the .drush dir if it does not already exist.
        if (!is_dir($install_dir)) {
          drush_mkdir($install_dir, FALSE);
        }
        // Change the location if the mkdir worked.
        if (is_dir($install_dir)) {
          $project['project_install_location'] = $install_dir;
        }
      }
      // We need to clear the drush commandfile cache so that
      // our newly-downloaded drush extension commandfiles can be found.
      drush_cache_clear_all();
    }
  }
}

/**
 * Determines a candidate destination directory for a particular site path.
 *
 * Optionally attempts to create the directory.
 *
 * @return String the candidate destination if it exists.
 */
function _pm_download_destination_lookup($type, $drupal_root, $sitepath, $create = FALSE) {
  if ($type == 'theme engine') {
    $destination = 'themes/engines';
  }
  // Profiles in Drupal < 8
  elseif (($type == 'profile') && (drush_drupal_major_version() < 8)) {
    $destination = 'profiles';
  }
  // Type: module, theme or profile.
  else {
    $destination = $type . 's';
    // Prefer /contrib if it exists.
    if ($sitepath) {
      $destination = $sitepath . '/' . $destination;
    }
    $contrib = $destination . '/contrib';
    if (is_dir($contrib)) {
      $destination = $contrib;
    }
  }
  if ($create) {
    drush_log(dt('Attempting to create destination directory at !dir', array('!dir' => $destination)));
    drush_mkdir($destination, TRUE);
  }
  if (is_dir($destination)) {
    drush_log(dt('Using destination directory !dir', array('!dir' => $destination)));
    return $destination;
  }
  drush_log(dt('Could not find destination directory at !dir', array('!dir' => $destination)));
  return FALSE;
}

/**
 * Returns the best destination for a particular download type we can find.
 *
 * It is based on the project type and drupal and site contexts.
 */
function _pm_download_destination($type) {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  $site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT', FALSE);
  $full_site_root = $drupal_root .'/'. $site_root;
  $sitewide = drush_drupal_sitewide_directory();

  $in_site_directory = FALSE;
  // Check if we are running within the site directory.
  if (strpos(realpath(drush_cwd()), realpath($full_site_root)) !== FALSE || (drush_get_option('use-site-dir', FALSE))) {
    $in_site_directory = TRUE;
  }

  $destination = '';
  if ($type != 'core') {
    // Attempt 1: If we are in a specific site directory, and the destination
    // directory already exists, then we use that.
    if (empty($destination) && $site_root && $in_site_directory) {
      $create_dir = drush_get_option('use-site-dir', FALSE);
      $destination = _pm_download_destination_lookup($type, $drupal_root, $full_site_root, $create_dir);
    }
    // Attempt 2: If the destination directory already exists for
    // the sitewide directory, use that.
    if (empty($destination) && $drupal_root) {
      $destination = _pm_download_destination_lookup($type, $drupal_root, $sitewide);
    }
    // Attempt 3: If a specific (non default) site directory exists and
    // the sitewide directory does not exist, then create destination
    // in the site specific directory.
    if (empty($destination) && $site_root && $site_root !== 'sites/default' && is_dir($full_site_root) && !is_dir($sitewide)) {
      $destination = _pm_download_destination_lookup($type, $drupal_root, $full_site_root, TRUE);
    }
    // Attempt 4: If sitewide directory exists, then create destination there.
    if (empty($destination) && is_dir($sitewide)) {
      $destination = _pm_download_destination_lookup($type, $drupal_root, $sitewide, TRUE);
    }
    // Attempt 5: If site directory exists (even default), then create
    // destination in that directory.
    if (empty($destination) && $site_root && is_dir($full_site_root)) {
      $destination = _pm_download_destination_lookup($type, $drupal_root, $full_site_root, TRUE);
    }
  }
  // Attempt 6: If we didn't find a valid directory yet (or we somehow found
  // one that doesn't exist) we always fall back to the current directory.
  if (empty($destination) || !is_dir($destination)) {
    $destination = drush_cwd();
  }

  return $destination;
}
