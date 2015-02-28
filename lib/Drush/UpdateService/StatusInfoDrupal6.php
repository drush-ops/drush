<?php

/**
 * @file
 * Implementation of 'drupal' update_status engine for Drupal 6.
 */

namespace Drush\UpdateService;

class StatusInfoDrupal6 extends StatusInfoDrupal7 {

  /**
   * {@inheritdoc}
   */
  function beforeGetStatus(&$projects, $check_disabled) {
    // If check-disabled option was provided, alter Drupal settings temporarily.
    // There's no other way to hook into this.
    if (!is_null($check_disabled)) {
      global $conf;
      $this->update_check_disabled = $conf['update_advanced_check_disabled'];
      $conf['update_advanced_check_disabled'] = $check_disabled;
    }
  }

  /**
   * {@inheritdoc}
   */
  function afterGetStatus(&$update_info, $projects, $check_disabled) {
    // Restore Drupal settings.
    if (!is_null($check_disabled)) {
      global $conf;
      $conf['update_advanced_check_disabled'] = $this->update_check_disabled;
      unset($this->update_check_disabled);
    }

    // update_advanced.module sets a different project type
    // for disabled projects. Here we normalize it.
    if ($check_disabled) {
      foreach ($update_info as $key => $project) {
        if (in_array($project['project_type'], array('disabled-module', 'disabled-theme'))) {
          $update_info[$key]['project_type'] = substr($project['project_type'], strpos($project['project_type'], '-') + 1);
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
}

