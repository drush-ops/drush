<?php
// $Id$

/**
 * @file Drush pm directory copy backup extension
 */

class drush_pm_version_control_backup implements drush_pm_version_control {

  /**
   * Implementation of pre_update().
   */
  public function pre_update(&$project, $items_to_test = array()) {
    if ($backup_dir = $this->prepare_back_dir()) {
      $backup_target = $backup_dir . '/' . $project['name'];
      // Save for rollback or notifications.
      $project['backup_target'] = $backup_target;
      if (!drush_move_dir($project['full_project_path'], $backup_target)) {
        return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Failed to backup project directory !project to !backup_target', array('!project' => $project['full_project_path'], '!backup_target' => $backup_target)));
      }
      return TRUE;  
    }
    else {
      return FALSE;
    }
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    if (drush_op('rename', $project['backup_target'], $project['full_project_path']) && is_dir($project['full_project_path'])) {
      return drush_log(dt("Backups were restored successfully."), 'ok');
    }
    return drush_set_error('DRUSH_PM_BACKUP_ROLLBACK_FAILED', dt('Could not restore backup and rollback from failed upgrade. You will need to resolve manually.'));
  }

  /**
   * Implementation of post_update().
   */
  public function post_update($project) {
    if ($project['backup_target']) {
      drush_log(dt("Backups were saved into the directory !backup_target.", array('!backup_target' => $project['backup_target'])), 'ok');
    }
  }

  /**
   * Implementation of post_download().
   */
  public function post_download($project) {
   // NOOP
  }
  
  // Helper for pre_update.
  public function prepare_backup_dir() {  
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

    // Save the date to be used in the backup directory's path name.
    $date = date('YmdHis', $_SERVER['REQUEST_TIME']);

    $backup_dir = drush_get_option('backup-dir', $_SERVER['HOME'] . '/' . '.drush-backups');
    if (strpos($backup_dir, $drupal_root) === 0) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('It\'s not allowed to store backups inside the Drupal root directory.'));
    }
    $backup_dir = rtrim($backup_dir, DIRECTORY_SEPARATOR);
    
    if (drush_mkdir($backup_dir)) {
      return $backup_dir .= '/' . $date . '/' . 'modules';
    }
  }
}
