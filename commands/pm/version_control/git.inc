<?php

/**
 * @file
 * Drush pm GIT extension
 */

class drush_version_control_git implements drush_version_control {

  /**
   * Implementation of pre_update().
   */
  public function pre_update(&$project, $items_to_test = array()) {
/*
    // If items to test is empty, test everything; otherwise, pass just
    // the list of files to test to git status.
    $args = array_keys($items_to_test);
    array_unshift($args, 'git status '. drush_get_option('gitstatusparams') . str_repeat('%s ', count($args)));
    array_unshift($args, $project['full_project_path']);
    if (call_user_func_array('drush_shell_cd_and_exec', $args)) {
      $output = preg_grep('/^[ ACDMRX?!~][ CM][ L][ +][ SX][ K]/', drush_shell_exec_output());
      if (!empty($output)) {
        return drush_set_error('DRUSH_PM_GIT_LOCAL_CHANGES', dt("The GIT working copy at !path appears to have uncommitted changes (see below). Please commit or revert these changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", $output))));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the git status on !path.\nThe specific errors are below:\n!errors", array('!path' => $project['full_project_path'], '!errors' => implode("\n", drush_shell_exec_output()))));
    }
*/

#    // Check for incoming updates
#    $args = array_keys($items_to_test);
#    array_unshift($args, 'git status -u '. drush_get_option('gitstatusparams') . str_repeat('%s ', count($args)));
#    array_unshift($args, $project['full_project_path']);
#    if (call_user_func_array('drush_shell_cd_and_exec', $args)) {
#      $output = preg_grep('/\*/', drush_shell_exec_output());
#      if (!empty($output)) {
#        return drush_set_error('DRUSH_PM_GIT_REMOTE_CHANGES', dt("The GIT working copy at !path appears to be out of date with the repository (see below). Please run 'git update' to pull down changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", $output))));
#      }
#    }
#    else {
#      return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the git remote status on !path. Check that you have connectivity to the repository.\nThe specific errors are below:\n!errors", array('!path' => $project['full_project_path'], '!errors' => implode("\n", drush_shell_exec_output()))));
#  }
    return TRUE;
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    /*
    if (drush_shell_exec('git revert '. drush_get_option('gitrevertparams') .' '. $project['full_project_path'])) {
      $output = drush_shell_exec_output();
      if (!empty($output)) {
        return drush_set_error('DRUSH_PM_GIT_LOCAL_CHANGES', dt("The GIT working copy at !path appears to have uncommitted changes (see below). Please commit or revert these changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", $output))));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the git status on !path. Check that you have Git \ninstalled and that this directory is a subversion working copy.\nThe specific errors are below:\n!errors", array('!path' => $project['full_project_path'], '!errors' => implode("\n", drush_shell_exec_output()))));
    }
     */
  }

  /**
   * Implementation of post_update().
   */
  public function post_update($project) {
    if ($this->sync($project)) {
      // Only attempt commit on a sucessful sync
      $this->commit($project);
    }
  }

  /**
   * Implementation of post_download().
   */
  public function post_download($project) {
    if ($this->sync($project)) {
      // Only attempt commit on a sucessful sync
      $this->commit($project);
    }
  }

  /**
   * Automatically add any unversioned files to Git and remove any files
   * that have been deleted on the file system in the full_project_path.
   */
  private function sync($project) {
    if (drush_get_option('gitsync')) {
      $errors = '';
      if (drush_shell_exec('git status '. drush_get_option('gitstatusparams') .' '. $project['full_project_path'])) {
        $output = drush_shell_exec_output();
        foreach ($output as $line) {
          if (preg_match('/^\? *(.*)/', $line, $matches)) {
            if (!drush_shell_exec('git add '. drush_get_option('gitaddparams') .' '. $matches[1])) {
              $errors .= implode("\n", drush_shell_exec_output());
            }
          }
          if (preg_match('/^\! *(.*)/', $line, $matches)) {
            if (!drush_shell_exec('git remove '. drush_get_option('gitremoveparams') .' '. $matches[1])) {
              $errors .= implode("\n", drush_shell_exec_output());
            }
          }
        }
        if (!empty($errors)) {
          return drush_set_error('DRUSH_PM_GIT_SYNC_PROBLEMS', dt("Problems were encountered adding or removing files to/from this GIT working copy.\nThe specific errors are below:\n!errors", array('!errors' => $errors)));
        }
      }
      else {
        return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the git status on !path. Check that you have Git \ninstalled and that this directory is a subversion working copy.\nThe specific errors are below:\n!errors", array('!path' => $project['full_project_path'], '!errors' => implode("\n", drush_shell_exec_output()))));
      }
      return TRUE;
    }
  }

  /**
   * Automatically commit changes to the repository
   */
  private function commit($project) {
    if (drush_get_option('gitcommit')) {
      $message = drush_get_option('gitmessage');
      if (empty($message)) {
        $from = '';
        if (!empty($project['existing_version'])) {
          $from = "from $project[existing_version] ";
        }
        $version = !empty($project['version']) ? $project['version'] : $project['recommended'];
        $message = dt("Update $project[name] ${from}to $version");
      }
      if (drush_shell_exec('git commit '. drush_get_option('gitcommitparams') .' -m '. escapeshellarg($message) .' '. escapeshellarg($project['full_project_path']))) {
        drush_log(dt('Project committed to Git successfully'), 'ok');
      }
      else {
        drush_set_error('DRUSH_PM_GIT_COMMIT_PROBLEMS', dt("Problems were encountered committing your changes to Git.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
      }
    }
    else {
      drush_print(dt("You should consider committing the new code to your Git repository.\nIf this version becomes undesireable, use Git to roll back."));
    }
  }

  public static function reserved_files() {
    return array('.git');
  }
}
