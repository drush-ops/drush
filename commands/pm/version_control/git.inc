<?php
// $Id$

/**
 * @file Drush pm Git extension
 */

class drush_pm_version_control_git implements drush_pm_version_control {

  /**
   * Implementation of pre_update().
   *
   * Check that the project or drupal core directory looks clean
   */
  public function pre_update(&$project, $items_to_test = array()) {
    // If items to test is empty, test everything; otherwise, pass just
    // the list of files to test to git status.
    $status_files = implode(' ', array_keys($items_to_test));

    // 2 step verification. See http://stackoverflow.com/questions/2657935/checking-for-a-dirty-index-or-untracked-files-with-git#2659808.
    if (drush_shell_cd_and_exec($project['full_project_path'], 'u="$(git ls-files --others)" && test -z "$u"', $status_files)) {
      if (!drush_shell_cd_and_exec($project['full_project_path'], 'git diff-index --quiet HEAD', $status_files)) {
        return drush_set_error('DRUSH_PM_GIT_LOCAL_CHANGES', dt("The Git working copy at !path appears to have uncommitted changes (see below). Please commit or revert these changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", drush_shell_exec_output()))));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_GIT_LOCAL_CHANGES', dt("The Git working copy at !path appears to have uncommitted changes (see below). Please commit or revert these changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", drush_shell_exec_output()))));
    }
    return TRUE;
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    if (drush_shell_exec('git checkout %s', $project['full_project_path'])) {
      $output = drush_shell_exec_output();
      if (!empty($output)) {
        return drush_set_error('DRUSH_PM_GIT_LOCAL_CHANGES', dt("The Git working copy at !path appears to have uncommitted changes (see below). Please commit or revert these changes before continuing:\n!output", array('!path' => $project['full_project_path'], '!output' => implode("\n", $output))));
      }
    }
    else {
      return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the Git status on !path. Check that you have Git \ninstalled and that this directory is a Git working copy.\nThe specific errors are below:\n!errors", array('!path' => $project['full_project_path'], '!errors' => implode("\n", drush_shell_exec_output()))));
    }
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
   * that have been deleted on the file system
   */
  private function sync($project) {
    if (drush_get_option('gitsync')) {
      // if (drush_shell_cd_exec($project['full_project_path'], 'git rev-parse')) {
        // Add new, modified, and deleted files to the index.
        if (drush_shell_exec('git add --all %s', $project['full_project_path'])) {
          drush_log(dt('!path files staged to Git successfully', array('!path' => $project['full_project_path'])), 'ok');
        }
        else {
          return drush_set_error('DRUSH_PM_GIT_NOT_FOUND', dt("Drush was unable to get the add --all. Check that you have Git \ninstalled and that the site is a Git working copy.\nThe specific errors are below:\n!errors", array('!errors' => implode("\n", drush_shell_exec_output()))));
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
        $message = dt("Drush automatic commit.\nProject: @name @type\nCommand: @arguments", array('@name' => $project['name'], '@type' => $project['project_type'], '@arguments' => implode(' ', $_SERVER['argv'])));
      }
      if (drush_shell_exec('git commit --message=%s %s', $message, $project['full_project_path'])) {
        drush_log(dt('Project !name committed to Git successfully', array('!name' => $project['name'])), 'ok');
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
    return array('.git', '.gitignore');
  }
}
