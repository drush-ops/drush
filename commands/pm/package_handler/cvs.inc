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
  drush_log('Downloading project ' . $project['name'] . ' ...');

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
 * General CVS helper function
 * @param $project The project array with name, base and full (final) paths. 
 * @param $release The release details array from drupal.org
 */
function drush_pm_cvs($project, $release) {
  // By default we assume a module
  $repos = 'drupal-contrib';
  $cvsdir = 'contributions/modules/';

  switch ($project['project_type']) {
    case 'theme':
      $cvsdir = 'contributions/themes/';
      break;
    case 'theme engine':
      $cvsdir = 'contributions/theme-engines/';
      break;
    case 'translation':
      drush_set_error('DRUSH_PM_CVS_NO_TRANSLATIONS', 'You cannot install translations with CVS, because they require processing (normally done on drupal.org to produce the tar.gz files).');
      exit();
    case 'profile':
      $cvsdir = 'contributions/profiles/';
      break;
    case 'core':
      $cvsdir = '';
      $repos = 'drupal';
  }

  $cvsmethod = drush_get_option('cvsmethod', FALSE);
  $cvsparams = drush_get_option('cvsparams', FALSE);
  // Determine reasonable defaults, based on context.
  if (empty($cvsmethod)) {
    $cvsmethod = 'checkout';
    // If we have an existing working copy we update.
    if (is_dir($project['full_project_path'] . '/CVS')) {
      $cvsmethod = 'update';
      // By default we update overwriting changes, however if we have
      // an existing CVS checkout that is version controlled then the
      // default is to update in place, which will attempt to merge changes
      // but we assume anyone using SVN is competent enough to deal with this!
      // TODO: Make this work with BZR etc.
      if (!file_exists($path . '/.svn') && $cvsparams === FALSE) {
        $cvsparams = '-dPC';
      }
    }
  }
  if ($cvsparams === FALSE) {
    $cvsparams = '-dP';
  }

  // CVS only accepts relative paths.
  $cwd = drush_get_context('DRUSH_DRUPAL_ROOT');
  $path = '.' . ltrim(str_replace($cwd, '', $project['full_project_path']), '.');

  $cvsparts = array();
  $cvsparts[] = '-z6';
  $cvsparts[] = $cvsparams;
  $cvsparts[] = '-d:pserver:' . drush_get_option('cvscredentials', 'anonymous:anonymous') . '@cvs.drupal.org:/cvs/' . $repos;
  $cvsparts[] = $cvsmethod;
  $cvsparts[] = '-r '. $release['tag'];
  if ($cvsmethod == 'checkout') {
    $cvsparts[] = '-d ' . $path;
    $cvsparts[] = $cvsdir . $project['name'];
  }
  else {
    $cvsparts[] = $path;
  }

  if (!drush_shell_exec('cvs ' . implode(' ', $cvsparts))) {
    drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', 'Unable to checkout ' . $project['name'] . ' from cvs.drupal.org.');
  }
}
