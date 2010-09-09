<?php
// $Id$

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
 * Implement hook_drush_command().
 */
function drush_make_d_o_drush_command() {
  $items = array();
  $items['convert-makefile'] = array(
    'description' => 'Convert the specified makefile to a drupal.org friendly format, and verify the converted file.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['verify-makefile'] = array(
    'description' => 'Verify the specified makefile is in a drupal.org-friendly format.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  return $items;
}


/**
 * Drush callback; verify a .make file is in a drupal.org friendly format.
 */
function drush_drush_make_d_o_verify_makefile($makefile) {
  include_once 'drush_make_d_o.convert.inc';
  $verifier = new DrushMakeDrupalorgVerifier($makefile);
  $verifier->run();
}


/**
 * Drush callback; convert the makefile to a drupal.org friendly format.
 */
function drush_drush_make_d_o_convert_makefile($from, $to = NULL) {
  include_once 'drush_make_d_o.convert.inc';
  // If output is going to STDOUT, use a custom logger.
  if (empty($to)) {
    drush_set_context('DRUSH_LOG_CALLBACK', 'drush_make_d_o_convert_log_stdout_handler');
  }
  $converter = new DrushMakeDrupalorgConverter($from, $to);
  $converter->run();
}

/**
 * Implement EXTENSION_drush_make_init().
 */
function drush_make_d_o_drush_init() {
  if (drush_get_option('drupal-org')) {
    // The --drupal-org switch implies these defaults:
    // Location to put our custom build files.
    drush_set_default('drupal-org-build-root', '.');
    // The destination of the downloaded contrib projects.
    drush_set_default('contrib-destination', '.');
    // Whether to allow a build without core.
    drush_set_default('no-core', TRUE);

    // Optionally set up a custom error logger.
    if (drush_get_option('drupal-org-log-errors-to-file')) {
      drush_set_context('DRUSH_LOG_CALLBACK', 'drush_make_d_o_log_errors_to_file');
    }

    // Optionally set up the package contents file. The packaging script
    // expects it, so it's created as an empty file here to ensure it
    // exists.
    if (drush_get_option('drupal-org-log-package-items-to-file')) {
      $drupal_org_build_root = drush_get_option('drupal-org-build-root');
      if (!touch($drupal_org_build_root . '/' . DRUSH_MAKE_DO_PACKAGE_CONTENTS_FILE)) {
        drush_make_error('FILE_ERROR', dt('Unable to write package contents file to %build_root', array('%build_root' => $drupal_org_build_root)));
      }
    }
  }
}

/**
 * Implement EXTENSION_drush_make_validate_info().
 */
function drush_make_d_o_drush_make_validate_info($info) {
  if (drush_get_option('drupal-org')) {
    // The list of currently allowed top-leval attributes.
    $top_level_attribute_whitelist = array('core', 'api', 'core_release', 'projects');
    
    // The list of currently allowed project-level attributes.
    $project_attribute_whitelist = array('version', 'subdir', 'patch');
    
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
        if (!is_array($project_data)) {
          $project_data = $info['projects'][$project] = array('version' => $project_data);
        }
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
        // Check that patches do in fact come from drupal.org.
        if (isset($project_data['patch'])) {
          foreach ($project_data['patch'] as $patch) {
            if (strpos($patch, 'http://drupal.org/files/issues') !== 0) {
              drush_make_error('BUILD_ERROR', dt("The patch '%patch' is not hosted on drupal.org", array('%patch' => $patch)));
              $errors = TRUE;
            }
          }
        }
        // Check for bad project version.
        if (!drush_make_d_o_check_valid_version($project, $project_data['version'])) {
          $errors = TRUE;
        }
        $info['projects'][$project] += array(
          'name' => $project,
          'core' => $info['core'],
        );
        $info['projects'][$project] = drush_make_d_o_updatexml($info['projects'][$project]);
      }
    }
    // Abort if any errors were found.
    if ($errors) {
      drush_make_error('BUILD_ERROR', dt('The drupal.org validation check failed -- see @doc_link for more information.', array('@doc_link' => DRUSH_MAKE_DO_DOCUMENTATION_LINK)));
      return FALSE;
    }
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
function drush_make_d_o_check_valid_version($name, $version_string) {
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
  if ($filename = _drush_make_download_file(drush_get_option('drush-make-update-default-url') . '/' . $project['name'] . '/' . $project['core'])) {
    $release_history = simplexml_load_string(file_get_contents($filename));
    drush_op('unlink', $filename);
  }
  // First, get the release history.
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
    // Optionally log package contents to a file.
    if (drush_get_option('drupal-org-log-package-items-to-file')) {
      $project = drush_make_d_o_write_package_item_nid($project);
    }
    return $project;
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
    if (file_put_contents(drush_get_option('drupal-org-build-root') . '/' . DRUSH_MAKE_DO_PACKAGE_CONTENTS_FILE, "$item_nid\n", FILE_APPEND)) {
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
  if ($entry['type'] == 'error' || $entry['type'] == 'failed') {
    file_put_contents(drush_get_option('drupal-org-build-root') . '/' . DRUSH_MAKE_DO_BUILD_ERRORS_FILE, $entry['message'] . "\n", FILE_APPEND);
  }
}
