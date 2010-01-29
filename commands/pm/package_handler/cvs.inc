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
  drush_log("Downloading project $project ...");

  $destination = rtrim($path, '/') . '/' . $project;

  // Check it out.
  drush_pm_cvs($project, $info, $destination);

  if (!drush_get_context('DRUSH_SIMULATE')) {
    if (is_dir($destination)) {
      drush_log("Checking out " . $project . " was successful.");
      return TRUE;
    }
    else {
      drush_set_error('DRUSH_PM_CVS_CHECKOUT_PROBLEMS', dt("Unable to check out !project to !destination from cvs.drupal.org", array('!project' => $project, '!destination' => $destination)));
      return FALSE;
    }
  }
}

/**
 * Update a project (so far, only modules are supported).
 *
 * @param $project The short name of the drupal.org project
 * @param $info The details (fetched from drupal.org via xml-rpc)
 * @param $path The path of the module to update.
 */
function package_handler_update_project($project, $info, $path = '.') {
  drush_log("Updating project $project ...");


  // Check out a fresh copy, or update an existing one.
  drush_pm_cvs($project, $info, $path);

  if (is_dir($path) && !drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Updating out " . $project . " was successful.");
    return TRUE;
  }
  else {
    return drush_set_error('DRUSH_PM_CVS_UPDATE_PROBLEMS', dt("Unable to update !project from cvs.drupal.org", array('!project' => $project)));
  }
}

/**
 * General CVS helper function
 * @param $project The short name of the drupal.org project
 * @param $info The details (fetched from drupal.org via xml-rpc)
 * @param $path The path of the module to work on.
 */
function drush_pm_cvs($project, $info, $path) {
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

  $cvsmethod = drush_get_option('cvsmethod', '');
  if (empty($cvsmethod) && file_exists($path . '/.svn')) {
    // If we have .svn files the default is to update in place
    // this will try and merge any changes, which could break things
    // but we assume anyone using SVN is competent enough to deal with this!
    // TODO: Make this work with BZR.
    $cvsmethod = 'update';
  }
  else {
    $cvsmethod = 'checkout';
  }

  // Normalize to forward slashes.
  $cwd = str_replace('\\','/',getcwd());
  $path = '.' . ltrim(str_replace($cwd, '', $path), '.');

  $cvsparts = array();
  $cvsparts[] = '-z6';
  $cvsparts[] = drush_get_option('cvsparams', '');
  $cvsparts[] = '-d:pserver:' . drush_get_option('cvscredentials', 'anonymous:anonymous') . '@cvs.drupal.org:/cvs/' . $repos;
  $cvsparts[] = $cvsmethod;
  $cvsparts[] = '-r '. $info['tag'];
  if ($cvsmethod == 'checkout') {
    $cvsparts[] = '-d '. $path;
    $cvsparts[] = $cvsdir . $project;
  }
  else {
    $cvsparts[] = $path;
  }

  if (!drush_shell_exec('cvs ' . implode(' ', $cvsparts))) {
    drush_set_error('DRUSH_PM_UNABLE_CHECKOUT', "Unable to checkout $project from cvs.drupal.org.");
  }
}
