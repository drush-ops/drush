<?php
// $Id$

/**
 * @file Drush PM drupal.org Git extension.
 */

/**
 * Install a project.
 *
 * @param $project The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_install_project($project, $release) {
  drush_log('Downloading project ' . $project['name'] . ' ...');

  $project_name = strtok($project['name'], ' ');

  if (isset($release['version_extra']) && $release['version_extra'] == 'dev') {
    // Use the development repository, not supported yet.
    $repository = 'git://git.drupal.org/project/' . $project_name . '.git';
    $tag = $release['tag'];
  }
  else {
    // Use a stable repository.
    $repository = 'git://git.drupal.org/contributions-stable/' . $project_name . '.git';
    $tag = $release['version'];
  }

  // Determine if we're going to init using submodules or not, as our logic
  // branches as a result
  $submodule = drush_get_option('gitsubmodule', FALSE);
  $function = 'package_handler_install_project_gitdo_' . ($submodule ? 'submodule' : 'normal');
  return $function($project, $repository, $tag);


}

function package_handler_install_project_gitdo_normal($project, $repository, $tag) {
  // Clone the repo into its appropriate target location.
  $commands = array();
  $commands[] = 'git clone ' . escapeshellarg($repository) . ' ' . escapeshellarg($project['full_project_path']);
  // Check out the appropriate branch.
  $commands[] = 'cd ' . escapeshellarg($project['full_project_path']);
  $commands[] = 'git checkout ' . escapeshellarg($tag);

  if (!drush_shell_exec(implode(' && ', $commands))) {
    return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $project['name'] . ' from git.drupal.org.');
  }
  else {
    return TRUE;
  }
}

function package_handler_install_project_gitdo_submodule($project, $repository, $tag) {
  // Verify that we are in a Git repository.
  if (drush_shell_exec("cd " . escapeshellarg($project['base_project_path']) . " ; git rev-parse --git-dir")) {
    $output = drush_shell_exec_output();
    if (isset($output[0])) {
      $git_dir = $output[0];
    }
  }
  if (!isset($git_dir)) {
    return drush_set_error('DRUSH_PM_GIT_CHECKOUT_PROBLEMS', dt('Unable to create !project as a git submodule: !dir is not in a Git repository.', array('!project' => $project['name'], '!dir' => $project['base_project_path'])));
  }

  // Create the project path and find its true location, git submodule doesn't
  // like symbolic links.
  mkdir($project['full_project_path']);
  $full_project_path = realpath($project['full_project_path']);
  rmdir($full_project_path);

  // Add the submodule; this clones it into place and registers it in the
  // superproject.
  $commands = array();
  $commands[] = 'cd ' . escapeshellarg($git_dir . '/..');
  $commands[] = 'git submodule add ' . escapeshellarg($repository) . ' ' . escapeshellarg($full_project_path);
  $commands[] = 'cd ' . escapeshellarg($project['full_project_path']);
  $commands[] = 'git checkout ' . escapeshellarg($tag);

  if (!drush_shell_exec(implode(' && ', $commands))) {
    return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $project['name'] . ' from git.drupal.org.');
  }
  else {
    return TRUE;
  }
}

/**
 * Update a project (so far, only modules are supported).
 *
 * @param $project The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_update_project($project, $release) {
  drush_log('Updating project ' . $project['name'] . ' ...');

  $commands = array();
  $commands[] = 'cd ' . escapeshellarg($project['full_project_path']);

  if ($release['version_extra'] == 'dev') {
    // Update the branch of the development repository.
    $commands[] = 'git pull';
  }
  else {
    // Use a stable repository.
    $commands[] = 'git fetch';
    $commands[] = 'git checkout ' . escapeshellarg($release['version']);
  }

  if (!drush_shell_exec(implode(' ; ', $commands))) {
    return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to update ' . $project['name'] . ' from git.drupal.org.');
  }
  else {
    return TRUE;
  }
}
