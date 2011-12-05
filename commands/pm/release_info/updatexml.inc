<?php

/**
 * @file Drush release info engine for update.drupal.org
 */

function release_info_fetch(&$request) {
  $project_types = pm_project_types();
  $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
  $xml = updatexml_get_release_history_xml($request);
  if (!$xml) {
    return FALSE;
  }

  // Identify the most appropriate release.
  $release = updatexml_parse_release($request, $xml);
  if (!$release) {
    return FALSE;
  }

  // Determine what type of project we are to download.
  $request['project_type'] = 'module';
  if ($types = $xml->xpath('/project/terms/term[name="Projects" and ' . $project_types_xpath . ']')) {
    $request['project_type'] = array_search($types[0]->value, $project_types);
  }

  if ($request['project_type'] == 'translation') {
    return drush_set_error('DRUSH_PM_DOWNLOAD_TRANSLATIONS_FORBIDDEN', dt('Drush has dropped support for downloading translation projects. See l10n_update and l10n_install projects.'));
  }

  // Determine the name of the directory that will contain the project.
  // We face here all the asymetries to make it smooth for package handlers.
  // For Drupal core: --drupal-project-rename or drupal-x.y
  if ($request['project_type'] == 'core') {
    // Avoid downloading core into existing core.
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
      if (strpos(realpath(drush_get_option('destination')), DRUPAL_ROOT) !== FALSE) {
        return drush_set_error('DRUSH_PM_DOWNLOAD_TRANSLATIONS_FORBIDDEN', dt('It\'s forbidden to download !project core into an existing core.', array('!project' => $request['name'])));
      }
    }

    if ($rename = drush_get_option('drupal-project-rename', FALSE)) {
      if ($rename === TRUE) {
        $request['project_dir'] = 'drupal';
      }
      else {
        $request['project_dir'] = $rename;
      }
    }
    else {
      // Set to drupal-x.y, the expected name for .tar.gz contents.
      // Explicitly needed for cvs package handler.
      $request['project_dir'] = strtolower(strtr($release['name'], ' ', '-'));
    }
  }
    // For the other project types we want the project name. Including core
    // variant for profiles.  Note those come with drupal-x.y in the .tar.gz.
  else {
    $request['project_dir'] = $request['name'];
  }
  return $release;
}

/**
 * Pick most appropriate release from XML list.
 *
 * @param array $request
 *   An array of version specifications as returned by pm_parse_project_version().
 * @param resource $xml
 *   A handle to the XML document.
 */
function updatexml_parse_release($request, $xml) {
  if (!empty($request['version'])) {
    $releases = $xml->xpath("/project/releases/release[status='published'][version='" . $request['version'] . "']");
    if (empty($releases)) {
      drush_log(dt("Could not locate specified project version, downloading latest stable version"), 'warning');
    }
  }
  // If that did not work, we will get the first published release for the
  // recommended major version.
  if (empty($releases)) {
    if ($recommended_major = $xml->xpath("/project/recommended_major")) {
      $xpath_releases = "/project/releases/release[status='published'][version_major=" . (string)$recommended_major[0] . "]";
      $releases = @$xml->xpath($xpath_releases);
    }
  }
  // If there are recommended releases (no 'version_extra' elements), then use
  // only recommended releases.  Otherwise, use all; in this case, the
  // recommended release defaults to the latest published release with the
  // right recommended major version number.
  $recommended_releases = array();
  if (!empty($releases)) {
    foreach ($releases as $one_release) {
      if (!array_key_exists('version_extra', $one_release)) {
        $recommended_releases[] = $one_release;
      }
    }
  }
  if (!empty($recommended_releases)) {
    $releases = $recommended_releases;
  }
  $release_type = 'recommended';
  if (drush_get_option('dev', FALSE)) {
    $releases = @$xml->xpath("/project/releases/release[status='published'][version_extra='dev']");
    $release_type = 'development';
  }
  if (drush_get_option('select', FALSE) || empty($releases)) {
    if (empty($releases)) {
      drush_print(dt('There is no !type release for project !project.', array('!type' => $release_type, '!project' => $request['name'])));
    }
    $options = _pm_download_releases_choice($xml, $request['name'], drush_get_option('all', FALSE), drush_get_option('dev', FALSE));
    $choice = drush_choice($options, dt('Choose one of the available releases for project !project:', array('!project' => $request['name'])));
    if ($choice) {
      $releases = $xml->xpath("/project/releases/release[status='published'][version='" . $choice . "']");
    }
    else {
      return FALSE;
    }
  }

  // First published release for the recommended major version is just the
  // first value in $releases.
  return (array)$releases[0];
}

/**
 * Download the release history xml for the specified request.
 */
function updatexml_get_release_history_xml($request) {
  // Don't rely on UPDATE_DEFAULT_URL since perhaps we are not fully
  // bootstrapped.
  $url = drush_get_option('source', 'http://updates.drupal.org/release-history') . '/' . $request['name'] . '/' . $request['drupal_version'];
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
