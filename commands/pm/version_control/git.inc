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

    // No-op. Nothing to do here.
    return TRUE;
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    // TODO, do we need this at all?
    // Other VCS implementations do a revert ... but can we trust that a commit was made?
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
    if (drush_get_option('gitsync') || drush_get_option('gitcommit')) {
      $errors = '';
      if (!drush_shell_exec('git add --all '. drush_get_option('gitaddparams') .' '. $project['full_project_path'])) {
        $errors .= implode("\n", drush_shell_exec_output());
      }
      if (!empty($errors)) {
        return drush_set_error('DRUSH_PM_GIT_SYNC_PROBLEMS', dt("Problems were encountered adding or removing files to/from this GIT working copy.\nThe specific errors are below:\n!errors", array('!errors' => $errors)));
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
      if (drush_shell_exec('git commit '. drush_get_option('gitcommitparams') .' -m '. escapeshellarg($message))) {
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
    // .gitignore is not in this list as it can be safely committed to a project.
    return array('.git');
  }
}
