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
    if (drush_get_option('no-backup', FALSE)) {
      return;
    }
    if ($backup_target = $this->prepare_backup_dir()) {
      if ($project['project_type'] != 'core') {
        $backup_target .= '/' . $project['project_type'] . 's';
        drush_mkdir($backup_target);
      }
      $backup_target .= '/'. $project['name'];
      // Save for rollback or notifications.
      $project['backup_target'] = $backup_target;

      // Move or copy to backup target based in package-handler.
      if (drush_get_option('package-handler', 'wget') == 'wget') {
        if (drush_move_dir($project['full_project_path'], $backup_target)) {
          return TRUE;
        }
      }
      // cvs or git.
      elseif (drush_copy_dir($project['full_project_path'], $backup_target)) {
        return TRUE;
      }
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Failed to backup project directory !project to !backup_target', array('!project' => $project['full_project_path'], '!backup_target' => $backup_target)));
    }
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($project) {
    if (drush_get_option('no-backup', FALSE)) {
      return;
    }
    if (drush_move_dir($project['backup_target'], $project['full_project_path'])) {
      return drush_log(dt("Backups were restored successfully."), 'ok');
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
  public function prepare_backup_dir($date_prefix = '') {  
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

    // Save the date to be used in the backup directory's path name.
    $date = gmdate('YmdHis', $_SERVER['REQUEST_TIME']);

    $backup_dir = drush_get_option('backup-dir', drush_server_home() . '/' . '.drush-backups');
    if (strpos($backup_dir, $drupal_root) === 0) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('It\'s not allowed to store backups inside the Drupal root directory.'));
    }
    if (!file_exists($backup_dir)) {
      if (!drush_mkdir($backup_dir)) {
        return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Unable to create backup directory !dir.', array('!dir' => $backup_dir)));
      }
    }
    if (!is_writable($backup_dir)) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Backup directory !dir is not writable.', array('!dir' => $backup_dir)));
    }

    $backup_dir = rtrim($backup_dir, DIRECTORY_SEPARATOR) . '/' . $date_prefix . $date;
    drush_mkdir($backup_dir);
    return $backup_dir;
  }

  public static function reserved_files() {
    return array();
  }
}
