<?php
// $Id$

/**
 * @file Drush PM Wget extension
 */

/**
 * Install a project.
 *
 * @param $project The project array with name, base and full (final) paths.
 * @param $release The release details array from drupal.org
 */
function package_handler_install_project(&$project, $release) {
  drush_log('Downloading project ' . $project['name'] . ' ...');

  // Get the filename...
  $filename = explode('/', $release['download_link']);
  $filename = array_pop($filename);

  // Set our directory to the download location.
  $olddir = getcwd();
  chdir($project['base_project_path']);

  // Download it.
  if (!drush_shell_exec("wget " . $release['download_link'])) {
    drush_shell_exec("curl -O " . $release['download_link']);
  }

  if (file_exists($filename) || drush_get_context('DRUSH_SIMULATE')) {
    drush_log("Downloading " . $filename . " was successful.");
  }
  else {
    chdir($olddir);
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', 'Unable to download ' . $filename . ' to ' . $project['base_project_path'] . ' from '. $release['download_link']);
  }

  // Check Md5 hash
  if (md5_file($filename) != $release['mdhash'] && !drush_get_context('DRUSH_SIMULATE')) {
    drush_op('unlink', $filename);
    chdir($olddir);
    return drush_set_error('DRUSH_PM_FILE_CORRUPT', "File $filename is corrupt (wrong md5 checksum).");
  }
  else {
    drush_log("Md5 checksum of $filename verified.");
  }

  // Decompress
  drush_shell_exec("gzip -d " . $filename);
  $tarpath = basename($filename, '.tar.gz');
  $tarpath = basename($tarpath, '.tgz');
  $tarpath .= '.tar';

  // Untar
  drush_shell_exec("tar -xf $tarpath");
  // We're not using tar -xzf because that's not working on windows...

  // Handle the dodgy directory used by the drupal project' packaging script.
  if ($project['name'] == 'drupal') {
    // The dodgy directory name is always the first line of output.
    drush_shell_exec("tar -tf $tarpath");
    $output = drush_shell_exec_output();
    $project_destination_name = $output[0];

    if ($rename = drush_get_option($project['name'] . '-project-rename')) {
      // If someone specifies --drupal-project-rename, but does not
      // provide a name, then default to 'drupal'.
      if ($rename === TRUE) {
        $rename = $project['name'];
      }
      $rename = trim($rename, '/');
      $renamed_destination = $project['base_project_path'] . (substr($project['base_project_path'], -1) != '/' ? '/' : '') . $rename;

      $rename_result = drush_op('rename', $project['base_project_path'] . $project_destination_name, $renamed_destination);
      if ($rename_result) {
        $project_destination_name = $rename;
      }
    }
    $project['full_project_path'] = $project['base_project_path'] . (substr($project['base_project_path'], -1) != '/' ? '/' : '') . $project_destination_name;
  }

  // Remove the tarball
  drush_op('unlink', $tarpath);

  // Set working directory back to the previous working directory.
  chdir($olddir);

  // 'drupal' project and profiles get untarred as drupal-6-10/foo instead of drupal/foo so must be excluded from test below.
  // Translations get untarred into the Drupal root, so there is no easy way to check.
  if (!is_dir($project['full_project_path']) && !drush_get_context('DRUSH_SIMULATE') && $project['name'] != 'drupal' && !in_array($release['type'], array('translation', 'profile'))) {
    return drush_set_error('DRUSH_PM_FILE_UNTAR_ERROR', 'Downloaded file ' . $filename . ' couldn\'t be untarred to ' . $project['full_project_path'] . ' correctly');
  }
  else {
    return TRUE;
  }
}

/**
 * This is an alias of the install function, since they are identical
 */
function package_handler_update_project(&$project, $release) {
  return package_handler_install_project($project, $release);
}
