<?php

/**
 * @file
 * drush_make extension for integrating with the drupal.org packaging system.
 *
 * This extension serves the following purposes:
 *  - Adds the --drupal-org and --build-run-key command line options,
 *    which activate the functionality in the rest of the code.
 *  - Validates .make files to ensure they comply with drupal.org packaging
 *    requirements.
 *  - Restricts download locations to those approved for drupal.org packages.
 *  - Implements a custom logging function for error reporting to the packaging
 *    script.
 *  - Builds a list of package contents (nids of package items), and stores
 *    for the packaging system to use.
 */

// URI of the 'drush_make on drupal.org' handbook page.
define('DRUSH_MAKE_DO_DOCUMENTATION_LINK', 'http://drupal.org/node/642116');
// Name of the package contents file used by the packaging script.
define('DRUSH_MAKE_DO_PACKAGE_CONTENTS_FILE', 'package_contents.txt');
// Name of the build errors file used by the packaging script.
define('DRUSH_MAKE_DO_BUILD_ERRORS_FILE', 'build_errors.txt');

/**
 * Implement EXTENSION_drush_make_init().
 */
function drush_make_d_o_drush_init($makefile, $base_path) {
  // Since we're building the profile contents in place, the files written by
  // this script should go in the main build directory. This switch provides
  // the script with it's location.
  if ($drupal_org_build_root = drush_get_option('drupal-org-build-root')) {
    // Create the package contents file. We always check for it's existence
    // in the packaging script, so make sure it exists even if no package
    // items get added.
    if (!touch($drupal_org_build_root . '/' . DRUSH_MAKE_DO_PACKAGE_CONTENTS_FILE)) {
      drush_make_error('FILE_ERROR', dt('Unable to write package contents file to %build_root', array('%build_root' => $drupal_org_build_root)));
    }
    // Remember the build path for this run.
    drush_set_context('DRUSH_MAKE_DO_PACKAGE_BUILD_PATH', $drupal_org_build_root);
  }
  if (drush_get_option('drupal-org')) {
    // Set up a custom error logger.
    drush_set_context('DRUSH_LOG_CALLBACK', 'drush_make_d_o_log_errors_to_file');
  }
}

/**
 * Implement EXTENSION_drush_make_command().
 */
function drush_make_d_o_drush_make_command(&$function, &$parameters, &$dependencies, $id, &$queue) {
  if (drush_get_option('drupal-org')) {
    switch ($function) {
      // No libraries on d.o.
      case 'drush_make_add_libraries':
        return FALSE;

      // Custom drupal.org validation for .make files.
      case 'drush_make_validate_info_file':
        $function = 'drush_make_d_o_validate_info_file';
        break;

      // Custom drupal.org processing for loading update XML files.
      case 'drush_make_updatexml':
        $function = 'drush_make_d_o_updatexml';
        break;
    }
  }
}

/**
 * Provide additional drupal.org specific validation for .make files.
 */
function drush_make_d_o_validate_info_file($info) {

  // Run the regular validation first.
  if (!($info = drush_make_validate_info_file($info))) {
    return FALSE;
  }

  // The list of currently allowed top-leval attributes.
  $top_level_attribute_whitelist = array('core', 'core_release', 'projects');

  // The list of currently allowed project-level attributes.
  $project_attribute_whitelist = array('version', 'subdir');

  // Assume no errors to start.
  $errors = FALSE;

  // Check for disallowed top-level attributes.
  foreach ($info as $attribute => $attribute_data) {
    if (!in_array($attribute, $top_level_attribute_whitelist)) {
      drush_make_error('BUILD_ERROR', dt("The top-level attribute '%attribute' is not allowed in drupal.org .make files", array('%attribute' => $attribute)));
      $errors = TRUE;
    }
  }

  // Check for a valid core value.
  // This is just a basic sanity check for a properly formatted value.
  if (!drush_make_d_o_check_valid_version(dt('Drupal core'), $info['core_release'])) {
    $errors = TRUE;
  }
  if (!empty($info['projects'])) {
    foreach ($info['projects'] as $project => $project_data) {
      // No specifying just project name.
      if (is_numeric($project)) {
        drush_make_error('BUILD_ERROR', dt('Automatic best release support for project %project is not supported -- you must specify a version.', array('%project' => $project_data)));
        $errors = TRUE;
      }
      else {
        // Version must be set.
        if (!isset($project_data['version'])) {
          drush_make_error('BUILD_ERROR', dt('No version specified for project %project -- version is required.', array('%project' => $project)));
          $errors = TRUE;
        }
        // Check for disallowed project-level attributes.
        foreach ($project_data as $attribute => $attribute_data) {
          if (!in_array($attribute, $project_attribute_whitelist)) {
            drush_make_error('BUILD_ERROR', dt("The project-level attribute '%attribute' is not allowed in drupal.org .make files", array('%attribute' => $attribute)));
            $errors = TRUE;
          }
        }
        // Check for bad project version.
        if (!drush_make_d_o_check_valid_version($project, $project_data['version'])) {
          $errors = TRUE;
        }
      }
    }
  }
  // Abort if any errors were found.
  if ($errors) {
    drush_make_error('BUILD_ERROR', dt('A .make file in your profile failed the <a href="@doc_link">drupal.org validation checks</a>.', array('@doc_link' => DRUSH_MAKE_DO_DOCUMENTATION_LINK)));
    return FALSE;
  }
  return $info;
}

/**
 * Validate release versions.
 *
 * @param $name
 *   The display name of the project.
 * @param $version_string
 *   The version string to test.
 * @return
 * TRUE if the version string is bad, FALSE otherwise.
 */
function drush_make_d_o_check_valid_version($name, $version_string) {;
  // Development snapshots not allowed.
  if (preg_match('/.*-dev$/', $version_string)) {
    drush_make_error('BUILD_ERROR', dt('%project branch releases not allowed.', array('%project' => $name)));
    return FALSE;
  }
  // Basic sanity checking on release version strings. We need this because
  // drush_make supports projects[foo][version] = 1, which downloads the latest
  // stable release on the major version 1 branch. We require a specific
  // release.
  elseif (!preg_match('/^\d+\.\d+(-[a-z0-9]+)?$/', $version_string)) {
    drush_make_error('BUILD_ERROR', dt('%project version format incorrect -- specifying an official release version is required.', array('%project' => $name)));
    return FALSE;
  }
  return TRUE;
}

/**
 * Custom implementation of drush_make_updatexml().
 *
 * Only allows loading update XML files from the default location, which for
 * our purposes is updates.drupal.org. This is also the place where package
 * items are collected for the package.
 */
function drush_make_d_o_updatexml($project) {
  $xml = file_get_contents(DRUSH_MAKE_UPDATE_DEFAULT_URL . '/' . $project['name'] . '/' . $project['core']);
  // First, get the release history.
  $release_history = simplexml_load_string($xml);
  if (!is_object($release_history) || !$release_history->title) {
    drush_make_error('XML_ERROR', dt("Could not retrieve version information for %project.", array('%project' => $project['name'])));
    return FALSE;
  }
  drush_log(dt('Project information for %project retrieved.', array('%project' => $project['name'])), 'ok');
  $project['release_history'] = $release_history;
  if (!isset($project['type'])) {
    // Determine the project type.
    $term_map = array(
      'Modules' => 'module',
      'Themes' => 'theme',
      'Drupal project' => 'core',
      'Installation profiles' => 'profile',
      'Translations' => 'translation'
    );
    // Iterate through all terms related to this project.
    foreach ($release_history->terms->term as $term) {
      // If we find a term from the term map, add it.
      if (in_array((string) $term->value, array_keys($term_map))) {
        $project['type'] = $term_map[(string)$term->value];
        break;
      }
    }
    if (!isset($project['type'])) {
      drush_make_error('BUILD_ERROR', dt("Unable to determine project type for %project.", array('%project' => $project['name'])));
      return FALSE;
    }
  }
  // Process the file download data, and record the item_nid for the package.
  if ($project = drush_make_update_xml_download($project)) {
    if ($project = drush_make_d_o_write_package_item_nid($project)) {
      return $project;
    }
  }
  return FALSE;
}

/**
 * Store a package item in a package summary file.
 */
function drush_make_d_o_write_package_item_nid($project) {
  // Release/version logic ripped off from drush_make_update_xml_download().
  // Make an array of releases.
  foreach ($project['release_history']->releases->release as $release) {
    $version = (string) $release->version_major;
    if ((string) $release->version_patch) {
      $version .= '.' . (string) $release->version_patch;
    }
    else {
      $version .= '.0';
    }
    if ($extra_version = (string) $release->version_extra) {
      $version .= '-' . $extra_version;
    }
    // Grab the release nid from the release node link.
    $releases[$version] = basename($release->release_link);
  }
  // Write the item's nid to the package contents file.
  if ($project['version'] && ($item_nid = $releases[$project['version']])) {
    $package_build_path = drush_get_context('DRUSH_MAKE_DO_PACKAGE_BUILD_PATH', NULL);
    if (file_put_contents($package_build_path . '/' . DRUSH_MAKE_DO_PACKAGE_CONTENTS_FILE, "$item_nid\n", FILE_APPEND)) {
      return $project;
    }
  }
  return FALSE;
}

/**
 * Custom logging function for packaging errors.
 *
 * Logs all error messages to a build_errors.txt file in the root of the
 * package build.
 *
 * @see drush_log() for a description of the $entry array.
 */
function drush_make_d_o_log_errors_to_file($entry) {
  $package_build_path = drush_get_context('DRUSH_MAKE_DO_PACKAGE_BUILD_PATH', NULL);
  if ($entry['type'] == 'error' || $entry['type'] == 'failed') {
    file_put_contents($package_build_path . '/' . DRUSH_MAKE_DO_BUILD_ERRORS_FILE, $entry['message'] . "\n", FILE_APPEND);
  }
}
