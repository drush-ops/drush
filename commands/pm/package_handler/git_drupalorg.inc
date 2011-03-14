<?php

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
 * Download a project.
 *
 * @param $request
 *   The project array with name, base and full (final) paths.
 * @param $release
 *   The release details array from drupal.org.
 */
function package_handler_download_project(&$request, $release) {
  $repository = 'git://git.drupal.org/project/' . $request['name'] . '.git';
  $tag = $release['tag'];

  if (drush_get_option('cache')) {
    $gitcache = drush_server_home() . '/.drush/gitcache';
    drush_gitcache_check($gitcache);

    drush_shell_cd_and_exec($gitcache, 'git remote show');
    $cached_modules = drush_shell_exec_output();

    if (!in_array($request['name'], $cached_modules)) {
      drush_gitcache_add_project($gitcache, $request, $repository);
    }
  }

  // Clone the repo into its appropriate target location.
  $command  = 'git clone';
  $command .= ' ' . drush_get_option('gitcloneparams');
  if (drush_get_option('cache')) {
    $command .= ' --reference ' . drush_escapeshellarg($gitcache);
  }
  if (drush_get_context('DRUSH_VERBOSE')) {
    $command .= ' --verbose --progress';
  }
  $command .= ' ' . drush_escapeshellarg($repository);
  $command .= ' ' . drush_escapeshellarg($request['full_project_path']);
  if (!drush_shell_exec($command)) {
    return drush_set_error('DRUSH_PM_GIT_CHECKOUT_PROBLEMS', dt('Unable to clone project !name from git.drupal.org.', array('!name' => $request['name'])));
  }

  // Check if the 'tag' from the release feed is a tag or a branch.
  // If the tag exists, git will return it
  if (!drush_shell_cd_and_exec($request['full_project_path'], 'git tag -l ' . drush_escapeshellarg($tag))) {
    return drush_set_error('DRUSH_PM_GIT_CHECKOUT_PROBLEMS', dt('Unable to clone project !name from git.drupal.org.', array('!name' => $request['name'])));
  }
  $output = drush_shell_exec_output();

  if (isset($output[0]) && ($output[0] == $tag)) {
    // If we want a tag, simply checkout it. The checkout will end up in
    // "detached head" state.
    $command  = 'git checkout ' . drush_get_option('gitcheckoutparams');
    $command .= ' ' . drush_escapeshellarg($tag);
    if (!drush_shell_cd_and_exec($request['full_project_path'], $command)) {
      return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $request['name'] . ' from git.drupal.org.');
    }
  }
  else {
    // Else, we want to checkout a branch.
    // First check if we are not already in the correct branch.
    if (!drush_shell_cd_and_exec($request['full_project_path'], 'git symbolic-ref HEAD')) {
      return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $request['name'] . ' from git.drupal.org.');
    }
    $output = drush_shell_exec_output();
    $current_branch = preg_replace('@^refs/heads/@', '', $output[0]);

    // If we are not on the correct branch already, switch to the correct one.
    if ($current_branch != $tag) {
      $command  = 'git checkout';
      $command .= ' ' . drush_get_option('gitcheckoutparams');
      $command .= ' --track ' . drush_escapeshellarg('origin/' . $tag) . ' -b ' . drush_escapeshellarg($tag);
      if (!drush_shell_cd_and_exec($request['full_project_path'], $command)) {
        return drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to retrieve ' . $request['name'] . ' from git.drupal.org.');
      }
    }
  }

  return TRUE;
}

/**
 * Update a project (so far, only modules are supported).
 *
 * @param $request
 *   The project array with name, base and full (final) paths.
 * @param $release
 *   The release details array from drupal.org.
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

/**
 * Post download action.
 *
 * This action take place once the project is placed in its final location.
 *
 * Here we add the project as a git submodule.
 */
function package_handler_post_download($project) {
  if (drush_get_option('gitsubmodule', FALSE)) {
    // Obtain the superproject path, then add as submodule.
    if (drush_shell_cd_and_exec(dirname($project['full_project_path']), 'git rev-parse --show-toplevel')) {
      $output = drush_shell_exec_output();
      $superproject = $output[0];
      // Add the downloaded project as a submodule of its git superproject.
      $command = array();
      $command[] = 'git submodule add';
      $command[] = drush_get_option('gitsubmoduleaddparams');
      $command[] = $project['repository'];
      // We need the submodule relative path.
      $command[] = substr($project['full_project_path'], strlen($superproject) + 1);
      if (!drush_shell_cd_and_exec($superproject, implode(' ', $command))) {
        return drush_set_error('DRUSH_PM_GIT_CHECKOUT_PROBLEMS', dt('Unable to add !name as a git submodule of !super.', array('!name' => $project['name'], '!super' => $superproject)));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_GIT_SUBMODULE_PROBLEMS', dt('Unable to create !project as a git submodule: !dir is not in a Git repository.', array('!project' => $project['name'], '!dir' => dirname($project['full_project_path']))));
    }
  }
}

/**
 * Check for gitcache directory or create it.
 *
 * @param $gitcache
 *   The location of the gitcache directory.
 */
function drush_gitcache_check($gitcache) {
  if (is_dir(drush_server_home() . '/.drush')) {
    drush_mkdir($gitcache);
    if (!drush_shell_cd_and_exec($gitcache, 'test $(git rev-parse --is-bare-repository) == "true"')) {
      drush_shell_cd_and_exec($gitcache, 'git init --bare');
      drush_shell_cd_and_exec($gitcache, 'git config core.compression 1');
    }
  }
}

/**
 * Add a project to the cache.
 *
 * @param $gitcache
 *   The location of the gitcache directory.
 * @param $request
 *   The project array with name, base and full (final) paths.
 * @param $release
 *   The release details array from drupal.org.
 */
function drush_gitcache_add_project($gitcache, $request, $repository) {
  // On the first fetch, git will check for common commits with the remote
  // repository, so to speed this up we initially fetch from a local clone.
  $tmpdir = drush_tempdir();
  $command = 'git clone --bare ';
  if (drush_get_context('DRUSH_VERBOSE')) {
    $command .= ' --verbose --progress ';
  }
  $command .= '%s';
  if (drush_shell_cd_and_exec($tmpdir, $command, $repository)) {
    // Temporarily set the remote url to our temporary clone and fetch from there.
    drush_shell_cd_and_exec($gitcache, 'git remote add %s %s && git fetch %s --tags', $request['name'], $tmpdir, $request['name']);

    // Change the remote URL and fetch normally.
    drush_shell_cd_and_exec($gitcache, 'git remote set-url %s %s && git fetch %s --tags', $request['name'], $repository, $request['name']);
  }
}
