<?php

/**
 * @file
 * pm-download command implementation.
 */

use Drush\Log\LogLevel;
use Drush\UpdateService\ReleaseInfo;

/**
 * Implements drush_hook_COMMAND_validate().
 */
function drush_pm_download_validate() {
  // Accomodate --select to the values accepted by release_info.
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
      drush_log(dt('Variant !variant is incompatible with !ph package-handler.', array('!variant' => $variant, '!ph' => $package_handler)), LogLevel::WARNING);
    }
  }
  // If we are working on a drupal root, full variant is not an option.
  else if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
    if ((!$variant) || (($variant == 'full') && (!isset($new_variant)))) {
      $new_variant = 'projects';
    }
    if ($variant == 'full') {
      drush_log(dt('Variant full is not a valid option within a Drupal root.'), LogLevel::WARNING);
    }
  }

  if (isset($new_variant)) {
    drush_set_option('variant', $new_variant);
    if ($variant) {
      drush_log(dt('Switching to --variant=!variant.', array('!variant' => $new_variant)), LogLevel::OK);
    }
  }
}

/**
 * Command callback. Download Drupal core or any project.
 */
function drush_pm_download() {
  $release_info = drush_get_engine('release_info');

  if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
    $requests = array('drupal');
  }

  // Pick cli options.
  $status_url = drush_get_option('source', ReleaseInfo::DEFAULT_URL);
  $restrict_to = drush_get_option('dev', '');
  $select = drush_get_option('select', 'auto');
  $all = drush_get_option('all', FALSE);
  // If we've bootstrapped a Drupal site and the user may have the chance
  // to select from a list of filtered releases, we want to pass
  // the installed project version, if any.
  $projects = array();
  if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
    if (!$all and in_array($select, array('auto', 'always'))) {
      $projects = drush_get_projects();
    }
  }

  // Get release history for each request and download the project.
  foreach ($requests as $request) {
    $request = pm_parse_request($request, $status_url, $projects);
    $version = isset($projects[$request['name']]) ? $projects[$request['name']]['version'] : NULL;
    $release = $release_info->selectReleaseBasedOnStrategy($request, $restrict_to, $select, $all, $version);
    if ($release == FALSE) {
      // Stop working on the first failure. Return silently on user abort.
      if (drush_get_context('DRUSH_USER_ABORT', FALSE)) {
        return FALSE;
      }
      // Signal that the command failed for all other problems.
      return drush_set_error('DRUSH_DOWNLOAD_FAILED', dt("Could not download requested project(s)."));
    }
    $request['version'] = $release['version'];

    $project_release_info = $release_info->get($request);
    $request['project_type'] = $project_release_info->getType();

    // Determine the name of the directory that will contain the project.
    // We face here all the assymetries to make it smooth for package handlers.
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
    drush_log(dt('Downloading project !name ...', array('!name' => $request['name'])));
    $request['full_project_path'] = package_handler_download_project($request, $release);
    if (!$request['full_project_path']) {
      // Delete the cached update service file since it may be invalid.
      $release_info->clearCached($request);
      drush_log(dt('Error downloading !name', array('!name' => $request['name']), LogLevel::ERROR));
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
          drush_log(dt("Skip installation of !project to !dest.", array('!project' => $request['name'], '!dest' => $request['project_install_location'])), LogLevel::WARNING);
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
      drush_log(dt("Project !project (!version) downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $request['project_install_location'])), LogLevel::SUCCESS);
      // Adjust full_project_path to the final project location.
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
        $project_release_info->getReleaseNotes($release['version'], FALSE);
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
 * the hook will move modules that contain only Drush commands to
 * /usr/share/drush/commands if it exists, or $HOME/.drush if the
 * site-wide location does not exist.
 */
function pm_drush_pm_download_destination_alter(&$request, $release) {
  // A module is a pure Drush command if it has no .info.yml (8+) and contains no
  // .drush.inc files.  Skip this test for Drush itself, though; we do
  // not want to download Drush to the ~/.drush folder.
  if (in_array($request['project_type'], array('module', 'utility')) && ($request['name'] != 'drush')) {
    $drush_command_files = drush_scan_directory($request['full_project_path'], '/.*\.drush.inc/');
    if (!empty($drush_command_files)) {
      $pattern = drush_drupal_major_version() >= 8 ? '/.*\.info/' : '/.*\.module/';
      $module_files = drush_scan_directory($request['full_project_path'], $pattern);
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
          $request['project_install_location'] = $install_dir;
        }
      }
      // We need to clear the Drush commandfile cache so that
      // our newly-downloaded Drush extension commandfiles can be found.
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
  $site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT');
  $full_site_root = (empty($drupal_root) || empty($site_root)) ? '' : $drupal_root .'/'. $site_root;
  $sitewide = empty($drupal_root) ? '' : $drupal_root . '/' . drush_drupal_sitewide_directory();

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
