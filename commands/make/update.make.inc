<?php

/**
 * @file
 * make-update command implementation.
 */

/**
 * Command callback for make-update.
 */
function drush_make_update($makefile = NULL) {
  // Process makefile and get projects array.
  $info = _make_parse_info_file($makefile);

  make_prepare_projects(FALSE, $info);
  $make_projects = drush_get_option('DRUSH_MAKE_PROJECTS', FALSE);

  // Pick projects coming from drupal.org and adjust its structure
  // to feed update_status engine.
  // We provide here some heuristics to determine if a git clone comes
  // from drupal.org and also guess its version.
  // #TODO# move git checks to make_prepare_projects() and use it to leverage
  // git_drupalorg engine.
  $projects = array();
  foreach ($make_projects as $project_name => $project) {
    if (($project['download']['type'] == 'git') && !empty($project['download']['url'])) {
      // TODO check that tag or branch are valid version strings (with pm_parse_version()).
      if (!empty($project['download']['tag'])) {
        $version = $project['download']['tag'];
      }
      elseif (!empty($project['download']['branch'])) {
        $version = $project['download']['branch'] . '-dev';
      }
      /*
      elseif (!empty($project['download']['refspec'])) {
        #TODO# Parse refspec.
      }
      */
      else {
        // If no tag or branch, we can't match a d.o version.
        continue;
      }
      $projects[$project_name] = $project + array(
        'path'    => '',
        'label'   => $project_name,
        'version' => $version,
      );
    }
    elseif ($project['download']['type'] == 'pm') {
      $projects[$project_name] = $project + array(
        'path'  => '',
        'label' => $project_name,
      );
    }
  }

  // Check for updates.
  $update_status = drush_get_engine('update_status');
  $update_info = $update_status->getStatus($projects, TRUE);

  $security_only = drush_get_option('security-only', FALSE);
  foreach ($update_info as $project_name => $project_update_info) {
    if (!$security_only || ($security_only && $project_update_info['status'] == DRUSH_UPDATESTATUS_NOT_SECURE)) {
      $make_projects[$project_name]['download']['full_version'] = $project_update_info['recommended'];
    }
  }

  // Inject back make projects and generate the updated makefile.
  drush_set_option('DRUSH_MAKE_PROJECTS', $make_projects);
  make_generate_from_makefile(drush_get_option('result-file'), $makefile);
}

