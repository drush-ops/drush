<?php

/**
 * @file
 * Drush release info engine for update.drupal.org and compatible services.
 *
 * This engine does connect directly to the update service. It doesn't depend
 * on a bootstrapped site.
 */

define('RELEASE_INFO_DEFAULT_URL', 'http://updates.drupal.org/release-history');

/**
 * Release info engine class.
 */
class drush_release_info_updatexml {
  #TODO# define an interface or abstract class for engines.
  private $cache;
  private $engine_config;

  /**
   * Constructor.
   */
  public function __construct($config) {
    if (is_null($config)) {
      $config = array();
    }
    $config += array(
      'cache-duration' => drush_get_option('cache-duration-releasexml', 24*3600),
    );
    $this->engine_config = $config;
    $this->cache = array();
  }

  /**
   * Returns configured cache duration.
   *
   * Used by 'drush' update_info engine.
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
   * @return \UpdateServiceProject
   */
  public function get($request, $refresh = FALSE) {
    if ($refresh || !isset($this->cache[$request['name']])) {
      $project_release_info = UpdateServiceProject::getInstance($request, $this->getCacheDuration());
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
    $url = UpdateServiceProject::buildFetchUrl($request);
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
  public function selectReleaseBasedOnStrategy($request, $restrict_to = '', $select = 'never', $all = FALSE) {
    if (!in_array($select, array('auto', 'never', 'always', 'ignore'))) {
      return drush_set_error('DRUSH_PM_UNKNOWN_SELECT_STRATEGY', dt("Error: select strategy must be one of: auto, never, always, ignore", array()));
    }

    $project_release_info = $this->get($request);
    if (!$project_release_info) {
      return FALSE;
    }

    if ($select != 'always') {
      if ($restrict_to == 'dev') {
        $release = $project_release_info->getDevRelease();
        if ($release === FALSE) {
          return drush_set_error('DRUSN_PM_NO_DEV_RELEASE', dt('There is no development release for project !project.', array('!project' => $request['name'])));
        }
      }
      if (empty($release)) {
        $release = $project_release_info->getSpecificRelease($request['version']);
        if ($release === FALSE) {
          return drush_set_error('DRUSH_PM_COULD_NOT_FIND_VERSION', dt("Could not locate !project version !version.", array('!project' => $request['name'], '!version' => $request['version'])));
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
        drush_log($message, 'warning');
        if ($select == 'ignore') {
          return NULL;
        }
      }
    }

    $releases = $project_release_info->filterReleases($all, $restrict_to);
    $options = array();
    foreach($releases as $version => $release) {
      $options[$version] = array($version, '-', gmdate('Y-M-d', $release['date']), '-', implode(', ', $release['release_status']));
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

/**
 * Representation of a project's release info from the update service.
 */
class UpdateServiceProject {
  private $xml;
  private $parsed;


  /**
   * Constructor.
   *
   * @param string $project_name
   *    Project name.
   *
   * @param \SimpleXMLElement $xml
   *    XML data.
   */
  function __construct(\SimpleXMLElement $xml) {
    $this->xml = $xml;
    $this->parsed = self::parseXml($xml);
  }

  /**
   * Downloads release info xml from update service.
   *
   * @param array $request
   *   A request array.
   * @param int $cache_duration
   *   Cache lifetime.
   *
   * @return UpdateServiceProject
   */
  public static function getInstance(array $request, $cache_duration) {
    $url = self::buildFetchUrl($request);
    drush_log(dt('Downloading release history from !url', array('!url' => $url)));

    $path = drush_download_file($url, drush_tempnam($request['name']), $cache_duration);
    $xml = simplexml_load_file($path);
    if (!$xml) {
      return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Could not download project status information from !url', array('!url' => $url)));
    }
    // Validate xml.
    // #TODO# move to the constructor.
    if ($error = $xml->xpath('/error')) {
      // Don't set an error here since it stops processing during site-upgrade.
      drush_log($error[0], 'warning'); // 'DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE',
      return FALSE;
    }
    // Unpublished project?
    // #TODO# move to the constructor.
    $project_status = $xml->xpath('/project/project_status');
    if ($project_status[0][0] == 'unpublished') {
      return drush_set_error('DRUSH_PM_PROJECT_UNPUBLISHED', dt("Project !project is unpublished and has no releases available.", array('!project' => $request['name'])), 'warning');
    }

    return new UpdateServiceProject($xml);
  }

  /**
   * Returns URL to the updates service for the given request.
   *
   * @param array $request
   *   A request array.
   *
   * @return string
   *   URL to the updates service.
   *
   * @see \Drupal\update\UpdateFetcher::buildFetchUrl()
   */
  public static function buildFetchUrl(array $request) {
    $status_url = isset($request['status url']) ? $request['status url'] : RELEASE_INFO_DEFAULT_URL;
    return $status_url . '/' . $request['name'] . '/' . $request['drupal_version'];
  }

  /**
   * Parses update service xml.
   *
   * @param \SimpleXMLElement $xml
   *   XML element from the updates service.
   *
   * @return array
   *   Project update information.
   */
  private function parseXml(\SimpleXMLElement $xml) {
    // If bootstrapped, we can obtain which is the installed release of a project.
    #TODO# Move this out of here. Probably to the engine class methods that rely on this.
    static $installed_projects = FALSE;
    if (!$installed_projects) {
      if (drush_get_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE) >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
        $installed_projects = drush_get_projects();
      }
      else {
        $installed_projects = array();
      }
    }

    $project_info = array();
    foreach (array('title', 'short_name', 'dc:creator', 'api_version', 'recommended_major', 'supported_majors', 'default_major', 'project_status', 'link') as $item) {
      if (array_key_exists($item, (array)$xml)) {
        $value = $xml->xpath($item);
        $project_info[$item] = (string)$value[0];
      }
    }

    $recommended_major = @$xml->xpath("/project/recommended_major");
    $recommended_major = empty($recommended_major)?"":(string)$recommended_major[0];
    $supported_majors = @$xml->xpath("/project/supported_majors");
    $supported_majors = empty($supported_majors)?array():array_flip(explode(',', (string)$supported_majors[0]));
    $releases_xml = @$xml->xpath("/project/releases/release[status='published']");
    $recommended_version = NULL;
    $latest_version = NULL;
    $releases = array();
    foreach ($releases_xml as $release) {
      $release_info = array();
      foreach (array('name', 'version', 'tag', 'version_major', 'version_extra', 'status', 'release_link', 'download_link', 'date', 'mdhash', 'filesize') as $item) {
        if (array_key_exists($item, $release)) {
          $value = $release->xpath($item);
          $release_info[$item] = (string)$value[0];
        }
      }
      $statuses = array();
      if (array_key_exists($release_info['version_major'], $supported_majors)) {
        $statuses[] = "Supported";
        unset($supported_majors[$release_info['version_major']]);
      }
      if ($release_info['version_major'] == $recommended_major) {
        if (!isset($latest_version)) {
          $latest_version = $release_info['version'];
        }
        // The first stable version (no 'version extra') in the recommended major
        // is the recommended release
        if (empty($release_info['version_extra']) && (!isset($recommended_version))) {
          $statuses[] = "Recommended";
          $recommended_version = $release_info['version'];
        }
      }
      if (!empty($release_info['version_extra']) && ($release_info['version_extra'] == "dev")) {
        $statuses[] = "Development";
      }
      foreach ($release->xpath('terms/term/value') as $release_type) {
        // There are three kinds of release types that we recognize:
        // "Bug fixes", "New features" and "Security update".
        // We will add "Security" for security updates, and nothing
        // for the other kinds.
        if (strpos($release_type, "Security") !== FALSE) {
          $statuses[] = "Security";
        }
      }
      // Add to status whether the project is installed.
      if (isset($installed_projects[$project_info['short_name']])) {
        if ($installed_projects[$project_info['short_name']]['version'] == $release_info['version']) {
           $statuses[] = dt('Installed');
           $project_info['installed'] = $release_info['version'];
         }
      }
      $release_info['release_status'] = $statuses;
      $releases[$release_info['version']] = $release_info;
    }
    // If there is no -stable- release in the recommended major,
    // then take the latest version in the recommended major to be
    // the recommended release.
    if (!isset($recommended_version) && isset($latest_version)) {
      $recommended_version = $latest_version;
      $releases[$recommended_version]['release_status'][] = "Recommended";
    }

    $project_info['releases'] = $releases;
    $project_info['recommended'] = $recommended_version;

    return $project_info;
  }

  /**
   * Returns the project type the xml data represents.
   *
   * @return string
   *   Type of the project.
   *
   * #TODO# do parsing in parseXml() and transform this into a simple getter
   */
  public function getType() {
    $project_types = array(
      'core' => 'project_core',
      'profile' => 'project_distribution',
      'module' => 'project_module',
      'theme' => 'project_theme',
      'theme engine' => 'project_theme_engine',
      'translation' => 'project_translation'
    );

    $type = (string)$this->xml->type;
    // Probably unused but kept for possible legacy compat.
    $type = ($type == 'profile-legacy') ? 'profile' : $type;
    $type = array_search($type, $project_types);
    return $type;
  }

  /**
   * Gets the parsed xml.
   *
   * @return array
   */
  public function getInfo() {
    return $this->parsed;
  }

  /**
   * Helper to pick the best release in a list of candidates.
   *
   * The best one is the first stable release if there are stable
   * releases; otherwise, it will be the first of the candidates.
   *
   * @param array $releases
   *   Array of release arrays.
   *
   * @return array|bool
   */
  private static function getBestRelease(array $releases) {
    if (empty($releases)) {
      return FALSE;
    }
    else {
      // If there are releases found, let's try first to fetch one with no
      // 'version_extra'. Otherwise, use all.
      $stable_releases = array();
      foreach ($releases as $one_release) {
        if (!array_key_exists('version_extra', $one_release)) {
          $stable_releases[] = $one_release;
        }
      }
      if (!empty($stable_releases)) {
        $releases = $stable_releases;
      }
    }

    // First published release is just the first value in $releases.
    return (array)$releases[0];
  }

  /**
   * Returns the specific release that matches the request version.
   *
   * @param string $version
   *    Version of the release to pick.
   * @return array|bool
   *    The release or FALSE if no version specified or no release found.
   */
  public function getSpecificRelease($version = NULL) {
    if (!empty($version)) {
      $matches = array();
      // See if we only have a branch version.
      if (preg_match('/^\d+\.x-(\d+)$/', $version, $matches)) {
        $xpath_releases = "/project/releases/release[status='published'][version_major=" . (string)$matches[1] . "]";
        $releases = $this->xml->xpath($xpath_releases);
      }
      else {
        // In some cases, the request only says something like '7.x-3.x' but the
        // version strings include '-dev' on the end, so we need to append that
        // here for the xpath to match below.
        if (substr($version, -2) == '.x') {
          $version .= '-dev';
        }
        $releases = $this->xml->xpath("/project/releases/release[status='published'][version='" . $version . "']");
      }
      if (empty($releases)) {
        return FALSE;
      }
      return self::getBestRelease($releases);
    }
    return array();
  }

  /**
   * Pick the first dev release from XML list.
   *
   * @return array|bool
   *    The selected release xml object or FALSE.
   */
  public function getDevRelease() {
    $releases = $this->xml->xpath("/project/releases/release[status='published'][version_extra='dev']");
    return self::getBestRelease($releases);
  }

  /**
   * Pick most appropriate release from XML list.
   *
   * @return array|bool
   *    The selected release xml object or FALSE.
   */
  public function getRecommendedOrSupportedRelease() {
    $releases = array();
    foreach(array('recommended_major', 'supported_majors') as $release_type) {
      if ($versions = $this->xml->xpath("/project/$release_type")) {
        $xpath = "/project/releases/release[status='published'][version_major=" . (string)$versions[0] . "]";
        $releases = $this->xml->xpath($xpath);
        if (!empty($releases)) {
          break;
        }
      }
    }

    return self::getBestRelease($releases);
  }

  /**
   * Helper function for release_info_filter_releases().
   *
   * @param $a
   *   Value to compare.
   * @param $b
   *   Value to compare.
   *
   * @return int
   */
  private static function compareDates($a, $b) {
    if ($a['date'] == $b['date']) {
      return 0;
    }
    if ($a['version_major'] == $b['version_major']) {
      return ($a['date'] > $b['date']) ? -1 : 1;
    }
    return ($a['version_major'] > $b['version_major']) ? -1 : 1;
  }

  /**
   * Filter project releases by a criteria and returns a list.
   *
   * @param $all
   *   Show all releases. If FALSE, shows only the first release that is
   *   Recommended or Supported or Security or Installed.
   * @param String $restrict_to
   *   If set to 'dev', show only development release.
   * @param $show_all_until_installed
   *   If TRUE, then all releases will be shown until the INSTALLED release
   *   is found, at which point the algorithm will stop.
   *
   * @return array
   */
  function filterReleases($all = FALSE, $restrict_to = '', $show_all_until_installed = TRUE) {
    $releases = $this->parsed['releases'];
    // Start off by sorting releases by release date.
    uasort($releases, array($this, 'compareDates'));
    // Find version_major for the installed release.
    #TODO# fix this along UpdateServiceProject::parseXml()
    $installed_version_major = FALSE;
    foreach ($releases as $version => $release_info) {
      if (in_array("Installed", $release_info['release_status'])) {
        $installed_version_major = $release_info['version_major'];
      }
    }
    // Now iterate through and filter out the releases we're interested in.
    $options = array();
    $limits_list = array();
    $dev = $restrict_to == 'dev';
    foreach ($releases as $version => $release_info) {
      if (!$dev || ((array_key_exists('version_extra', $release_info)) && ($release_info['version_extra'] == 'dev'))) {
        $saw_unique_status = FALSE;
        foreach ($release_info['release_status'] as $one_status) {
          // We will show the first release of a given kind;
          // after we show the first security release, we show
          // no other.  We do this on a per-major-version basis,
          // though, so if a project has three major versions, then
          // we will show the first security release from each.
          // This rule is overridden by $all and $show_all_until_installed.
          $test_key = $release_info['version_major'] . $one_status;
          if (!array_key_exists($test_key, $limits_list)) {
            $limits_list[$test_key] = TRUE;
            $saw_unique_status = TRUE;
            // Once we see the "Installed" release we will stop
            // showing all releases
            if ($one_status == "Installed") {
              $show_all_until_installed = FALSE;
            }
          }
        }
        if ($all || ($show_all_until_installed && ($installed_version_major == $release_info['version_major'])) || $saw_unique_status) {
          $options[$release_info['version']] = $release_info;
        }
      }
    }
    // If "show all until installed" is still true, that means that
    // we never encountered the installed release anywhere in releases,
    // and therefore we did not filter out any releases at all.  If this
    // is the case, then call ourselves again, this time with
    // $show_all_until_installed set to FALSE from the beginning.
    // The other situation we might encounter is when we do not encounter
    // the installed release, and $options is still empty.  This means
    // that there were no supported or recommended or security or development
    // releases found.  If this is the case, then we will force ALL to TRUE
    // and show everything on the second iteration.
    if (($all === FALSE) && ($show_all_until_installed === TRUE)) {
      $options = $this->filterReleases(empty($options), $restrict_to, FALSE);
    }
    return $options;
  }

  /**
   * Prints release notes for given projects.
   *
   * @param string $version
   *   Version of the release to get notes.
   * @param bool $print_status
   *   Whether to print a informative note.
   * @param string $tmpfile
   *   If provided, a file that contains contents to show before the
   *   release notes.
   */
  function getReleaseNotes($version = NULL, $print_status = TRUE, $tmpfile = NULL) {
    $project_name = $this->parsed['short_name'];
    if (!isset($tmpfile)) {
      $tmpfile = drush_tempnam('rln-' . $project_name . '.');
    }

    $versions = array();
    if (!is_null($version)) {
      $versions[] = $version;
    }
    else {
      // If requested project is installed,
      // show release notes for the installed version and all newer versions.
      if (isset($this->parsed['recommended'], $this->parsed['installed'])) {
        $releases = array_reverse($this->parsed['releases']);
        foreach($releases as $version => $release) {
          if ($release['date'] >= $this->parsed['releases'][$this->parsed['installed']]['date']) {
            $release += array('version_extra' => '');
            $this->parsed['releases'][$this->parsed['installed']] += array('version_extra' => '');
            if ($release['version_extra'] == 'dev' && $this->parsed['releases'][$this->parsed['installed']]['version_extra'] != 'dev') {
              continue;
            }
            $versions[] = $version;
          }
        }
      }
      else {
        // Project is not installed and user did not specify a version,
        // so show the release notes for the recommended version.
        $versions[] = $this->parsed['recommended'];
      }
    }

    foreach ($versions as $version) {
      if (!isset($this->parsed['releases'][$version]['release_link'])) {
        drush_log(dt("Project !project does not have release notes for version !version.", array('!project' => $project_name, '!version' => $version)), 'warning');
        continue;
      }
      $release_link = $this->parsed['releases'][$version]['release_link'];
      $filename = drush_download_file($release_link, drush_tempnam($project_name));
      @$dom = DOMDocument::loadHTMLFile($filename);
      if ($dom) {
        drush_log(dt("Successfully parsed and loaded the HTML contained in the release notes' page for !project (!version) project.", array('!project' => $project_name, '!version' => $version)), 'notice');
      }
      else {
        drush_log(dt("Error while requesting the release notes page for !project project.", array('!project' => $project_name)), 'error');
        continue;
      }
      $xml = simplexml_import_dom($dom);
      $node_content = $xml->xpath('//div[@class="node node-project-release clearfix"]');

      // Prepare the project notes to print.
      $notes_last_update = $node_content[0]->div[1]->div[0]->asXML();
      unset($node_content[0]->div);
      $project_notes = $node_content[0]->asXML();

      // Build the status message.
      $status_msg = '> ' . implode(', ', $this->parsed['releases'][$version]['release_status']);
      $break = '<br>';
      $notes_header = dt("<hr>
> RELEASE NOTES FOR '!name' PROJECT, VERSION !version:!break
> !time.!break
!status
<hr>
", array('!status' => $print_status ? $status_msg : '', '!name' => strtoupper($project_name), '!break' => $break, '!version' => $version, '!time' => $notes_last_update));

      // Finally print the release notes for the requested project.
      #TODO# accept $html as a method argument
      if (drush_get_option('html', FALSE)) {
        $print = $notes_header . $project_notes;
      }
      else {
        $print = drush_html_to_text($notes_header . $project_notes . "\n", array('br', 'p', 'ul', 'ol', 'li', 'hr'));
        if (drush_drupal_major_version() < 7) { $print .= "\n"; }
      }
      file_put_contents($tmpfile, $print, FILE_APPEND);
    }

    #TODO# don't print! Just return the filename
    drush_print_file($tmpfile);
  }
}
