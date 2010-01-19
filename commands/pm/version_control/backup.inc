<?php
// $Id$

/**
 * @file Drush pm directory copy backup extension
 */

class drush_pm_version_control_backup implements drush_pm_version_control {

  /**
   * Implementation of pre_update().
   */
  public function pre_update(&$release) {
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

    // Save the date to be used in the backup directory's path name.
    $date = date('YmdHis', $_SERVER['REQUEST_TIME']);

    $backup_dir = drush_get_option('backup-dir', $drupal_root  . '/backup');
    $backup_dir = rtrim($backup_dir, '/');
    @drush_op('mkdir', $backup_dir, 0777);
    $backup_dir .= '/modules';
    @drush_op('mkdir', $backup_dir, 0777);
    $backup_dir .= "/$date";
    @drush_op('mkdir', $backup_dir, 0777);
    $backup_target = $backup_dir . '/'. $release['name'];
    // Save for rollback or notifications.
    $release['backup_target'] = $backup_target;
    if (!drush_op('rename', $release['full_project_path'], $backup_target)) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Failed to backup project directory !project to !backup_target', array('!project' => $release['full_project_path'], '!backup_target' => $backup_target)));
    }
    return TRUE;
  }

  /**
   * Implementation of rollback().
   */
  public function rollback($release) {
    if (drush_op('rename', $release['backup_target'], $release['full_project_path']) && is_dir($release['full_project_path'])) {
      return drush_log(dt("Backups were restored successfully."), 'ok');
    }
    return drush_set_error('DRUSH_PM_BACKUP_ROLLBACK_FAILED', dt('Could not restore backup and rollback from failed upgrade. You will need to resolve manually.'));
  }

  /**
   * Implementation of post_update().
   */
  public function post_update($release) {
    if ($release['backup_target']) {
      drush_log(dt("Backups were saved into the directory !backup_target.", array('!backup_target' => $release['backup_target'])), 'ok');
    }
  }

  /**
   * Implementation of post_download().
   */
  public function post_download($release) {
   // NOOP
  }
}
