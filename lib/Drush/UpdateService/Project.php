<?php

namespace Drush\UpdateService;

use Drush\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;
use Composer\Semver\Semver;

/**
 * Representation of a project's release info from the update service.
 */
class Project {
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
    // Check if the xml contains an error on the project.
    if ($error = $xml->xpath('/error')) {
      $error = (string)$error[0];
      if (strpos($error, 'No release history available for') === 0) {
        $project_status = 'unsupported';
      }
      elseif (strpos($error, 'No release history was found for the requested project') === 0) {
        $project_status = 'unknown';
      }
      // Any other error we are not aware of.
      else {
        $project_status = 'unknown';
      }
    }
    // The xml has a project, but still it can have errors.
    else {
      $this->parsed = self::parseXml($xml);
      if (empty($this->parsed['releases'])) {
        $error = dt('No available releases found for the requested project (!name).', array('!name' => $this->parsed['short_name']));
        $project_status = 'unknown';
      }
      else {
        $error = FALSE;
        $project_status = $xml->xpath('/project/project_status');
        $project_status = (string)$project_status[0];
      }
    }

    if (drush_drupal_major_version() >= 8) {
      foreach ($this->parsed['releases'] as $version => $info) {
        if (!$this->versionIsCompatible($version, $info)) {
          unset($this->parsed['releases'][$version]);
        }
      }
    }

    $this->project_status = $project_status;
    $this->error = $error;
    if ($error) {
      drush_set_error('DRUSH_RELEASE_INFO_ERROR', $error);
    }
  }

  protected function versionIsCompatible($version, $info) {
    // If there is no "core_compatibility" field then assume this version
    // is only compatible with Drupal 8.
    if (!isset($info['core_compatibility'])) {
      return drush_drupal_major_version() == 8;
    }
    if (preg_match('#^[0-9]*\.x$#', $info['core_compatibility'])) {
      return $info['core_compatibility'] == drush_drupal_major_version() . '.x';
    }
    return Semver::satisfies(drush_drupal_version(), $info['core_compatibility']);
  }

  /**
   * Downloads release info xml from update service.
   *
   * @param array $request
   *   A request array.
   * @param int $cache_duration
   *   Cache lifetime.
   *
   * @return \Drush\UpdateService\Project
   */
  public static function getInstance(array $request, $cache_duration = ReleaseInfo::CACHE_LIFETIME) {
    $url = self::buildFetchUrl($request);
    drush_log(dt('Downloading release history from !url', array('!url' => $url)));

    $path = drush_download_file($url, drush_tempnam($request['name']), $cache_duration);
    $xml = simplexml_load_file($path);
    if (!$xml) {
      $error = dt('Failed to get available update data from !url', array('!url' => $url));
      return drush_set_error('DRUSH_RELEASE_INFO_ERROR', $error);
    }

    return new Project($xml);
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
    $status_url = isset($request['status url']) ? $request['status url'] : ReleaseInfo::DEFAULT_URL;
    $drupal_version = $request['drupal_version'];
    if (drush_drupal_major_version() >= 8) {
      $drupal_version = 'current';
    }
    if ($drupal_version == '9.x') {
      $drupal_version = 'all';
    }
    return $status_url . '/' . $request['name'] . '/' . $drupal_version;
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
  private static function parseXml(\SimpleXMLElement $xml) {
    $project_info = array();

    // Extract general project info.
    $items = array('title', 'short_name', 'dc:creator', 'type', 'api_version',
      'recommended_major', 'supported_majors', 'default_major', 'supported_branches',
      'project_status', 'link',
    );
    foreach ($items as $item) {
      if (array_key_exists($item, (array)$xml)) {
        $value = $xml->xpath($item);
        $project_info[$item] = (string)$value[0];
      }
    }
    $supported_branches = [];
    if (isset($project_info['supported_branches'])) {
      $supported_branches = explode(',', $project_info['supported_branches']);
    }

    // Parse project type.
    $project_types = array(
      'core' => 'project_core',
      'profile' => 'project_distribution',
      'module' => 'project_module',
      'theme' => 'project_theme',
      'theme engine' => 'project_theme_engine',
      'translation' => 'project_translation',
      'utility' => 'project_drupalorg',
    );
    $type = $project_info['type'];
    // Probably unused but kept for possible legacy compat.
    $type = ($type == 'profile-legacy') ? 'profile' : $type;
    $project_info['project_type'] = array_search($type, $project_types);

    // Extract project terms.
    $project_info['terms'] = array();
    if ($xml->terms) {
      foreach ($xml->terms->children() as $term) {
        $term_name = (string) $term->name;
        $term_value = (string) $term->value;
        if (!isset($project_info[$term_name])) {
          $project_info['terms'][$term_name] = array();
        }
        $project_info['terms'][$term_name][] = $term_value;
      }
    }

    // Extract and parse releases info.
    // In addition to the info in the update service, here we calculate
    // release statuses as Recommended, Security, etc.

    $recommended_major = empty($project_info['recommended_major']) ? '' : $project_info['recommended_major'];
    $supported_majors = empty($project_info['supported_majors']) ? array() : array_flip(explode(',', $project_info['supported_majors']));

    $items = array(
      'name', 'date', 'status', 'type',
      'version', 'tag', 'version_major', 'version_patch', 'version_extra',
      'release_link', 'download_link', 'mdhash', 'filesize', 'core_compatibility',
    );

    $releases = array();
    $releases_xml = @$xml->xpath("/project/releases/release[status='published']");
    foreach ($releases_xml as $release) {
      $release_info = array();
      $statuses = array();

      // Extract general release info.
      foreach ($items as $item) {
        if (array_key_exists($item, $release)) {
          $value = $release->xpath($item);
          $release_info[$item] = (string)$value[0];
        }
      }

      // Extract release terms.
      $release_info['terms'] = array();
      if ($release->terms) {
        foreach ($release->terms->children() as $term) {
          $term_name = (string) $term->name;
          $term_value = (string) $term->value;
          if (!isset($release_info['terms'][$term_name])) {
            $release_info['terms'][$term_name] = array();
          }
          $release_info['terms'][$term_name][] = $term_value;

          // Add "Security" for security updates, and nothing
          // for the other kinds.
          if (strpos($term_value, "Security") !== FALSE) {
            $statuses[] = "Security";
          }
        }
      }

      // Extract files.
      $release_info['files'] = array();
      foreach ($release->files->children() as $file) {
        // Normalize keys to match the ones in the release info.
        $item = array(
          'download_link' => (string) $file->url,
          'date'          => (string) $file->filedate,
          'mdhash'        => (string) $file->md5,
          'filesize'      => (string) $file->size,
          'archive_type'  => (string) $file->archive_type,
        );
        if (!empty($file->variant)) {
          $item['variant'] = (string) $file->variant;
        }
        $release_info['files'][] = $item;

        // Copy the mdhash from the matching download file into the
        // root of the release object (make /current structure like /8.x)
        if ($item['download_link'] == $release_info['download_link'] && !isset($release_info['mdhash'])) {
          $release_info['mdhash'] = $item['mdhash'];
        }
      }

      // '/current' does not include version_major et.al.; put them back if missing.
      if (!isset($release_info['version_major'])) {
          $two_part_version_key = 'version_patch';
          $version = preg_replace('#^[89]\.x-#', '', $release_info['version']);
          if ($version == $release_info['version']) {
            $two_part_version_key = 'version_minor';
          }
          if (preg_match('#-([a-z]+[0-9]*)$#', $version, $matches)) {
            $release_info['version_extra'] = $matches[1];
            $version = preg_replace('#-[a-z]+[0-9]*$#', '', $version);
          }
          $version = preg_replace('#\.x$#', '', $version);
          $parts = explode('.', $version);

          $release_info['version_major'] = $parts[0];
          if (count($parts) > 1) {
            $release_info[$two_part_version_key] = $parts[1];
          }
          if (count($parts) > 2) {
            $release_info['version_minor'] = $parts[1];
            $release_info['version_patch'] = $parts[2];
          }
      }

      // Calculate statuses.
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
      } else {
        $supported_branch = static::branchIsSupported($release_info['version'], $supported_branches);
        if ($supported_branch) {
          $statuses[] = "Supported";
          $supported_branches = array_diff($supported_branches, [$supported_branch]);
          $sup_maj = $release_info['version_major'];
          if (!empty($project_info['supported_majors'])) {
            $sup_maj = $project_info['supported_majors'] . ',' . $sup_maj;
          }
          $project_info['supported_majors'] = $sup_maj;
        }
      }

      $release_info['release_status'] = $statuses;
      $releases[$release_info['version']] = $release_info;
    }

    // If there's no "Recommended major version", we want to recommend
    // the most recent release.
    if (!$recommended_major) {
      $latest_version = key($releases);
    }

    // If there is no -stable- release in the recommended major,
    // then take the latest version in the recommended major to be
    // the recommended release.
    if (!isset($recommended_version) && isset($latest_version)) {
      $recommended_version = $latest_version;
      $releases[$recommended_version]['release_status'][] = "Recommended";
    }

    $project_info['releases'] = $releases;
    if (isset($recommended_version)) {
      $project_info['recommended'] = $recommended_version;
    }

    return $project_info;
  }

  private static function branchIsSupported($version, $supported_branches) {
    foreach ($supported_branches as $supported_branch) {
      if (substr($version, 0, strlen($supported_branch)) == $supported_branch) {
        return $supported_branch;
      }
    }
    return false;
  }

  /**
   * Gets the project type.
   *
   * @return string
   *   Type of the project.
   */
  public function getType() {
    return $this->parsed['project_type'];
  }

  /**
   * Gets the project status in the update service.
   *
   * This is the project status in drupal.org: insecure, revoked, published etc.
   *
   * @return string
   */
  public function getStatus() {
    return $this->project_status;
  }

  /**
   * Whether this object represents a project in the update service or an error.
   */
  public function isValid() {
    return ($this->error === FALSE);
  }

  /**
   * Gets the parsed xml.
   *
   * @return array or FALSE if the xml has an error.
   */
  public function getInfo() {
    return (!$this->error) ? $this->parsed : FALSE;
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
  public static function getBestRelease(array $releases) {
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
    return reset($releases);
  }

  private function searchReleases($key, $value) {
    $releases = array();
    foreach ($this->parsed['releases'] as $version => $release) {
      if ($release['status'] == 'published' && isset($release[$key]) && strcmp($release[$key], $value) == 0) {
        $releases[$version] = $release;
      }
    }
    return $releases;
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
        $releases = $this->searchReleases('version_major', $matches[1]);
      }
      else {
        // In some cases, the request only says something like '7.x-3.x' but the
        // version strings include '-dev' on the end, so we need to append that
        // here for the xpath to match below.
        if (substr($version, -2) == '.x') {
          $version .= '-dev';
        }
        $releases = $this->searchReleases('version', $version);
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
    $releases = $this->searchReleases('version_extra', 'dev');
    return self::getBestRelease($releases);
  }

  /**
   * Pick most appropriate release from XML list.
   *
   * @return array|bool
   *    The selected release xml object or FALSE.
   */
  public function getRecommendedOrSupportedRelease() {
    $majors = array();

    $recommended_major = empty($this->parsed['recommended_major']) ? 0 : $this->parsed['recommended_major'];
    if ($recommended_major != 0) {
      $majors[] = $this->parsed['recommended_major'];
    }
    if (!empty($this->parsed['supported_majors'])) {
      $supported = explode(',', $this->parsed['supported_majors']);
      foreach ($supported as $v) {
        if ($v != $recommended_major) {
          $majors[] = $v;
        }
      }
    }
    $releases = array();
    foreach ($majors as $major) {
      $releases = $this->searchReleases('version_major', $major);
      if (!empty($releases)) {
        break;
      }
    }

    return self::getBestRelease($releases);
  }

  /**
   * Comparison routine to order releases by date.
   *
   * @param array $a
   *   Release to compare.
   * @param array $b
   *   Release to compare.
   *
   * @return int
   * -1, 0 or 1 whether $a is greater, equal or lower than $b.
   */
  private static function compareDates(array $a, array $b) {
    if ($a['date'] == $b['date']) {
      return ($a['version_major'] > $b['version_major']) ? -1 : 1;
    }
    if ($a['version_major'] == $b['version_major']) {
      return ($a['date'] > $b['date']) ? -1 : 1;
    }
    return ($a['version_major'] > $b['version_major']) ? -1 : 1;
  }

  /**
   * Comparison routine to order releases by version.
   *
   * @param array $a
   *   Release to compare.
   * @param array $b
   *   Release to compare.
   *
   * @return int
   * -1, 0 or 1 whether $a is greater, equal or lower than $b.
   */
  private static function compareVersions(array $a, array $b) {
    $defaults = array(
      'version_patch' => '',
      'version_extra' => '',
      'date' => 0,
    );
    $a += $defaults;
    $b += $defaults;
    if ($a['version_major'] != $b['version_major']) {
      return ($a['version_major'] > $b['version_major']) ? -1 : 1;
    }
    else if ($a['version_patch'] != $b['version_patch']) {
      return ($a['version_patch'] > $b['version_patch']) ? -1 : 1;
    }
    else if ($a['version_extra'] != $b['version_extra']) {
      // Don't rely on version_extra alphabetical order.
      return ($a['date'] > $b['date']) ? -1 : 1;
    }

    return 0;
  }

  /**
   * Filter project releases by a criteria and returns a list.
   *
   * If no filter is provided, the first Recommended, Supported, Security
   * or Development release on each major version will be shown.
   *
   * @param string $filter
   *   Valid values:
   *     - 'all': Select all releases.
   *     - 'dev': Select all development releases.
   * @param string $installed_version
   *   Version string. If provided, Select all releases in the same
   *   version_major branch until the provided one is found.
   *   On any other branch, the default behaviour will be applied.
   *
   * @return array
   *   List of releases matching the filter criteria.
   */
  function filterReleases($filter = '', $installed_version = NULL) {
    $releases = $this->parsed['releases'];
    usort($releases, array($this, 'compareDates'));

    $installed_version = pm_parse_version($installed_version);

    // Iterate through and filter out the releases we're interested in.
    $options = array();
    $limits_list = array();
    foreach ($releases as $release) {
      $eligible = FALSE;

      // Mark as eligible if the filter criteria matches.
      if ($filter == 'all') {
        $eligible = TRUE;
      }
      elseif ($filter == 'dev') {
        if (!empty($release['version_extra']) && ($release['version_extra'] == 'dev')) {
          $eligible = TRUE;
        }
      }
      // The Drupal core version scheme (ex: 7.31) is different to
      // other projects (ex 7.x-3.2). We need to manage this special case.
      elseif (($this->getType() != 'core') && ($installed_version['version_major'] == $release['version_major'])) {
        // In case there's no filter, select all releases until the installed one.
        // Always show the dev release.
        if (isset($release['version_extra']) && ($release['version_extra'] == 'dev')) {
          $eligible = TRUE;
        }
        else {
          if (self::compareVersions($release, $installed_version) < 1) {
            $eligible = TRUE;
          }
        }
      }
      // Otherwise, pick only the first release in each status.
      // For example after we pick out the first security release,
      // we won't pick any other. We do this on a per-major-version basis,
      // though, so if a project has three major versions, then we will
      // pick out the first security release from each.
      else {
        foreach ($release['release_status'] as $one_status) {
          $test_key = $release['version_major'] . $one_status;
          if (empty($limits_list[$test_key])) {
            $limits_list[$test_key] = TRUE;
            $eligible = TRUE;
          }
        }
      }

      if ($eligible) {
        $options[$release['version']] = $release;
      }
    }

    // Add Installed status.
    if (!is_null($installed_version) && isset($options[$installed_version['version']])) {
      $options[$installed_version['version']]['release_status'][] = 'Installed';
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

    // Select versions to show.
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
        drush_log(dt("Project !project does not have release notes for version !version.", array('!project' => $project_name, '!version' => $version)), LogLevel::WARNING);
        continue;
      }

      // Download the release node page and get the html as xml to explore it.
      $release_link = $this->parsed['releases'][$version]['release_link'];
      $filename = drush_download_file($release_link, drush_tempnam($project_name));
      @$dom = \DOMDocument::loadHTMLFile($filename);
      if ($dom) {
        drush_log(dt("Successfully parsed and loaded the HTML contained in the release notes' page for !project (!version) project.", array('!project' => $project_name, '!version' => $version)), LogLevel::NOTICE);
      }
      else {
        drush_log(dt("Error while requesting the release notes page for !project project.", array('!project' => $project_name)), LogLevel::ERROR);
        continue;
      }
      $xml = simplexml_import_dom($dom);

      // Extract last update time and the notes.
      $last_updated = $xml->xpath('//div[contains(@class,"views-field-changed")]');
      $last_updated = $last_updated[0]->asXML();
      $notes = $xml->xpath('//div[contains(@class,"field-name-body")]');
      $notes = (!empty($notes)) ? $notes[0]->asXML() : dt("There're no release notes.");

      // Build the notes header.
      $header = array();
      $header[] = '<hr>';
      $header[] = dt("> RELEASE NOTES FOR '!name' PROJECT, VERSION !version:", array('!name' => strtoupper($project_name), '!version' => $version));
      $header[] = dt("> !last_updated.", array('!last_updated' => trim(drush_html_to_text($last_updated))));
      if ($print_status) {
        $header[] = '> ' . implode(', ', $this->parsed['releases'][$version]['release_status']);
      }
      $header[] = '<hr>';

      // Finally add the release notes for the requested project to the tmpfile.
      $content = implode("\n", $header) . "\n" . $notes . "\n";
      #TODO# accept $html as a method argument
      if (!drush_get_option('html', FALSE)) {
        $content = drush_html_to_text($content, array('br', 'p', 'ul', 'ol', 'li', 'hr'));
      }
      file_put_contents($tmpfile, $content, FILE_APPEND);
    }

    #TODO# don't print! Just return the filename
    drush_print_file($tmpfile);
  }
}
