<?php
// $Id$

/**
 * @file Drush pm SVN extension
 */

/**
 * Implementation of version_control_is_versioned().
 */
function version_control_is_versioned($source) {
  if (file_exists($source. '/.svn')) {
    // Skip backup since we need all .svn  directories. Assuming admin can use svn revert if new project is bad.
    drush_log(dt('Backup skipped because .svn directory was detected. Use Subversion to revert if needed.'));
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
 * Automatically add any unversioned files to Subversion and remove any files
 * that have been deleted on the file system
 */
function version_control_sync($project, $info, $path = '.') {
  if (drush_get_option('svnsync')) {
    $errors = '';
    if (drush_shell_exec('svn status '. drush_get_option('svnstatusparams') .' '. $path.$project)) {
      $output = drush_shell_exec_output();
      foreach ($output as $line) {
        if (preg_match('/^\? *(.*)/', $line, $matches)) {
          if (!drush_shell_exec('svn add '. drush_get_option('svnaddparams') .' '. $matches[1])) {
            $errors .= implode("\n", drush_shell_exec_output());
          }
        }
        if (preg_match('/^\! *(.*)/', $line, $matches)) {
          if (!drush_shell_exec('svn remove '. drush_get_option('svnremoveparams') .' '. $matches[1])) {
            $errors .= implode("\n", drush_shell_exec_output());
          }
        }
      }
      if (!empty($errors)) {
        return drush_set_error('DRUSH_PM_SVN_SYNC_PROBLEMS', dt("Problems were encountered adding or removing files to/from subversion.\nThe specific errors are below:\n!errors", array('!errors' => $errors)));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_SVN_NOT_FOUND', dt("Drush was unable to get the svn status. Check that you have Subversion \ninstalled and that the site is a subversion working copy.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
    }
    return TRUE;
  }
}

/**
 * Automatically commit changes to the repository
 */
function version_control_commit($project, $info, $path = '.') {
  if (drush_get_option('svncommit')) {
    $message = drush_get_option('svnmessage');
    if (empty($message)) {
      $message = dt("Drush automatic commit: \n") . implode(' ', $_SERVER['argv']);
    }
    if (drush_shell_exec('svn commit '. drush_get_option('svncommitparams') .' -m "'. $message .'" '. $path.$project)) {
      drush_log(dt('Project committed to Subversion successfully'), 'ok');
    }
    else {
      drush_set_error('DRUSH_PM_SVN_COMMIT_PROBLEMS', dt("Problems were encountered committing your changes to Subversion.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
    }
  }
  else {
    drush_print(dt("You should consider committing the new code to your Subversion repository.\nIf this version becomes undesireable, use Subversion to roll back."));
  }
}
