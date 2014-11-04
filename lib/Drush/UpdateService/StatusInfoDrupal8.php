<?php

/**
 * @file
 * Implementation of 'drupal' update_status engine for Drupal 8.
 */

namespace Drush\UpdateService;

class StatusInfoDrupal8 implements StatusInfoInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($type, $engine, $config) {
    $this->engine_type = $type;
    $this->engine = $engine;
    $this->engine_config = $config;
  }

  /**
   * {@inheritdoc}
   */
  function lastCheck() {
    $last_check = \Drupal::state()->get('update.last_check') ?: 0;
    return $last_check;
  }

  /**
   * {@inheritdoc}
   */
  function refresh() {
    update_refresh();
  }

  /**
   * Get update information for all installed projects.
   *
   * @return
   *   Array of update status information.
   */
  function getStatus($projects) {
    $available = $this->getAvailableReleases();
    $update_info = $this->calculateUpdateStatus($available, $projects);
    return $update_info;
  }

  /**
   * Obtains release info for all installed projects via update.module.
   *
   * @see update_get_available().
   * @see \Drupal\update\Controller\UpdateController::updateStatusManually()
   */
  protected function getAvailableReleases() {
    // Force to invalidate some caches that are only cleared
    // when visiting update status report page. This allow to detect changes in
    // .info.yml files.
    \Drupal::keyValueExpirable('update')->deleteMultiple(array('update_project_projects', 'update_project_data'));

    // From update_get_available(): Iterate all projects and create a fetch task
    // for those we have no information or is obsolete.
    $available = \Drupal::keyValueExpirable('update_available_releases')->getAll();
    $update_projects = \Drupal::service('update.manager')->getProjects();
    foreach ($update_projects as $key => $project) {
      if (empty($available[$key])) {
        \Drupal::service('update.processor')->createFetchTask($project);
        continue;
      }
      if ($project['info']['_info_file_ctime'] > $available[$key]['last_fetch']) {
        $available[$key]['fetch_status'] = UPDATE_FETCH_PENDING;
      }
      if (empty($available[$key]['releases'])) {
        $available[$key]['fetch_status'] = UPDATE_FETCH_PENDING;
      }
      if (!empty($available[$key]['fetch_status']) && $available[$key]['fetch_status'] == UPDATE_FETCH_PENDING) {
        \Drupal::service('update.processor')->createFetchTask($project);
      }
    }

    // Set a batch to process all pending tasks.
    $batch = array(
      'operations' => array(
        array(array(\Drupal::service('update.manager'), 'fetchDataBatch'), array()),
      ),
      'finished' => 'update_fetch_data_finished',
      'file' => drupal_get_path('module', 'update') . '/update.fetch.inc',
    );
    batch_set($batch);
    drush_backend_batch_process();

    // Clear any error set by a failed update fetch task. This avoid rollbacks.
    drush_clear_error();

    return \Drupal::keyValueExpirable('update_available_releases')->getAll();
  }

  /**
   * Calculates update status for all projects via update.module.
   */
  protected function calculateUpdateStatus($available, $projects) {
    module_load_include('inc', 'update', 'update.compare');
    $data = update_calculate_project_data($available);

    foreach ($data as $project_name => $project) {
      // Discard custom projects.
      if ($project['status'] == UPDATE_UNKNOWN) {
        unset($data[$project_name]);
        continue;
      }
      // Discard projects with unknown installation path.
      if ($project_name != 'drupal' && !isset($projects[$project_name]['path'])) {
        unset($data[$project_name]);
        continue;
      }

      // Allow to update disabled projects.
      $data[$project_name]['project_type'] = $this->adjustProjectType($project);

      // Add some info from the project to $data.
      $data[$project_name] += array(
        'path'  => isset($projects[$project_name]['path']) ? $projects[$project_name]['path'] : '',
        'label' => $projects[$project_name]['label'],
      );
      // Store all releases, not just the ones selected by update.module.
      // We use it to allow the user to update to a specific version.
      if (isset($available[$project_name]['releases'])) {
        $data[$project_name]['releases'] = $available[$project_name]['releases'];
      }
    }

    return $data;
  }

  /**
   * Adjust project type for disabled projects.
   *
   * update.module sets a different project type
   * for disabled projects. Here we normalize it.
   */
  protected function adjustProjectType($project) {
    if (in_array($project['project_type'], array('module-disabled', 'theme-disabled'))) {
      return substr($project['project_type'], 0, strpos($project['project_type'], '-'));
    }
    return $project['project_type'];
  }
}

