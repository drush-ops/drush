<?php
// $Id$

/**
 * @file Drush PM Wget extension
 */

/**
 * Validate this package handler can run.
 */
function package_handler_validate() {
  // Check wget or curl command exists. Disable possible output.
  $debug = drush_get_context('DRUSH_DEBUG');
  drush_set_context('DRUSH_DEBUG', FALSE);
  $success = drush_shell_exec('wget --version');
  if (!$success) {
    $success = drush_shell_exec('curl --version');
  }
  drush_set_context('DRUSH_DEBUG', $debug);
  if (!$success) {
    return drush_set_error('DRUSH_SHELL_COMMAND_NOT_FOUND', dt('wget nor curl executables found.'));
  }
}

/**
 * Download a project.
 *
 * @param $request Array with information on the request to download.
 * @param $release The release details array from drupal.org.
 */
function package_handler_install_project(&$request, $release) {
  // Get the filename.
  $filename = explode('/', $release['download_link']);
  $filename = array_pop($filename);

  // Chdir to the download location.
  $olddir = getcwd();
  chdir($request['base_project_path']);

  // Download the project.
  if (!drush_shell_exec("wget -P . " . $release['download_link'])) {
    drush_shell_exec("curl -O " . $release['download_link']);
  }
  if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Downloading " . $filename . " was successful.");
  }
  else {
    chdir($olddir);
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', 'Unable to download ' . $filename . ' to ' . $request['base_project_path'] . ' from '. $release['download_link']);
  }

  // Check Md5 hash.
  if (md5_file($filename) != $release['mdhash'] && !drush_get_context('DRUSH_SIMULATE')) {
    drush_set_error('DRUSH_PM_FILE_CORRUPT', "File $filename is corrupt (wrong md5 checksum).");
    drush_op('unlink', $filename);
    chdir($olddir);
    return FALSE;
  }
  else {
    drush_log("Md5 checksum of $filename verified.");
  }

  // Decompress and untar in two steps as tar -xzf does not work on windows.
  drush_shell_exec("gzip -d " . $filename);
  $tarpath = basename($filename, '.tar.gz');
  $tarpath = basename($tarpath, '.tgz');
  $tarpath .= '.tar';
  drush_shell_exec("tar -xf $tarpath");

  // Move untarred directory to project_dir, if distinct.
  if (($request['project_type'] == 'core') || (($request['project_type'] == 'profile') && (drush_get_option('variant', 'core') == 'core'))) {
    // Obtain the dodgy project_dir for drupal core.
    // We use a separate tar -tf instead of -xvf above because
    // the output is not the same in Mac.
    drush_shell_exec("tar -tf $tarpath");
    $output = drush_shell_exec_output();
    $project_dir = rtrim($output[0], DIRECTORY_SEPARATOR);
    if ($request['project_dir'] != $project_dir) {
      $path = $request['base_project_path'];
      drush_move_dir($path . '/'. $project_dir, $path . '/' . $request['project_dir']);
    }
  }

  // Cleanup. Remove the tar file and set previous working directory.
  drush_op('unlink', $tarpath);
  chdir($olddir);

  return TRUE;
}

/**
 * This is an alias of the install function, since they are identical
 */
function package_handler_update_project(&$request, $release) {
  return package_handler_install_project($request, $release);
}
