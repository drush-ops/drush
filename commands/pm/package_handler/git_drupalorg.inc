<?php
// $Id$

/**
 * @file Drush PM drupal.org Git extension.
 */

/**
 * Validate this package handler can run.
 */
function package_handler_validate() {
  // Check git command exists. Disable possible output.
  $debug = drush_get_context('DRUSH_DEBUG');
  drush_set_context('DRUSH_DEBUG', FALSE);
  $success = drush_shell_exec('git --version');
  drush_set_context('DRUSH_DEBUG', $debug);
  if (!$success) {
    return drush_set_error('DRUSH_SHELL_COMMAND_NOT_FOUND', dt('git executable not found.'));
  }
  // Check git_deploy is enabled. Only for bootstrapped sites.
  if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
    if (!module_exists('git_deploy')) {
      drush_log(dt('git package handler needs git_deploy module enabled to work properly.'), 'warning');
    }
  }
}

/**
 * Install a project.
 *
 * @param $request The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_install_project(&$request, $release) {
  drush_log(dt('Downloading project !name ...', array('!name' => $request['name'])));

  if (isset($release['version_extra']) && $release['version_extra'] == 'dev') {
    // Use the development repository, not supported yet.
    $repository = 'git://git.drupal.org/project/' . $request['name'] . '.git';
    // Strip -dev off the end.
    $tag = substr($release['version'], 0, -4);
  }
  else {
    // Use a stable repository.
    $repository = 'git://git.drupal.org/project-stable/' . $request['name'] . '.git';
    $tag = $release['version'];
  }
  $request['repository'] = $repository;

  // Clone the repo into its appropriate target location.
  $command = array();
  $command[] = 'git clone';
  $command[] = drush_get_option('gitcloneparams');
  $command[] = $repository;
  $command[] = $request['full_project_path'];
  if (!drush_shell_exec(implode(' ', $command))) {
    return drush_set_error('DRUSH_PM_GIT_CHECKOUT_PROBLEMS', dt('Unable to clone project !name from git.drupal.org.', array('!name' => $request['name'])));
  }

  // Check out the appropriate branch.
  $command = array();
  $command[] = 'git checkout';
  $command[] = drush_get_option('gitcheckoutparams');
  $command[] = $tag;
  if (!drush_shell_cd_and_exec($request['full_project_path'], implode(' ', $command))) {
    return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $request['name'] . ' from git.drupal.org.');
  }

  return TRUE;
}

/**
 * Update a project (so far, only modules are supported).
 *
 * @param $request The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_update_project($request, $release) {
  drush_log('Updating project ' . $request['name'] . ' ...');

  $commands = array();
  if ($release['version_extra'] == 'dev') {
    // Update the branch of the development repository.
    $commands[] = 'git pull';
    $commands[] = drush_get_option('gitpullparams');
  }
  else {
    // Use a stable repository.
    $commands[] = 'git fetch';
    $commands[] = drush_get_option('gitfetchparams');
    $commands[] = ';';
    $commands[] = 'git checkout';
    $commands[] = drush_get_option('gitcheckoutparams');
    $commands[] = $release['version'];
  }

  if (!drush_shell_cd_and_exec($request['full_project_path'], implode(' ', $commands))) {
    return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to update ' . $request['name'] . ' from git.drupal.org.');
  }

  return TRUE;
}
