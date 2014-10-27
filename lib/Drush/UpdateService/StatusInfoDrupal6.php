<?php

/**
 * @file
 * Implementation of 'drupal' update_status engine for Drupal 6.
 */

namespace Drush\UpdateService;

class StatusInfoDrupal6 extends StatusInfoDrupal7 {

  /**
   * Obtains release info for all installed projects via update.module.
   *
   * @see update_get_available().
   * @see update_manual_status().
   */
  protected function getAvailableReleases() {
    // We force a refresh if the cache is not available.
    if (!cache_get('update_available_releases', 'cache_update')) {
      $this->refresh();
    }

    $available = update_get_available(TRUE);

    // Force to invalidate some update_status caches that are only cleared
    // when visiting update status report page.
    if (function_exists('_update_cache_clear')) {
      _update_cache_clear('update_project_data');
      _update_cache_clear('update_project_projects');
    }

    return $available;
  }

  /**
   * {@inheritdoc}
   */
  protected function adjustProjectType($project) {
    if (in_array($project['project_type'], array('disabled-module', 'disabled-theme'))) {
      $data[$project_name]['project_type'] = substr($project['project_type'], strpos($project['project_type'], '-') + 1);
    }
    return $project['project_type'];
  }
}

