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
function drush_pm_updatestatus() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  // Get specific requests.
  $requests = pm_parse_arguments(func_get_args(), FALSE);

  // Get installed extensions and projects.
  $extensions = drush_get_extensions();
  $projects = drush_get_projects($extensions);

  // Parse out project name and version.
  $requests = pm_parse_project_version($requests, $projects);

  $update_info = _pm_get_update_info($projects);

  foreach ($extensions as $name => $extension) {
    // Add an item to $update_info for each enabled extension which was obtained
    // from cvs or git and its project is unknown (because of cvs_deploy or
    // git_deploy is not enabled).
    if (!isset($extension->info['project'])) {
      if ((isset($extension->vcs)) && ($extension->status)) {
        $update_info[$name] = array(
          'name' => $name,
          'label' => $extension->label,
          'existing_version' => 'Unknown',
          'status' => DRUSH_PM_REQUESTED_PROJECT_NOT_PACKAGED,
          'status_msg' => dt('Project was not packaged by drupal.org but obtained from !vcs. You need to enable !vcs_deploy module', array('!vcs' => $extension->vcs)),
        );
        // The user may have requested to update a project matching this
        // extension. If it was by coincidence or error we don't mind as we've
        // already added an item to $update_info. Just clean up $requests.
        if (isset($requests[$name])) {
          unset($requests[$name]);
        }
      }
    }
    // Additionally if the extension name is distinct to the project name and
    // the user asked to update the extension, fix the request.
    elseif ((isset($requests[$name])) && ($extension->name != $extension->info['project'])) {
      $requests[$extension->info['project']] = $requests[$name];
      unset($requests[$name]);
    }
  }
  // If specific project updates were requested then remove releases for all
  // others.
  $requested = func_get_args();
  if (!empty($requested)) {
    foreach ($update_info as $name => $project) {
      if (!isset($requests[$name])) {
        unset($update_info[$name]);
      }
    }
  }
  // Add an item to $update_info for each request not present in $update_info.
  foreach ($requests as $name => $request) {
    if (!isset($update_info[$name])) {
      // Disabled projects.
      if ((isset($projects[$name])) && ($projects[$name]['status'] == 0)) {
        $update_info[$name] = array(
          'name' => $name,
          'label' => $projects[$name]['label'],
          'existing_version' => $projects[$name]['version'],
          'status' => DRUSH_PM_REQUESTED_PROJECT_NOT_UPDATEABLE,
        );
        unset($requests[$name]);
      }
      // At this point we are unable to find matching installed project.
      // It does not exist at all or it is misspelled,...
      else {
        $update_info[$name] = array(
          'name' => $name,
          'label' => $name,
          'existing_version' => 'Unknown',
          'status'=> DRUSH_PM_REQUESTED_PROJECT_NOT_FOUND,
        );
      }
    }
  }

  // If specific versions were requested, match the requested release.
  foreach ($requests as $name => $request) {
    if (!empty($request['version'])) {
      $release = pm_get_release($request, $update_info[$name]);
      if (!$release) {
        $update_info[$name]['status'] = DRUSH_PM_REQUESTED_VERSION_NOT_FOUND;
      }
      else if ($release['version'] == $update_info[$name]['existing_version']) {
        $update_info[$name]['status'] = DRUSH_PM_REQUESTED_CURRENT;
      }
      else {
        $update_info[$name]['status'] = DRUSH_PM_REQUESTED_UPDATE;
      }
      // Set the candidate version to the requested release.
      $update_info[$name]['candidate_version'] = $release['version'];
    }
  }
  // Process locks specified on the command line.
  $locked_list = drush_pm_update_lock($update_info, drush_get_option_list('lock'), drush_get_option_list('unlock'), drush_get_option('lock-message'));

  // Build project updatable messages, set candidate version and mark
  // 'updatable' in the project.
  foreach ($update_info as $key => $project) {
    switch($project['status']) {
      case DRUSH_PM_REQUESTED_UPDATE:
        $status = dt('Specified version available');
        $project['updateable'] = TRUE;
        break;
      case DRUSH_PM_REQUESTED_CURRENT:
        $status = dt('Specified version already installed');
        break;
      case DRUSH_PM_REQUESTED_PROJECT_NOT_PACKAGED:
        $status = $project['status_msg'];
        break;
      case DRUSH_PM_REQUESTED_VERSION_NOT_FOUND:
        $status = dt('Specified version not found');
        break;
      case DRUSH_PM_REQUESTED_PROJECT_NOT_FOUND:
        $status = dt('Specified project not found');
        break;
      case DRUSH_PM_REQUESTED_PROJECT_NOT_UPDATEABLE:
        $status = dt('Project has no enabled extensions and can\'t be updated');
        break;
      default:
        // This can set $project['updateable'] and $project['candidate_version']
        $status = pm_update_filter($project);
        break;
    }

    if (isset($project['locked'])) {
      $status = $project['locked'] . " ($status)";
    }
    // Persist candidate_version in $update_info (plural).
    if (empty($project['candidate_version'])) {
      $update_info[$key]['candidate_version'] = $project['existing_version']; // Default to no change
    }
    else {
      $update_info[$key]['candidate_version'] = $project['candidate_version'];
    }
    $update_info[$key]['status_msg'] = $status;
    if (isset($project['updateable'])) {
      $update_info[$key]['updateable'] = $project['updateable'];
    }
  }

  // Only list projects that have updates available. Use pm-list if you want more info.
  $updateable = pm_project_filter($update_info, drush_get_option('security-only'));
  return $updateable;
}

/**
 * Return an array of updateable projects and fill $rows.
 *
 * Array of updateable projects is obtained from calculated project update
 * status and $security_only flag.
 */
function pm_project_filter(&$update_info, $security_only) {
  $updateable = array();
  foreach ($update_info as $key => $project) {
    if (!empty($project['updateable'])) {
      $updateable[$key] = $project;
      // Find only security updates
      if ($security_only && ($project['status'] != UPDATE_NOT_SECURE)) {
        unset($updateable[$key]);
      }
    }
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
  // If installed version is dev and the candidate version is older, choose
  // latest dev as candidate.
  if (($project['install_type'] == 'dev') && isset($project['candidate_version'])) {
    if ($project['releases'][$project['candidate_version']]['date'] < $project['info']['datestamp']) {
      $project['candidate_version'] = $project['latest_dev'];
      if ($project['releases'][$project['candidate_version']]['date'] <= $project['info']['datestamp']) {
        $project['candidate_version'] = $project['existing_version'];
        $project['updateable'] = FALSE;
      }
    }
  }
}


/**
 * Get the a best release match for a requested update.
 *
 * @param $request A information array for the requested project
 * @param $project A project information array for this project, as returned by an update service from pm_get_extensions()
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
