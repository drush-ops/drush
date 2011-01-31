<?php
// $Id$

include_once 'includes/install.inc';
include_once drupal_get_path('module', 'system') . '/system.install';

/**
 * Drush callback; generate makefile from the current build.
 */
function _drush_make_generate($file) {
  // What projects should we pin the versions for?
  // Check the command-line options for details
  foreach(array("include", "exclude") as $option) {
    $version_options[$option] = drush_get_option("$option-versions");
    if ($version_options[$option] !== TRUE) {
      $version_options[$option] = array_filter(split(",", $version_options[$option]));
    }
  }

  $projects = array();

  $system_requirements = system_requirements('runtime');
  $core_project = strtolower($system_requirements['drupal']['title']); 
  $projects[$core_project] = array('_type' => 'core');
  if ($core_project != 'drupal') {
    $projects[$core_project]['custom_download'] = TRUE;
    $projects[$core_project]['type'] = 'core';
  }
  else {
    // Drupal core - we can determine the version if required
    if (_drush_generate_track_version("drupal", $version_options)) {
      $projects[$core_project]["version"] = $system_requirements['drupal']['value'];
    }
  }

  // Non-default profiles section.
  $install_profile = variable_get('install_profile', '');
  if ($install_profile != 'default' && $install_profile != '') {
    $projects[$install_profile]['type'] =
    $projects[$install_profile]['_type'] = 'profile';
    if (_drush_generate_makefile_updatexml($install_profile, 'profile')) {
      $projects[$install_profile]['custom_download'] = TRUE;
    }
  }

  // Re-build theme and module data to be sure we have the most current information.
  $project_info = drush_get_projects();
  foreach ($project_info as $item) {
    $name = $item->name;
    $type = $item->type;
    if ($item->info['project'] != 'drupal' && $item->info['project'] == $name) {
      if (($item->type == 'module') && (!$item->status)) {
        continue;
      }
      $updatexml = _drush_generate_makefile_updatexml($name, $type, $item->info);
      $projects[$project_name] = array('_type' => $type);
      if ($updatexml) {
        $projects[$project_name]['type'] = $type;
        $projects[$project_name]['custom_download'] = TRUE;
      }
      $projects[$project_name] += _drush_generate_makefile_check_location($name, $type);
      // Add version number if this project's version is to be tracked
      // and if we have the information in its ->info to determine it
      if (_drush_generate_track_version($name, $version_options) && $item->info["version"]) {
        $projects[$project_name]['version'] = preg_replace("/^". DRUPAL_CORE_COMPATIBILITY . "-/", "", $item->info["version"]);
      }
    }
  }

  if (function_exists('libraries_get_libraries')) {
    $libraries = libraries_get_libraries();
    foreach ($libraries as $library_name => $library_path) {
      $path = explode('/', $library_path);
      $projects[$library_name] = array(
        'directory_name' => $path[(count($path) - 1)],
        'custom_download' => TRUE,
        'type' => 'library',
        '_type' => 'librarie', // For plural.
      );
    }
  }

  $contents = _drush_make_generate_makefile_contents($projects);
  if (file_put_contents($file, $contents)) {
    drush_log(dt("Wrote .make file %file", array('%file' => $file)), 'ok');
  }
  else {
    drush_make_error('FILE_ERROR', dt("Unable to write .make file %file", array('%file' => $file)));
  }
}

/**
 * Helper function to determine if a given project is to have its version tracked
 */
function _drush_generate_track_version($project, $version_options) {
  // A. If --exclude-versions has been specified:
  //  A.a. if it's a boolean, check the --include-versions option
  if ($version_options["exclude"] === TRUE) {
    // A.a.1 if --include-versions has been specified, ensure it's an array
    // 
    if (is_array($version_options["include"])) {
      return in_array($project, $version_options["include"]);
    }
    // A.a.2 If no include array, then we're excluding versions for ALL projects
    return FALSE;
  }
  //  A.b. if --exclude-versions is an array with items, check this project is in it: if so,
  //    then return FALSE.
  elseif (is_array($version_options["exclude"]) && count($version_options["exclude"])) {
    return !in_array($project, $version_options["exclude"]);
  }

  // B. If by now no --exclude-versions, but --include-versions is an array,
  // examine it for this project
  if (is_array($version_options["include"]) && count($version_options["include"])) {
    return in_array($project, $version_options["include"]);
  }

  // If none of the above conditions match, include version number by default
  return TRUE;
}

/**
 * Helper function to check if a detected project lives on drupal.org or not.
 */
function _drush_generate_makefile_updatexml($name, $type, $info = array()) {
  // First we set up the project information array.
  $project = array(
    'name' => $name,
    'location' => (isset($info['location']) ? $info['location'] : drush_get_option('drush-make-update-default-url')),
    'core' => DRUPAL_CORE_COMPATIBILITY,
    'version' => DRUSH_MAKE_VERSION_BEST,
  );

  // Now we get the project information.
  $update_check = drush_make_updatexml($project, NULL);
  return $update_check === TRUE && $type == $update_check['type'];
}

/**
 * Helper function to check for a non-default installation location.
 */
function _drush_generate_makefile_check_location($name, $type) {
  // Check for non-default installation location.
  $path = drupal_get_path($type, $name);
  $default_location = 'sites/all/' . $type . 's';
  $info = array();
  if ($path != $default_location . '/' . $name) {
    if (substr($path, 0, strlen($default_location)) != $default_location) {
      $info['contrib_destination'] = $path;
    }
    else {
      // If it's in a subdir of sites/all/modules, set the subdir.
      $subdir = preg_replace(array('@^sites/all/' . $type . 's/@', "@/$name" . '$@'), '', $path);
      $info['subdir'] = $subdir;
    }
  }
  return $info;
}

function _drush_make_generate_makefile_contents($projects) {
  $output = array();
  $custom = FALSE;
  $output[] = '; This file was auto-generated by drush_make';
  $output['core'] = DRUPAL_CORE_COMPATIBILITY;
  $output[] = '';
  $output['api'] = DRUSH_MAKE_API;
  $previous_type = 'core';
  foreach ($projects as $name => $project) {
    $type = ($project['type'] == 'library' ? 'libraries' : 'projects');
    if ($previous_type != $project['_type']) {
      $previous_type = $project['_type'];
      $output[] = '; ' . ucfirst($previous_type) . 's';
    }
    unset($project['_type']);
    if (!$project && is_string($name)) {
      $output[] = $type . '[] = "' . $name . '"';
      continue;
    }
    $base = $type . '[' . $name . ']';
    if (isset($project['custom_download'])) {
      $custom = TRUE;
      $output[] = '; Please fill the following out. Type may be one of get, cvs, git, bzr or svn,';
      $output[] = '; and url is the url of the download.';
      $output[$base . '[download][type]'] = '""';
      $output[$base . '[download][url]'] = '""';
      unset($project['custom_download']);
    }
    foreach ($project as $key => $value) {
      $output[$base . '[' . $key . ']'] = '"' . $value . '"';
    }
    $output[] = '';
  }
  $string = '';
  foreach ($output as $k => $v) {
    if (!is_numeric($k)) {
      $string .= $k . ' = ' . $v;
    }
    else {
      $string .= $v;
    }
    $string .= "\n";
  }
  if ($custom) {
    drush_log(dt('Some of the properties in your makefile will have to be manually edited. Please do that now.'), 'error');
  }
  return $string;
}
