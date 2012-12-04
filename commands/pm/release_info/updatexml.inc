<?php

/**
 * @file Drush release info engine for update.drupal.org and compatible services.
 *
 * This engine does connect directly to the update service. It doesn't depend
 * on a bootstraped site.
 */

define('RELEASE_INFO_DEFAULT_URL', 'http://updates.drupal.org/release-history');


/**
 * Obtain the most appropiate release for the requested project.
 *
 * @param Array &$request
 *   A project request as returned by pm_parse_project_version(). The array will
 *   be expanded with the project type.
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
 *   If no supported release is found, allow to ask the user to choose one.
 * @param Boolean $all
 *   In case $select = TRUE this indicates that all available releases will be
 *  offered the user to choose.
 *
 * @return
 *  The selected release xml object.
 */
function release_info_fetch(&$request, $restrict_to = '', $select = 'never', $all = FALSE) {
  if (!in_array($select, array('auto', 'never', 'always'))) {
    drush_log(dt("Error: select strategy must be one of: auto, never, always", array(), 'error'));
    return FALSE;
  }

  $xml = updatexml_get_release_history_xml($request);
  if (!$xml) {
    return FALSE;
  }

  $request['project_type'] = updatexml_determine_project_type($xml);

  if ($select != 'always') {
    // Try to identify the most appropriate release.
    $release = updatexml_parse_release($request, $xml, $restrict_to);
    if ($release) {
      return $release;
    }
    else {
      if ($select == 'never') {
        return FALSE;
      }
    }
  }

  $project_info = updatexml_get_releases_from_xml($xml, $request['name']);
  $releases = release_info_filter_releases($project_info['releases'], $all, $restrict_to);
  $options = array();
  foreach($releases as $version => $release) {
    $options[$version] = array($version, '-', gmdate('Y-M-d', $release['date']), '-', implode(', ', $release['release_status']));
  }
  $choice = drush_choice($options, dt('Choose one of the available releases for !project:', array('!project' => $request['name'])));
  if ($choice) {
    return $project_info['releases'][$choice];
  }

  return FALSE;
}

/**
 * Obtain releases info for given requests and fill in status information.
 *
 * @param $requests
 *   An array of project names optionally with a version.
 */
function release_info_get_releases($requests) {
  $info = array();

  foreach ($requests as $name => $request) {
    $xml = updatexml_get_release_history_xml($request);
    if (!$xml) {
      continue;
    }

    $project_info = updatexml_get_releases_from_xml($xml, $name);
    $info[$name] = $project_info;
  }

  return $info;
}

/**
 * Check if a project is available in a update service.
 *
 * It also checks for consistency by comparing given project type and the
 * type obtained from the update service.
 */
function release_info_check_project($request, $type) {
  $xml = updatexml_get_release_history_xml($request);
  if (!$xml) {
    return FALSE;
  }

  $project_type = updatexml_determine_project_type($xml);
  if ($project_type != $type) {
    return FALSE;
  }

  return TRUE;
}

/**
 * Prints release notes for given projects.
 *
 * @param $requests
 *   An array of drupal.org project names optionally with a version.
 * @param $print_status
 *   Boolean. Used by pm-download to not print a informative note.
 * @param $tmpfile
 *   If provided, a file that contains contents to show before the
 *   release notes.
 */
function release_info_print_releasenotes($requests, $print_status = TRUE, $tmpfile = NULL) {
  $info = release_info_get_releases($requests);
  if (!$info) {
    return drush_log(dt('No valid projects given.'), 'ok');
  }

  if (is_null($tmpfile)) {
    $tmpfile = drush_tempnam('rln-' . implode('-', array_keys($requests)) . '.');
  }

  foreach ($info as $name => $project) {
    $selected_versions = array();
    // If the request includes version, show the release notes for this version.
    if (isset($requests[$name]['version'])) {
      $selected_versions[] = $requests[$name]['version'];
    }
    else {
      // If requested project is installed,
      // show release notes for the installed version and all newer versions.
      if (isset($project['recommended'], $project['installed'])) {
        $releases = array_reverse($project['releases']);
        foreach($releases as $version => $release) {
          if ($release['date'] >= $project['releases'][$project['installed']]['date']) {
            $release += array('version_extra' => '');
            $project['releases'][$project['installed']] += array('version_extra' => '');
            if ($release['version_extra'] == 'dev' && $project['releases'][$project['installed']]['version_extra'] != 'dev') {
              continue;
            }
            $selected_versions[] = $version;
          }
        }
      }
      else {
        // Project is not installed and user did not specify a version,
        // so show the release notes for the recommended version.
        $selected_versions[] = $project['recommended'];
      }
    }

    foreach ($selected_versions as $version) {
      if (!isset($project['releases'][$version]['release_link'])) {
        drush_log(dt("Project !project does not have release notes for version !version.", array('!project' => $name, '!version' => $version)), 'warning');
        continue;
      }
      $release_link = $project['releases'][$version]['release_link'];
      $filename = drush_download_file($release_link, drush_tempnam($name));
      @$dom = DOMDocument::loadHTMLFile($filename);
      if ($dom) {
        drush_log(dt("Successfully parsed and loaded the HTML contained in the release notes' page for !project (!version) project.", array('!project' => $name, '!version' => $version)), 'notice');
      }
      else {
        drush_log(dt("Error while requesting the release notes page for !project project.", array('!project' => $name)), 'error');
        continue;
      }
      $xml = simplexml_import_dom($dom);
      $node_content = $xml->xpath('//*/div[@class="node-content"]');

      // Prepare the project notes to print.
      $notes_last_update = $node_content[0]->div[1]->div[0]->asXML();
      unset($node_content[0]->div);
      $project_notes = $node_content[0]->asXML();

      // Build the status message.
      $status_msg = '> ' . implode(', ', $project['releases'][$version]['release_status']);
      $break = '<br>';
      $notes_header = dt("<hr>
 > RELEASE NOTES FOR '!name' PROJECT, VERSION !version:!break
 > !time.!break
 !status
<hr>
", array('!status' => $print_status ? $status_msg : '', '!name' => strtoupper($name), '!break' => $break, '!version' => $version, '!time' => $notes_last_update));

      // Finally print the release notes for the requested project.
      if (drush_get_option('html', FALSE)) {
        $print = $notes_header . $project_notes;
      }
      else {
        $print = drush_html_to_text($notes_header . $project_notes . "\n", array('br', 'p', 'ul', 'ol', 'li', 'hr'));
        if (drush_drupal_major_version() < 7) { $print .= "\n"; }
      }
      file_put_contents($tmpfile, $print, FILE_APPEND);
    }
  }
  drush_print_file($tmpfile);
}

/**
 * Helper function for release_info_filter_releases().
 */
function _release_info_compare_date($a, $b) {
    if ($a['date'] == $b['date']) {
        return 0;
    }
    if ($a['version_major'] == $b['version_major']) {
      return ($a['date'] > $b['date']) ? -1 : 1;
    }
    return ($a['version_major'] > $b['version_major']) ? -1 : 1;
}

/**
 * Filter a list of releases.
 *
 * @param $releases
 *   Array of release information
 * @param $all
 *   Show all releases. If FALSE, shows only the first release that is
 *   Recommended or Supported or Security or Installed.
 * @param String $restrict_to
 *   If set to 'dev', show only development release.
 * @param $show_all_until_installed
 *   If TRUE, then all releases will be shown until the INSTALLED release
 *   is found, at which point the algorithm will stop.
 */
function release_info_filter_releases($releases, $all = FALSE, $restrict_to = '', $show_all_until_installed = TRUE) {
  // Start off by sorting releases by release date.
  uasort($releases, '_release_info_compare_date');
  // Find version_major for the installed release.
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
            $installed_release_date = $release_info['date'];
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
  // that there were no supported or recommented or security or development
  // releases found.  If this is the case, then we will force ALL to TRUE
  // and show everything on the second iteration.
  if (($all === FALSE) && ($show_all_until_installed === TRUE)) {
    $options = release_info_filter_releases($releases, empty($options), $restrict_to, FALSE);
  }
  return $options;
}

/**
 * Pick most appropriate release from XML list or ask the user if no one fits.
 *
 * @param array $request
 *   An array with project and version strings as returned by
 *   pm_parse_project_version().
 * @param resource $xml
 *   A handle to the XML document.
 * @param String $restrict_to
 *   One of:
 *     'dev': Forces a -dev release.
 *     'version': Forces a point release.
 *     '': No restriction (auto-selects latest recommended or supported release
           if requested release is not found).
 *   Default is ''.
 */
function updatexml_parse_release($request, $xml, $restrict_to = '') {
  if (!empty($request['version'])) {
    $matches = array();
    // See if we only have a branch version.
    if (preg_match('/^\d+\.x-(\d+)$/', $request['version'], $matches)) {
      $xpath_releases = "/project/releases/release[status='published'][version_major=" . (string)$matches[1] . "]";
      $releases = @$xml->xpath($xpath_releases);
    }
    else {
      // In some cases, the request only says something like '7.x-3.x' but the
      // version strings include '-dev' on the end, so we need to append that
      // here for the xpath to match below.
      if (substr($request['version'], -2) == '.x') {
        $request['version'] .= '-dev';
      }
      $releases = $xml->xpath("/project/releases/release[status='published'][version='" . $request['version'] . "']");
      if (empty($releases)) {
        if (empty($restrict_to)) {
          drush_log(dt("Could not locate !project version !version, will try to download latest recommended or supported release.", array('!project' => $request['name'], '!version' => $request['version'])), 'warning');
        }
        else {
          drush_log(dt("Could not locate !project version !version.", array('!project' => $request['name'], '!version' => $request['version'])), 'warning');
          return FALSE;
        }
      }
    }
  }

  if ($restrict_to == 'dev') {
    $releases = @$xml->xpath("/project/releases/release[status='published'][version_extra='dev']");
    if (empty($releases)) {
      drush_print(dt('There is no development release for project !project.', array('!type' => $release_type, '!project' => $request['name'])));
      return FALSE;
    }
  }

  // If that did not work, we will get the first published release for the
  // recommended major version or fallback to other supported major versions.
  if (empty($releases)) {
    foreach(array('recommended_major', 'supported_majors') as $release_type) {
      if ($versions = $xml->xpath("/project/$release_type")) {
        $xpath = "/project/releases/release[status='published'][version_major=" . (string)$versions[0] . "]";
        $releases = @$xml->xpath($xpath);
        if (!empty($releases)) {
          break;
        }
      }
    }
  }

  // If there are releases found, let's try first to fetch one with no
  // 'version_extra'. Otherwise, use all.
  if (!empty($releases)) {
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

  if (empty($releases)) {
    drush_print(dt('There are no releases for project !project.', array('!project' => $request['name'])));
    return FALSE;
  }

  // First published release is just the first value in $releases.
  return (array)$releases[0];
}

/**
 * Download the release history xml for the specified request.
 */
function updatexml_get_release_history_xml($request) {
  $status_url = isset($request['status url'])?$request['status url']:RELEASE_INFO_DEFAULT_URL;
  $url =  $status_url . '/' . $request['name'] . '/' . $request['drupal_version'];
  drush_log('Downloading release history from ' . $url);
  // Some hosts have allow_url_fopen disabled.
  if ($path = drush_download_file($url, drush_tempnam($request['name']), drush_get_option('cache-duration-releasexml', 24*3600))) {
    $xml = simplexml_load_file($path);
  }
  if (!$xml) {
    // We are not getting here since drupal.org always serves an XML response.
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Could not download project status information from !url', array('!url' => $url)));
  }
  if ($error = $xml->xpath('/error')) {
    // Don't set an error here since it stops processing during site-upgrade.
    drush_log($error[0], 'warning'); // 'DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE',
    return FALSE;
  }
  // Unpublished project?
  $project_status = $xml->xpath('/project/project_status');
  if ($project_status[0][0] == 'unpublished') {
    return drush_set_error('DRUSH_PM_PROJECT_UNPUBLISHED', dt("Project !project is unpublished and has no releases available.", array('!project' => $request['name'])), 'warning');
  }

  return $xml;
}


/**
 * Obtain releases for a project's xml as returned by the update service.
 */
function updatexml_get_releases_from_xml($xml, $project) {
  // If bootstraped, we can obtain which is the installed release of a project.
  static $installed_projects = FALSE;
  if (!$installed_projects) {
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      $installed_projects = drush_get_projects();
    }
    else {
      $installed_projects = array();
    }
  }

  foreach (array('title', 'short_name', 'dc:creator', 'api_version', 'recommended_major', 'supported_majors', 'default_major', 'project_status', 'link') as $item) {
    if (array_key_exists($item, $xml)) {
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
    if (isset($installed_projects[$project])) {
      if ($installed_projects[$project]['version'] == $release_info['version']) {
         $statuses[] = dt('Installed');
         $project_info['installed'] = $release_info['version'];
       }
    }
    $release_info['release_status'] = $statuses;
    $releases[$release_info['version']] = $release_info;
  }
  // If there is no -stable- release in the recommended major,
  // then take the latest verion in the recommended major to be
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
 * Determine a project type from its update service xml.
 */
function updatexml_determine_project_type($xml) {
  $project_types = array(
    'core' => 'Drupal core',
    'profile' => 'Distributions',
    'profile-legacy' => 'Installation profiles',
    'module' => 'Modules',
    'theme' => 'Themes',
    'theme engine' => 'Theme engines',
    'translation' => 'Translations'
  );

  $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
  $type = 'module';
  if ($types = $xml->xpath('/project/terms/term[name="Projects" and ' . $project_types_xpath . ']')) {
    $type = array_search($types[0]->value, $project_types);
    $type = ($type == 'profile-legacy') ? 'profile' : $type;
  }

  return $type;
}

