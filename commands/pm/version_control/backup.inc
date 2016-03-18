<?php

/**
 * @file
 * Drush pm directory copy backup extension
 */

use Drush\Log\LogLevel;

class drush_version_control_backup implements drush_version_control {

  /**
   * Implementation of pre_update().
   */
  public function pre_update(&$project, $items_to_test = array('' => 1)) {
    if (drush_get_option('no-backup', FALSE)) {
      return TRUE;
    }
    if ($backup_target = $this->prepare_backup_dir()) {
      if ($project['project_type'] != 'core') {
        $backup_target .= '/' . $project['project_type'] . 's';
      }
      $backup_target .= '/'. $project['name'];
      $backup_source = $project['full_project_path'];
      // Save for rollback or notifications.
      $project['backup_target'] = $backup_target;
      $ok = TRUE;

      foreach (array_keys($items_to_test) as $file) {
        drush_mkdir(dirname("$backup_target/$file"));
        // Move or copy to backup target based on package-handler.
        if (drush_get_option('package-handler', 'wget') == 'wget') {
          $ok = $ok && drush_move_dir("$backup_source/$file", "$backup_target/$file");
        }
        // git.
        else {
          $ok = $ok && drush_copy_dir("$backup_source/$file", "$backup_target/$file");
        }
      }

      if (!$ok) {
        return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Failed to backup project directory !project to !backup_target', array('!project' => $backup_source, '!backup_target' => $backup_target)));
      }
      return TRUE;
    }
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    if (drush_get_option('no-backup', FALSE)) {
      return;
    }
    if (drush_move_dir($project['backup_target'], $project['full_project_path'], TRUE)) {
      return drush_log(dt("Backups were restored successfully."), LogLevel::OK);
    }
    return drush_set_error('DRUSH_PM_BACKUP_ROLLBACK_FAILED', dt('Could not restore backup and rollback from failed upgrade. You will need to resolve manually.'));
  }

  /**
   * Implementation of post_update().
   */
  public function post_update($project) {
    if (drush_get_option('no-backup', FALSE)) {
      return;
    }
    if ($project['backup_target']) {
      drush_log(dt("Backups were saved into the directory !backup_target.", array('!backup_target' => $project['backup_target'])), LogLevel::OK);
    }
  }

  /**
   * Implementation of post_download().
   */
  public function post_download($project) {
   // NOOP
  }

  // Helper for pre_update.
  public function prepare_backup_dir($subdir = NULL) {
    return drush_prepare_backup_dir($subdir);
  }

  public static function reserved_files() {
    return array();
  }
}
