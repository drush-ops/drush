<?php

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
    // Old version of curl shipped in darwin returns error status for --version
    // and --help. Give the chance to use it.
    if (!$success) {
      $success = drush_shell_exec('which curl');
    }
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
function package_handler_download_project(&$request, $release) {
  // Install profiles come in 3 variants. User may specify which one she wants.
  if ($request['project_type'] == 'profile') {
    // @todo Use xpath to get the right file url.
    $files = $release['files'];
    foreach ($files as $key => $file) {
      if ((string)$file->variant == drush_get_option('variant', 'full') && (string)$file->archive_type == 'tar.gz') {
        $release['download_link'] = (string)$file->url;
        $release['mdhash'] = (string)$file->md5;
        break;
      }
    }
  }
  
  $filename = explode('/', $release['download_link']);
  $filename = array_pop($filename);

  // Chdir to the download location.
  $olddir = getcwd();
  drush_op('chdir', $request['base_project_path']);

  // Download the project.
  if (!drush_shell_exec("wget -P . %s", $release['download_link'])) {
    drush_shell_exec("curl -O %s", $release['download_link']);
  }
  if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Downloading " . $filename . " was successful.");
  }
  else {
    drush_op('chdir', $olddir);
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', 'Unable to download ' . $filename . ' to ' . $request['base_project_path'] . ' from '. $release['download_link']);
  }

  // Check Md5 hash.
  if (drush_op('md5_file', $filename) != $release['mdhash'] && !drush_get_context('DRUSH_SIMULATE')) {
    drush_set_error('DRUSH_PM_FILE_CORRUPT', "File $filename is corrupt (wrong md5 checksum).");
    drush_op('unlink', $filename);
    drush_op('chdir', $olddir);
    return FALSE;
  }
  else {
    drush_log("Md5 checksum of $filename verified.");
  }

  // Decompress and untar in two steps as tar -xzf does not work on windows.
  drush_shell_exec("gzip -d %s", $filename);
  $tarpath = basename($filename, '.tar.gz');
  $tarpath = basename($tarpath, '.tgz');
  $tarpath .= '.tar';
  drush_shell_exec("tar -xf %s", $tarpath);

  // Move untarred directory to project_dir, if distinct.
  if (($request['project_type'] == 'core') || (($request['project_type'] == 'profile') && (drush_get_option('variant', 'core') == 'core'))) {
    // Obtain the dodgy project_dir for drupal core.
    // We use a separate tar -tf instead of -xvf above because
    // the output is not the same in Mac.
    drush_shell_exec("tar -tf %s", $tarpath);
    $output = drush_shell_exec_output();
    $project_dir = rtrim($output[0], DIRECTORY_SEPARATOR);
    if ($request['project_dir'] != $project_dir) {
      $path = $request['base_project_path'];
      drush_move_dir($path . '/'. $project_dir, $path . '/' . $request['project_dir']);
    }
  }

  // Cleanup. Remove the tar file and set previous working directory.
  drush_op('unlink', $tarpath);
  drush_op('chdir', $olddir);

  return TRUE;
}

/**
 * This is an alias of the download function, since they are identical
 */
function package_handler_update_project(&$request, $release) {
  return package_handler_download_project($request, $release);
}

/**
 * Post download action.
 *
 * This action take place once the project is placed in its final location.
 */
function package_handler_post_download($project) {
}

