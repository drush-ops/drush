<?php

/**
 * @file
 * Drush release info engine for update.drupal.org and compatible services.
 *
 * This engine does connect directly to the update service. It doesn't depend
 * on a bootstrapped site.
 */

namespace Drush\UpdateService;

use Drush\Log\LogLevel;

/**
 * Release info engine class.
 */
class ReleaseInfo {
  const DEFAULT_URL = 'https://updates.drupal.org/release-history';

  // Cache release xml files for 24h by default.
  const CACHE_LIFETIME = 86400;

  private $cache;
  private $engine_config;

  /**
   * Constructor.
   */
  public function __construct($type, $engine, $config) {
    $this->engine_type = $type;
    $this->engine = $engine;

    if (is_null($config)) {
      $config = array();
    }
    $config += array(
      'cache-duration' => drush_get_option('cache-duration-releasexml', self::CACHE_LIFETIME),
    );
    $this->engine_config = $config;
    $this->cache = array();
  }

  /**
   * Returns configured cache duration.
   */
  public function getCacheDuration() {
    return $this->engine_config['cache-duration'];
  }

  /**
   * Returns a project's release info from the update service.
   *
   * @param array $request
   *   A request array.
   *
   * @param bool $refresh
   *   Whether to discard cached object.
   *
   * @return \Drush\UpdateService\Project
   */
  public function get($request, $refresh = FALSE) {
    if ($refresh || !isset($this->cache[$request['name']])) {
      $project_release_info = Project::getInstance($request, $this->getCacheDuration());
      if ($project_release_info && !$project_release_info->isValid()) {
        $project_release_info = FALSE;
      }
      $this->cache[$request['name']] = $project_release_info;
    }
    return $this->cache[$request['name']];
  }

  /**
   * Delete cached update service file of a project.
   *
   * @param array $request
   *   A request array.
   */
  public function clearCached(array $request) {
    if (isset($this->cache[$request['name']])) {
      unset($this->cache[$request['name']]);
    }
    $url = Project::buildFetchUrl($request);
    $cache_file = drush_download_file_name($url);
    if (file_exists($cache_file)) {
      unlink($cache_file);
    }
  }

  /**
   * Select the most appropriate release for a project, based on a strategy.
   *
   * @param Array &$request
   *   A request array.
   *   The array will be expanded with the project type.
   * @param String $restrict_to
   *   One of:
   *     'dev': Forces choosing a -dev release.
   *     'version': Forces choosing a point release.
   *     '': No restriction.
   *   Default is ''.
   * @param String $select
   *   Strategy for selecting a release, should be one of:
   *    - auto: Try to select the latest release, if none found allow the user
   *            to choose.
   *    - always: Force the user to choose a release.
   *    - never: Try to select the latest release, if none found then fail.
   *    - ignore: Ignore and return NULL.
   *   If no supported release is found, allow to ask the user to choose one.
   * @param Boolean $all
   *   In case $select = TRUE this indicates that all available releases will be
   *  offered the user to choose.
   *
   * @return array
   *  The selected release.
   */
  public function selectReleaseBasedOnStrategy($request, $restrict_to = '', $select = 'never', $all = FALSE, $version = NULL) {
    if (!in_array($select, array('auto', 'never', 'always', 'ignore'))) {
      return drush_set_error('DRUSH_PM_UNKNOWN_SELECT_STRATEGY', dt("Error: select strategy must be one of: auto, never, always, ignore", array()));
    }

    $project_release_info = $this->get($request);
    if (!$project_release_info) {
      return FALSE;
    }

    if ($select != 'always') {
      if (isset($request['version'])) {
        $release = $project_release_info->getSpecificRelease($request['version']);
        if ($release === FALSE) {
          return drush_set_error('DRUSH_PM_COULD_NOT_FIND_VERSION', dt("Could not locate !project version !version.", array(
            '!project' => $request['name'],
            '!version' => $request['version'],
          )));
        }
      }
      if ($restrict_to == 'dev') {
        // If you specified a specific release AND --dev, that is either
        // redundant (okay), or contradictory (error).
        if (!empty($release)) {
          if ($release['version_extra'] != 'dev') {
            return drush_set_error('DRUSH_PM_COULD_NOT_FIND_VERSION', dt("You requested both --dev and !project version !version, which is not a '-dev' release.", array(
              '!project' => $request['name'],
              '!version' => $request['version'],
           )));
         }
        }
        else {
          $release = $project_release_info->getDevRelease();
          if ($release === FALSE) {
            return drush_set_error('DRUSH_PM_NO_DEV_RELEASE', dt('There is no development release for project !project.', array('!project' => $request['name'])));
          }
        }
      }
      // If there was no specific release requested, try to identify the most appropriate release.
      if (empty($release)) {
        $release = $project_release_info->getRecommendedOrSupportedRelease();
      }
      if ($release) {
        return $release;
      }
      else {
        $message = dt('There are no stable releases for project !project.', array('!project' => $request['name']));
        if ($select == 'never') {
          return drush_set_error('DRUSH_PM_NO_STABLE_RELEASE', $message);
        }
        drush_log($message, LogLevel::WARNING);
        if ($select == 'ignore') {
          return NULL;
        }
      }
    }

    // At this point the only chance is to ask the user to choose a release.
    if ($restrict_to == 'dev') {
      $filter = 'dev';
    }
    elseif ($all) {
      $filter = 'all';
    }
    else {
      $filter = '';
    }
    $releases = $project_release_info->filterReleases($filter, $version);

    // Special checking: Drupal 6 is EOL, so there are no stable
    // releases for ANY contrib project. In this case, we'll default
    // to the best release, unless the user specified --select.
    $version_major = drush_drupal_major_version();
    if (($select != 'always') && ($version_major < 7)) {
      $bestRelease = Project::getBestRelease($releases);
      if (!empty($bestRelease)) {
        $message = dt('Drupal !major has reached EOL, so there are no stable releases for any contrib projects. Selected the best release, !project.', array('!major' => $version_major, '!project' => $bestRelease['name']));
        drush_log($message, LogLevel::WARNING);
        return $bestRelease;
      }
    }

    $options = array();
    foreach($releases as $release) {
      $options[$release['version']] = array($release['version'], '-', gmdate('Y-M-d', $release['date']), '-', implode(', ', $release['release_status']));
    }
    $choice = drush_choice($options, dt('Choose one of the available releases for !project:', array('!project' => $request['name'])));
    if (!$choice) {
      return drush_user_abort();
    }

    return $releases[$choice];
  }

  /**
   * Check if a project is available in the update service.
   *
   * Optionally check for consistency by comparing given project type and
   * the type obtained from the update service.
   *
   * @param array $request
   *   A request array.
   * @param string $type
   *   Optional. If provided, will do a consistent check of the project type.
   *
   * @return boolean
   *   True if the project exists and type matches.
   */
  public function checkProject($request, $type = NULL) {
    $project_release_info = $this->get($request);
    if (!$project_release_info) {
      return FALSE;
    }
    if ($type) {
      if ($project_release_info->getType() != $type) {
        return FALSE;
      }
    }

    return TRUE;
  }
}
