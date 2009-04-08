<?php
// $Id$

/**
 * @file Drush PM Wget extension
 */

/**
 * Implementation of hook_drush_help().
 * TODO: Move help to an engine parameter.
 */
function package_handler_help($section) {
  switch ($section) {
    case 'drush:pm install':
    case 'drush:pm update':
      return dt("Note: the wget handler will use the curl command if wget is not found\n");
  }
}

/**
 * Install a project (so far, only modules are supported).
 *
 * @param $project The short name of the drupal.org project
 * @param $info The details (fetched from drupal.org via xml-rpc)
 * @param $path The path to install the module to.
 */
function package_handler_install_project($project, $info, $path = '.') {
  drush_verbose("Downloading project $project ...");

  // Get the filename...
  $filename = explode('/', $info['download_link']);
  $filename = array_pop($filename);
  $fullpath = $path . $filename;

  // Download it.
  if (!drush_shell_exec("wget " . $info['download_link'])) {
    drush_shell_exec("curl -O " . $info['download_link']);
  }

  if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
    drush_verbose("Downloading " . $filename . " was successful.");
  }
  else {
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', "Unable to download $filename to $path from ". $info['download_link']);
  }

  // Check Md5 hash
  if (md5_file($filename) != $info['mdhash'] && !drush_get_context('DRUSH_SIMULATE')) {
    drush_op('unlink', $filename);
    return drush_set_error('DRUSH_PM_FILE_CORRUPT', "File $filename is corrupt (wrong md5 checksum).");
  }
  else {
    drush_verbose("Md5 checksum of $filename verified.");
  }

  // Decompress
  drush_shell_exec("gzip -d " . $filename);
  $tarpath = substr($filename, 0, strlen($filename)-3);
  // Untar
  drush_shell_exec("tar -xf $tarpath -C $path");
  // We're not using tar -xzf because that's not working on windows...

  // Remove the tarball
  drush_op('unlink', $tarpath);

  // 'drupal' project gets tarred as drupal-6-10/foo instead of drupal/foo so must be excluded from test below.
  if (!is_dir($path . $project) && !drush_get_context('DRUSH_SIMULATE') && $project != 'drupal') {
    return drush_set_error('DRUSH_PM_FILE_UNTAR_ERROR', "Downloaded file $filename couldn't be untarred correctly");
  }
  else {
    return TRUE;
  }
}

/**
 * This is an alias of the install function, since they are identical
 */
function package_handler_update_project($project, $info, $path = '.') {
  return package_handler_install_project($project, $info, $path);
}
