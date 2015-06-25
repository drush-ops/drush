<?php

/**
 * Implements hook_pm_post_update().
 *
 * Restore sqlsrv driver after core update.
 */
function pm_update_pm_post_update($project_name, $installed_release, $project) {
  // Restore sqlsrv database driver.
  if ($project_name == 'drupal') {
    $sqlsrv_dir = 'includes/database/sqlsrv';
    if (file_exists($project['backup_target'] . '/' . $sqlsrv_dir)) {
      if (drush_copy_dir($project['backup_target'] . '/' . $sqlsrv_dir, $project['full_project_path'] . '/' . $sqlsrv_dir)) {
        drush_log('SQLSRV database driver has been restored.', 'info');
      }
    }
  }
}

