<?php
// $Id$

/**
 * @file Drush PM CVS extension
 */

/**
 * Install a project (so far, only modules are supported).
 *
 * @param $project The short name of the drupal.org project
 * @param $info The details (fetched from drupal.org's xml file)
 * @param $path The path to install the module to.
 */
function package_handler_install_project($project, $info, $path = '.') {
  drush_op('chdir', $path);

  drush_log("Downloading project $project ...");

  $cvsparams = drush_get_option('cvsparams');

  // Check it out.
  drush_pm_cvs_checkout($project, $info, $cvsparams);

  if (!drush_get_context('DRUSH_SIMULATE')) {
    if (is_dir($path . $project)) {
      drush_log("Checking out " . $project . " was successful.");
      return TRUE;
    }
    else {
      drush_set_error('DRUSH_PM_CVS_CHECKOUT_PROBLEMS', dt("Unable to check out !filename to !path from cvs.drupal.org", array('!filename' => $filename, '!path' => $path)));
      return FALSE;
    }
  }
}

/**
 * Update a project (so far, only modules are supported).
 *
 * @param $project The short name of the drupal.org project
 * @param $info The details (fetched from drupal.org via xml-rpc)
 * @param $path The path to install the module to.
 */
function package_handler_update_project($project, $info, $path = '.') {
  drush_op('chdir', $path);

  drush_log("Updating project $project ...");

  $cvsmethod = drush_get_option('cvsmethod');
  if (empty($cvsmethod) && file_exists($project. '/.svn')) {
    // If we have .svn files the default is to update in place
    // this will try and merge any changes, which could break things
    // but we assume anyone using SVN is competent enough to deal with this!
    $cvsmethod = 'update';
  }

  $cvsparams = drush_get_option('cvsparams');

  drush_log($cvsmethod);
  drush_log(strpos(strtolower($cvsmethod), 'up'));
  if (substr($cvsmethod, 0, 2) == 'up') {
    // Update the working copy.
    drush_op('chdir', $project);
    if (!drush_shell_exec('cvs update -dP '. $cvsparams . ' -r '. $info['tag'])) {
      drush_die("Unable to update $project from cvs.drupal.org.");
    }
    drush_op('chdir', '..');
  }
  else {
    // Check it out.
    drush_pm_cvs_checkout($project, $info, $cvsparams);
  }

  if (is_dir($path . $project) && !drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Updating out " . $project . " was successful.");
    return TRUE;
  }
  else {
    return drush_set_error('DRUSH_PM_CVS_UPDATE_PROBLEMS', dt("Unable to update !project from cvs.drupal.org", array('!project' => $project)));
  }
}

function drush_pm_cvs_checkout($project, $info, $cvsparams) {
  // By default we assume a module
  $repos = 'drupal-contrib';
  $cvsdir = 'contributions/modules/';

  switch ($info['type']) {
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
  
  $cvscredentials = drush_get_option('cvscredentials', 'anonymous:anonymous');

  // TRUE means failure in the twilight zone.
  if (drush_shell_exec('cvs -z6 '. $cvsparams ." -d:pserver:$cvscredentials@cvs.drupal.org:/cvs/" . $repos . ' checkout -d '. $project .' -r '. $info['tag'] .' '. $cvsdir . $project)) {
    drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', "Unable to checkout $project from cvs.drupal.org.");
  }
}
