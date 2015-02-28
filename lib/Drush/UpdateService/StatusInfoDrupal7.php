<?php

/**
 * @file
 * Implementation of 'drupal' update_status engine for Drupal 7.
 */

namespace Drush\UpdateService;

class StatusInfoDrupal7 extends StatusInfoDrupal8 {

  /**
   * {@inheritdoc}
   */
  function lastCheck() {
    return variable_get('update_last_check', 0);
  }

  /**
   * {@inheritdoc}
   */
  function beforeGetStatus(&$projects, $check_disabled) {
    // If check-disabled option was provided, alter Drupal settings temporarily.
    // There's no other way to hook into this.
    if (!is_null($check_disabled)) {
      global $conf;
      $this->update_check_disabled = $conf['update_check_disabled'];
      $conf['update_check_disabled'] = $check_disabled;
    }
  }

  /**
   * {@inheritdoc}
   */
  function afterGetStatus(&$update_info, $projects, $check_disabled) {
    // Restore Drupal settings.
    if (!is_null($check_disabled)) {
      global $conf;
      $conf['update_check_disabled'] = $this->update_check_disabled;
      unset($this->update_check_disabled);
    }

    // update.module sets a different project type
    // for disabled projects. Here we normalize it.
    if ($check_disabled) {
      foreach ($update_info as $key => $project) {
        if (in_array($project['project_type'], array('module-disabled', 'theme-disabled'))) {
          $update_info[$key]['project_type'] = substr($project['project_type'], 0, strpos($project['project_type'], '-'));
        }
      }
    }
  }

  /**
   * Obtains release info for all installed projects via update.module.
   *
   * @see update_get_available().
   * @see update_manual_status().
   */
  protected function getAvailableReleases() {
    // Force to invalidate some caches that are only cleared
    // when visiting update status report page. This allow to detect changes in
    // .info files.
    _update_cache_clear('update_project_data');
    _update_cache_clear('update_project_projects');

    // From update_get_available(): Iterate all projects and create a fetch task
    // for those we have no information or is obsolete.
    $available = _update_get_cached_available_releases();

    module_load_include('inc', 'update', 'update.compare');
    $update_projects = update_get_projects();

    foreach ($update_projects as $key => $project) {
      if (empty($available[$key])) {
        update_create_fetch_task($project);
        continue;
      }
      if ($project['info']['_info_file_ctime'] > $available[$key]['last_fetch']) {
        $available[$key]['fetch_status'] = UPDATE_FETCH_PENDING;
      }
      if (empty($available[$key]['releases'])) {
        $available[$key]['fetch_status'] = UPDATE_FETCH_PENDING;
      }
      if (!empty($available[$key]['fetch_status']) && $available[$key]['fetch_status'] == UPDATE_FETCH_PENDING) {
        update_create_fetch_task($project);
      }
    }

    // Set a batch to process all pending tasks.
    $batch = array(
      'operations' => array(
        array('update_fetch_data_batch', array()),
      ),
      'finished' => 'update_fetch_data_finished',
      'file' => drupal_get_path('module', 'update') . '/update.fetch.inc',
    );
    batch_set($batch);
    drush_backend_batch_process();

    // Clear any error set by a failed update fetch task. This avoid rollbacks.
    drush_clear_error();

    // Calculate update status data.
    $available = _update_get_cached_available_releases();
    return $available;
  }
}

