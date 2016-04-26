<?php

/**
 * @file
 * Drush PM Wget extension
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

  return TRUE;
}

/**
 * Download a project.
 *
 * @param $request Array with information on the request to download.
 * @param $release The release details array from drupal.org.
 */
function package_handler_download_project(&$request, $release) {
  // Install profiles come in several variants. User may specify which one she wants.
  if ($request['project_type'] == 'profile') {
    $variant = drush_get_option('variant', 'full');
    foreach ($release['files'] as $file) {
      if ($file['variant'] == $variant && $file['archive_type'] == 'tar.gz') {
        $release = array_merge($release, $file);
        break;
      }
    }
  }

  // Add <date> to download link, so it is part of the cache key. Dev snapshots can then be cached forever.
  $download_link = $release['download_link'];
  if (strpos($release['download_link'], '-dev') !== FALSE) {
    $download_link .= '?date=' . $release['date'];
  }
  // Cache for a year by default.
  $cache_duration = (drush_get_option('cache', TRUE)) ? 86400*365 : 0;

  // Prepare download path. On Windows file name cannot contain '?'.
  // See http://drupal.org/node/1782444
  $filename = str_replace('?', '_', basename($download_link));
  $download_path = drush_tempdir() . '/' . $filename;

  // Download the tarball.
  $download_path = drush_download_file($download_link, $download_path, $cache_duration);
  if ($download_path || drush_get_context('DRUSH_SIMULATE')) {
    drush_log(dt('Downloading !filename was successful.', array('!filename' => $filename)));
  }
  else {
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Unable to download !project to !path from !url.', array('!project' => $request['name'], '!path' => $download_path, '!url' => $download_link)));
  }

  // Check Md5 hash.
  if (!drush_get_option('no-md5')) {
    if (drush_op('md5_file', $download_path) !== $release['mdhash'] && !drush_get_context('DRUSH_SIMULATE')) {
      drush_delete_dir(drush_download_file_name($download_link, TRUE));
      return drush_set_error('DRUSH_PM_FILE_CORRUPT', dt('File !filename is corrupt (wrong md5 checksum).', array('!filename' => $filename)));
    }
    else {
      drush_log(dt('Md5 checksum of !filename verified.', array('!filename' => $filename)));
    }
  }

  // Extract the tarball in place and return the full path to the untarred directory.
  $download_base = dirname($download_path);
  if (!$tar_file_list = drush_tarball_extract($download_path, $download_base, TRUE)) {
    // An error has been logged.
    return FALSE;
  }
  $tar_directory = drush_trim_path($tar_file_list[0]);

  return $download_base . '/' . $tar_directory;
}

/**
 * Update a project.
 *
 * @return bool
 *   Success or failure. An error message will be logged.
 */
function package_handler_update_project(&$request, $release) {
  $download_path = package_handler_download_project($request, $release);
  if ($download_path) {
    return drush_move_dir($download_path, $request['full_project_path']);
  }
  else {
    return FALSE;
  }
}

/**
 * Post download action.
 *
 * This action take place once the project is placed in its final location.
 */
function package_handler_post_download($project) {
}
