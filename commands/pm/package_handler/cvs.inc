<?php
// $Id$

/**
 * @file Drush PM CVS extension
 */

/**
 * Install a project.
 *
 * @param $project The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_install_project($project, $release) {
  // Check it out.
  drush_pm_cvs($project, $release);

  if (!drush_get_context('DRUSH_SIMULATE')) {
    if (is_dir($project['full_project_path'])) {
      drush_log("Checking out " . $project['name'] . " was successful.");
      return TRUE;
    }
    else {
      drush_set_error('DRUSH_PM_CVS_CHECKOUT_PROBLEMS', dt("Unable to check out !project to !destination from cvs.drupal.org", array('!project' => $project['name'], '!destination' => $project['full_project_path'])));
      return FALSE;
    }
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

  // Check out a fresh copy, or update an existing one.
  drush_pm_cvs($project, $release);

  if (is_dir($project['full_project_path']) && !drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Updating of " . $project['name'] . " was successful.");
    return TRUE;
  }
  else {
    return drush_set_error('DRUSH_PM_CVS_UPDATE_PROBLEMS', dt("Unable to update !project from cvs.drupal.org", array('!project' => $project['name'])));
  }
}

/**
 * General CVS helper function.
 *
 * @param $project The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function drush_pm_cvs($project, $release) {
  // Build the cvs command to execute.
  $command = array('cvs');

  // Global options.
  $command[] = '-z6';
  // cvs root.
  $cvsroot = '-d:pserver:' . drush_get_option('cvscredentials', 'anonymous:anonymous') . '@cvs.drupal.org:/cvs/';
  $cvsroot .= ($project['project_type'] == 'core')?'drupal':'drupal-contrib';
  $command[] = $cvsroot;

  // CVS command ("cvs method").
  $cvsmethod = drush_get_option('cvsmethod', FALSE);
  if (empty($cvsmethod)) {
    $cvsmethod = 'checkout';
     // If we have an existing working copy we update.
    if (is_dir($project['full_project_path'] . '/CVS')) {
      $cvsmethod = 'update';
    }
  }
  $command[] = $cvsmethod;

  // CVS command options.
  $cvsparams = drush_get_option('cvsparams', FALSE);
  // common options for any command.
  $command[] = '-r '. $release['tag'];
  // command specific options.
  if ($cvsmethod == 'checkout') {
    // checkout dir.
    $command[] = '-d ' . $project['project_dir'];
    // path to cvs 'module' to check out.
    if ($project['project_type'] == 'core') {
      $command[] = $project['name']; // drupal
    }
    else {
      // strtr for 'theme engine' type.
      $command[] = 'contributions/' . strtr($project['project_type'], ' ' ,'-') . 's/' . $project['name'];
    }
  }
  else {
    if ($cvsparams === FALSE) {
      // By default we update overwriting changes, however if we have
      // an existing CVS checkout that is version controlled then the
      // default is to update in place, which will attempt to merge changes
      // but we assume anyone using SVN is competent enough to deal with this!
      // TODO: Make this work with BZR etc.
      if (!file_exists($project['full_project_path'] . '/.svn')) {
        $command[] = '-dPC';
      }
      else {
        $command[] = '-dP';
      }
    }
    // Directory to work on.
    $command[] = $project['project_dir'];
  }

  // CVS only accepts relative paths. We will cd in the checkout path right
  // before running the cvs command.
  if (!drush_shell_cd_and_exec($project['base_project_path'], implode(' ', $command))) {
    drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to checkout ' . $project['name'] . ' from cvs.drupal.org.');
  }
}
