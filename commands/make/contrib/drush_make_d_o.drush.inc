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
  if (!drush_make_ensure_version()) {
    return;
  }
  $info = drush_make_validate_info_file(drush_make_parse_info_file($makefile));
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
    $transformers = array(
      'top' => array(
        'DrushMakeDo_TopWhitelist',
        'DrushMakeDo_CoreVersion',
      ),
      'project' => array(
        'DrushMakeDo_ProjectVersion',
        'DrushMakeDo_ProjectWhitelist',
        'DrushMakeDo_ProjectPatch',
        'DrushMakeDo_ProjectUpdateXML',
      ),
    );
    $pass = TRUE;
    foreach ($transformers['top'] as $transformer) {
      $object = new $transformer('make');
      $pass = $pass && $object->verify($info);
    }

    if (!empty($info['projects'])) {
      foreach ($info['projects'] as $project => $project_data) {
        foreach ($transformers['project'] as $transformer) {
          $object = new $transformer('make');
          $pass = $pass && $object->verify($project_data, $project, $info['core']);
        }
        $info['projects'][$project] = $project_data;
      }
    }

    // Abort if any errors were found.
    if (!$pass) {
      drush_make_error('BUILD_ERROR', dt('The drupal.org validation check failed -- see @doc_link for more information.', array('@doc_link' => DRUSH_MAKE_DO_DOCUMENTATION_LINK)));
      return FALSE;
    }
  }
  return $info;
}

abstract class DrushMakeDo_Transformer {
  protected function log($message) {
    drush_make_error('BUILD_ERROR', $message);
  }
  protected function latestVersion($project_name, $version, $core) {
    // Project types that are valid for the version checker.
    $allowed_project_types = array(
      'core',
      'module',
      'theme',
    );
    $project = array(
      'name'           => $project_name,
      'core'           => $core,
      'version'        => $version,
      'location'       => drush_get_option('drush-make-update-default-url'),
    );
    $project = drush_make_updatexml($project);

    // Because the version data we get back from the XML may not be the same
    // data we passed, and because we can fail even if version data is
    // returned, track both the success/failure of the request, and  the
    // version data.

    // Check for bad projects and branch releases.
    if (empty($project) || preg_match('/.*-dev$/', $project['version'])) {
      // Use the passed version if no version was returned.
      if (empty($project)) {
        $final_version = $version;
      }
      else {
        $final_version = $project['version'];
      }
      if ($project_name == 'drupal') {
        drush_make_error('BUILD_ERROR', dt("Drupal core does not have an official release for version '%version' yet.", array('%version' => $final_version)));
      }
      else {
        drush_make_error('BUILD_ERROR', dt("Project '%project' does not have an official release for version '%version'.", array('%project' => $project_name, '%version' => $final_version)));
      }
      $result = FALSE;
    }
    // Make sure the project is an allowed project type.
    elseif (!in_array($project['type'], $allowed_project_types)) {
      drush_make_error('BUILD_ERROR', dt("Project %project of type '%type' is not permitted.", array('%project' => $project_name, '%type' => $project['type'])));
      $final_version = $project['version'];
      $result = FALSE;
    }
    else {
      $final_version = $project['version'];
      $result = TRUE;
    }
    return $result ? $final_version : FALSE;
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
  protected function validVersion($name, $version_string) {
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
}

class DrushMakeDo_TopWhitelist extends DrushMakeDo_Transformer {
  // The list of currently allowed top-leval attributes.
  protected $attribute_whitelist = array('core', 'api', 'core_release', 'projects');
  protected $level = 'top';

  function verify(&$makefile, $name = NULL) {
    $pass = TRUE;
    // Check for disallowed top-level attributes.
    foreach ($makefile as $attribute => $attribute_data) {
      if (!in_array($attribute, $this->attribute_whitelist)) {
        if ($this->level == 'top') {
          $this->log(dt("The top-level attribute '%attribute' is not allowed.", array('%attribute' => $attribute)));
        }
        else {
          $this->log(dt("The project-level attribute '%attribute' on project '%project' is not allowed.", array('%attribute' => $attribute, '%project' => $name)));
        }
        $pass = FALSE;
      }
    }
    return $pass;
  }
  function convert($makefile) {
    // Check for disallowed top-level attributes.
    foreach ($makefile as $attribute => $attribute_data) {
      if (!in_array($attribute, $this->attribute_whitelist)) {
        unset($makefile[$attribute]);
      }
    }
    return $makefile;
  }
}
class DrushMakeDo_CoreVersion extends DrushMakeDo_Transformer {
  function verify(&$makefile) {
    return $this->validVersion(dt('Drupal core'), $makefile['core_release']);
  }
  function convert($makefile) {
    // First order of business: convert core version.
    $parts = explode('.', $makefile['core']);
    $core_major = $parts[0];
    $core = $core_major . '.x';

    // Check for a stable release on the branch.
    $final_version = $this->latestVersion('drupal', $core_major, $core);
    if ($final_version) {
      $makefile['core'] = $final_version;
      return $makefile;
    }
    return FALSE;
  }
}
class DrushMakeDo_ProjectVersion extends DrushMakeDo_Transformer {
  function verify(&$project_data, $project) {
    $pass = TRUE;
    if (!is_array($project_data)) {
      $project_data = array('version' => $project_data);
    }
    // Version must be set.
    if (!isset($project_data['version'])) {
      drush_make_error('BUILD_ERROR', dt('No version specified for project %project -- version is required.', array('%project' => $project)));
      $pass = FALSE;
    }
    // Check for bad project version.
    if (!$this->validVersion($project, $project_data['version'])) {
      $pass = FALSE;
    }
    return $pass;
  }
  function convert($makefile) {
    
  }
}
class DrushMakeDo_ProjectWhitelist extends DrushMakeDo_TopWhitelist {
  protected $attribute_whitelist = array('version', 'subdir', 'patch');
  protected $level = 'project';
}
class DrushMakeDo_ProjectPatch extends DrushMakeDo_Transformer {
  function verify(&$project_data, $project) {
    // Check that patches do in fact come from drupal.org.
    if (isset($project_data['patch'])) {
      foreach ($project_data['patch'] as $patch) {
        if (strpos($patch, 'http://drupal.org/files/issues') !== 0) {
          drush_make_error('BUILD_ERROR', dt("The patch '%patch' is not hosted on drupal.org", array('%patch' => $patch)));
          $errors = TRUE;
        }
      }
    }
    return TRUE;
  }
  function convert($makefile) {
    
  }
}
class DrushMakeDo_ProjectUpdateXML extends DrushMakeDo_Transformer {
  function verify(&$project_data, $project, $core) {
    $project_data += array(
      'name' => $project,
      'core' => $core,
    );
    $project_data = drush_make_d_o_updatexml($project_data);
    return $project_data;
  }
  function convert($makefile) {
    
  }
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
  $project['release_history'] = $release_history;
  if (!isset($project['type'])) {
    // Determine the project type.
    $term_map = array(
      'Modules' => 'module',
      'Themes' => 'theme',
      'Drupal core' => 'core',
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

/**
 * Custom logging function for conversions going to STDOUT.
 *
 * Drush doesn't output drush_set_error() messages to STDERR, lame-o...  :(
 *
 * This helper function overcomes that deficiency by manually writing error
 * messages to STDERR in the case where output is going to STDOUT.
 */
function drush_make_d_o_convert_log_stdout_handler($entry) {
  if ($entry['type'] == 'error' || $entry['type'] == 'failed') {
    fwrite(STDERR, dt("ERROR") . ": " .  $entry['message'] . "\n");
  }
}
