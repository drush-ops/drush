<?php

use Drush\Log\LogLevel;

/**
 * Implementation of drush_hook_COMMAND_validate().
 */
function drush_pm_projectinfo_validate() {
  $status = drush_get_option('status');
  if (!empty($status)) {
    if (!in_array($status, array('enabled', 'disabled'), TRUE)) {
      return drush_set_error('DRUSH_PM_INVALID_PROJECT_STATUS', dt('!status is not a valid project status.', array('!status' => $status)));
    }
  }
}

/**
 * Implementation of drush_hook_COMMAND().
 */
function drush_pm_projectinfo() {
  // Get specific requests.
  $requests = pm_parse_arguments(func_get_args(), FALSE);

  // Get installed extensions and projects.
  $extensions = drush_get_extensions();
  $projects = drush_get_projects($extensions);

  // If user did not specify any projects, return them all
  if (empty($requests)) {
    $result = $projects;
  }
  else {
    $result = array();
    foreach ($requests as $name) {
      if (array_key_exists($name, $projects)) {
        $result[$name] = $projects[$name];
      }
      else {
        drush_log(dt('!project was not found.', array('!project' => $name)), LogLevel::WARNING);
        continue;
      }
    }
  }

  // Find the Drush commands that belong with each project.
  foreach ($result as $name => $project) {
    $drush_commands = pm_projectinfo_commands_in_project($project);
    if (!empty($drush_commands)) {
      $result[$name]['drush'] = $drush_commands;
    }
  }

  // If user specified --drush, remove projects with no drush extensions
  if (drush_get_option('drush')) {
    foreach ($result as $name => $project) {
      if (!array_key_exists('drush', $project)) {
        unset($result[$name]);
      }
    }
  }

  // If user specified --status=1|0, remove projects with a distinct status.
  if (($status = drush_get_option('status', FALSE)) !== FALSE) {
    $status_code = ($status == 'enabled') ? 1 : 0;
    foreach ($result as $name => $project) {
      if ($project['status'] != $status_code) {
        unset($result[$name]);
      }
    }
  }

  return $result;
}

function pm_projectinfo_commands_in_project($project) {
  $drush_commands = array();
  if (array_key_exists('path', $project)) {
    $commands = drush_get_commands();
    foreach ($commands as $commandname => $command) {
      if (!array_key_exists("is_alias", $command) && ($command['path'] == $project['path'])) {
        $drush_commands[] = $commandname;
      }
    }
  }
  return $drush_commands;
}

