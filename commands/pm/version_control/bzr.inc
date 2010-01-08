<?php
// $Id$

/**
 * @file Drush pm BZR extension
 */

/**
 * Helper function: get the root of a repository path.
 *
 * @param $source
 *   The source path.
 * @return
 *   An absolute path to the repository root.
 */
function bzr_get_repository_root($source) {
  if (drush_shell_exec('bzr root %s', $source)) {
    $output = drush_shell_exec_output();
    return reset($output);
  }
}

/**
 * Implementation of version_control_is_versioned().
 */
function version_control_is_versioned($source) {
  if (bzr_get_repository_root($source)) {
    // Skip backup since we need all .bzr  directories. Assuming admin can use bzr revert if new project is bad.
    drush_log(dt('Backup skipped because .bzr directory was detected. Use Bzr to revert if needed.'));
    return TRUE;
  }
  return FALSE;
}

/**
 * Implementation of version_control_post_update().
 */
function version_control_post_update($project, $info, $path = '.') {
  if (version_control_is_versioned($path) && version_control_sync($project, $info, $path)) {
    // Only attempt commit on a sucessful sync
    version_control_commit($project, $info, $path);
  }
}

/**
 * Implementation of version_control_post_install().
 */
function version_control_post_install($project, $info, $path = '.') {
  if (version_control_is_versioned($path) && version_control_sync($project, $info, $path)) {
    // Only attempt commit on a sucessful sync
    version_control_commit($project, $info, $path);
  }
}

/**
 * Automatically add any unversioned files to Bzr and remove any files
 * that have been deleted on the file system
 */
function version_control_sync($project, $info, $path = '.') {
  if (drush_get_option('bzrsync')) {
    $errors = '';

    // All paths returned by bzr status are relative to the repository root.
    $root = bzr_get_repository_root($source);

    if (drush_shell_exec('bzr status -S %s', $path . $project)) {
      $output = drush_shell_exec_output();

      // Parse the output of bzr status to get the list of added and removed
      // files.
      $new_files = array();
      $removed_files = array();
      foreach ($output as $line) {
        if (preg_match('/^\? *(.*)/', $line, $matches)) {
          $new_files[] = $root . '/' . $matches[1];
        }
        if (preg_match('/^\ D *(.*)/', $line, $matches)) {
          $removed_files[] = $root . '/' . $matches[1];
        }
      }

      // Proceed adding new files.
      foreach (array_chunk($new_files, 100) as $chunk) {
        $chunk = array_map('escapeshellarg', $chunk);
        if (!drush_shell_exec('bzr add ' . implode(' ', $chunk))) {
          $errors .= implode("\n", drush_shell_exec_output());
        }
      }

      // Proceed removing old files.
      foreach (array_chunk($removed_files, 100) as $chunk) {
        $chunk = array_map('escapeshellarg', $chunk);
        if (!drush_shell_exec('bzr remove ' . implode(' ', $chunk))) {
          $errors .= implode("\n", drush_shell_exec_output());
        }
      }

      if (!empty($errors)) {
        return drush_set_error('DRUSH_PM_BZR_SYNC_PROBLEMS', dt("Problems were encountered adding or removing files to/from Bazaar.\nThe specific errors are below:\n!errors", array('!errors' => $errors)));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_BZR_NOT_FOUND', dt("Drush was unable to get the bzr status. Check that you have Bazaar \ninstalled and that the site is a Bazaar working copy.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
    }
    return TRUE;
  }
}

/**
 * Automatically commit changes to the repository
 */
function version_control_commit($project, $info, $path = '.') {
  if (drush_get_option('bzrcommit')) {
    $message = drush_get_option('bzrmessage');
    if (empty($message)) {
      $message = dt("Drush automatic commit: \n") . implode(' ', $_SERVER['argv']);
    }
    if (drush_shell_exec('bzr commit -m %s %s', $message, $path . $project)) {
      drush_log(dt('Project committed to Bazaar successfully'), 'ok');
    }
    else {
      drush_set_error('DRUSH_PM_BZR_COMMIT_PROBLEMS', dt("Problems were encountered committing your changes to Bazaar.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
    }
  }
  else {
    drush_print(dt("You should consider committing the new code to your Bazaar repository.\nIf this version becomes undesireable, use Bazaar to roll back."));
  }
}
