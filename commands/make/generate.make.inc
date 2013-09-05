<?php
/**
 * @file
 * Functions for the generate makefile command.
 */

include_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
include_once drupal_get_path('module', 'system') . '/system.install';

/**
 * Drush callback; generate makefile from the current build.
 */
function drush_make_generate($file = NULL) {
  $version_options = _drush_make_generate_get_version_options();
  $all_extensions = drush_get_extensions();
  list($projects, $libraries) = _drush_make_generate_projects($all_extensions, $version_options);
  $contents = _drush_make_generate_makefile_contents($projects, $libraries);

  if (!$file) {
    drush_print($contents);
  }
  elseif (file_put_contents($file, $contents)) {
    drush_log(dt("Wrote .make file @file", array('@file' => $file)), 'ok');
  }
  else {
    drush_make_error('FILE_ERROR', dt("Unable to write .make file !file", array('!file' => $file)));
  }
}

/**
 * Create the $version_options array from the --include-versions and
 * --exclude-versions command line options.
 */
function _drush_make_generate_get_version_options() {
  // What projects should we pin the versions for?
  // Check the command-line options for details.
  foreach (array("include", "exclude") as $option) {
    $version_options[$option] = drush_get_option("$option-versions");
    if ($version_options[$option] !== TRUE) {
      $version_options[$option] = array_filter(explode(",", $version_options[$option]));
    }
  }
  return $version_options;
}

/**
 * Generate the $projects makefile array for the current site.
 */
function _drush_make_generate_projects($all_extensions, $version_options) {
  $projects = array();
  $project_libraries = array();

  $system_requirements = system_requirements('runtime');
  // Update xml expects the drupal version to be expressed as "7.x" or "8.x"
  // We used to check $system_requirements['drupal']['value'], but this now
  // contains values such as "7.10-dev".
  $drupal_major_version = drush_drupal_major_version() . '.x';
  $core_project = strtolower($system_requirements['drupal']['title']);
  $projects[$core_project] = array('_type' => 'core');
  if ($core_project != 'drupal') {
    $projects[$core_project]['custom_download'] = TRUE;
    $projects[$core_project]['type'] = 'core';
  }
  else {
    // Drupal core - we can determine the version if required.
    if (_drush_generate_track_version("drupal", $version_options)) {
      $projects[$core_project]["version"] = drush_drupal_version();
    }
  }

  // Non-default profiles section.
  $install_profile = variable_get('install_profile', '');
  if (!in_array($install_profile, array('default', 'standard', 'minimal', 'testing')) && $install_profile != '') {
    $projects[$install_profile]['type']
      = $projects[$install_profile]['_type'] = 'profile';
    $request = array(
      'name' => $install_profile,
      'drupal_version' => $drupal_major_version,
    );
    if (!release_info_check_project($request, 'profile')) {
      $projects[$install_profile]['custom_download'] = TRUE;
    }
  }

  // Iterate installed projects to build $projects array.
  $extensions = $all_extensions;
  $project_info = drush_get_projects($extensions);
  foreach ($project_info as $name => $project) {
    // Discard the extensions within this project. At the end $extensions will
    // contain only extensions part of custom projects (not from drupal.org or
    // other update service).
    foreach ($project['extensions'] as $ext) {
      unset($extensions[$ext]);
    }
    if ($name == 'drupal') {
      continue;
    }
    $type = $project['type'];
    // Discard projects with all modules disabled.
    if (($type == 'module') && (!$project['status'])) {
      continue;
    }
    $projects[$name] = array('_type' => $type);
    // Check the project is on drupal.org or its own update service.
    $request = array(
      'name' => $name,
      'drupal_version' => $drupal_major_version,
    );
    if (isset($project['status url'])) {
      $request['status url'] = $project['status url'];
      $projects[$name]['location'] = $status_url;
    }
    if (!release_info_check_project($request, $type)) {
      // It is not a project on drupal.org neither an external update service.
      $projects[$name]['type'] = $type;
      $projects[$name]['custom_download'] = TRUE;
    }
    // Add 'subdir' if the project is installed in a non-default location.
    if (isset($project['path'])) {
      $projects[$name] += _drush_generate_makefile_check_path($project);
    }
    // Add version number if this project's version is to be tracked.
    if (_drush_generate_track_version($name, $version_options) && $project["version"]) {
      $projects[$name]['version'] = preg_replace("/^" . drush_get_drupal_core_compatibility() . "-/", "", $project["version"]);
    }
    foreach ($project['extensions'] as $extension_name) {
      _drush_make_generate_add_patch_files($projects[$name], dirname($all_extensions[$extension_name]->filename));
    }
  }

  // Add a project for each unknown extension.
  foreach ($extensions as $name => $extension) {
    list($project_name, $project_data) = _drush_generate_custom_project($name, $extension, $version_options);
    $projects[$project_name] = $project_data;
  }

  // Add libraries.
  if (function_exists('libraries_get_libraries')) {
    $libraries = libraries_get_libraries();
    foreach ($libraries as $library_name => $library_path) {
      $path = explode('/', $library_path);
      $project_libraries[$library_name] = array(
        'directory_name' => $path[(count($path) - 1)],
        'custom_download' => TRUE,
        'type' => 'library',
        '_type' => 'librarie', // For plural.
      );
    }
  }
  return array($projects, $project_libraries);
}

/**
 * Record any patches that were applied to this project
 * per information stored in PATCHES.txt.
 */
function _drush_make_generate_add_patch_files(&$project, $location) {
  $patchfile = DRUPAL_ROOT . '/' . $location . '/PATCHES.txt';
  if (is_file($patchfile)) {
    foreach (file($patchfile) as $line) {
      if (substr($line, 0, 2) == '- ') {
        $project['patches'][] = trim(substr($line, 2));
      }
    }
  }
}

/**
 * Create a project record for an extension not downloaded from drupal.org
 */
function _drush_generate_custom_project($name, $extension, $version_options) {
  $project['_type'] = $extension->type;
  $project['type'] = $extension->type;
  $info_file = $extension->filename;
  $location = DRUPAL_ROOT . '/' . dirname($info_file);
  // To start off, we will presume that our custom extension is
  // stored in a folder named after its project, and there are
  // no subfolders between the .info file and the project root.
  $project_name = basename($location);
  drush_shell_cd_and_exec($location, 'git rev-parse --git-dir 2> ' . drush_bit_bucket());
  $output = drush_shell_exec_output();
  if (!empty($output)) {
    $git_dir = $output[0];
    // Find the actual base of the git repository.
    $repo_root = $git_dir == ".git" ? $location : dirname($git_dir);
    // If the repository root is at the drupal root or some parent
    // of the drupal root, or some other location that could not
    // pausibly be a project, then there is nothing we can do.
    // (We can't tell Drush make to download some sub-part of a repo,
    // can we?)
    if ($repo_project_name = _drush_generate_validate_repo_location($repo_root)) {
      $project_name = $repo_project_name;
      drush_shell_cd_and_exec($repo_root, 'git remote show origin');
      $output = drush_shell_exec_output();
      foreach ($output as $line) {
        if (strpos($line, "Fetch URL:") !== FALSE) {
          $url = preg_replace('/ *Fetch URL: */', '', $line);
          if (!empty($url)) {
            // We use the unconventional-looking keys
            // `download][type` and `download][url` so that
            // we can produce output that appears to be two-dimensional
            // arrays from a single-dimensional array.
            $project['download][type'] = 'git';
            $project['download][url'] = $url;

            // Fill in the branch as well.
            drush_shell_cd_and_exec($repo_root, 'git branch');
            $output = drush_shell_exec_output();
            foreach ($output as $line) {
              if ($line{0} == '*') {
                $branch = substr($line, 2);
                if ($branch != "master") {
                  $project['download][branch'] = $branch;
                }
              }
            }

            // Put in the commit hash.
            drush_shell_cd_and_exec($repo_root, 'git log');
            $output = drush_shell_exec_output();
            if (substr($output[0], 0, 7) == "commit ") {
              $revision = substr($output[0], 7);
              if (_drush_generate_track_version($project_name, $version_options)) {
                $project['download][revision'] = $revision;
              }
            }

            // Add patch files, if any.
            _drush_make_generate_add_patch_files($project, $repo_root);
          }
        }
      }
    }
  }
  // If we could not figure out where the extension came from, then give up and
  // flag it as a "custom" download.
  if (!isset($project['download][type'])) {
    $project['custom_download'] = TRUE;
  }
  return array($project_name, $project);
}

/**
 * If the user has checked in the Drupal root, or the 'sites/all/modules'
 * folder into a git repository, then we do not want to confuse that location
 * with a "project".
 */
function _drush_generate_validate_repo_location($repo_root) {
  $project_name = basename($repo_root);
  // The Drupal root, or any folder immediately inside the Drupal
  // root cannot be a project location.
  if ((strlen(DRUPAL_ROOT) >= strlen($repo_root)) || (dirname($repo_root) == DRUPAL_ROOT)) {
    return NULL;
  }
  // Also exclude sites/* and sites/*/{modules,themes} and profile/* and
  // profile/*/{modules,themes}.
  return $project_name;
}

/**
 * Helper function to determine if a given project is to have its version
 * tracked.
 */
function _drush_generate_track_version($project, $version_options) {
  // A. If --exclude-versions has been specified:
  // A.a. if it's a boolean, check the --include-versions option.
  if ($version_options["exclude"] === TRUE) {
    // A.a.1 if --include-versions has been specified, ensure it's an array.
    if (is_array($version_options["include"])) {
      return in_array($project, $version_options["include"]);
    }
    // A.a.2 If no include array, then we're excluding versions for ALL
    // projects.
    return FALSE;
  }
  // A.b. if --exclude-versions is an array with items, check this project is in
  // it: if so, then return FALSE.
  elseif (is_array($version_options["exclude"]) && count($version_options["exclude"])) {
    return !in_array($project, $version_options["exclude"]);
  }

  // B. If by now no --exclude-versions, but --include-versions is an array,
  // examine it for this project.
  if (is_array($version_options["include"]) && count($version_options["include"])) {
    return in_array($project, $version_options["include"]);
  }

  // If none of the above conditions match, include version number by default.
  return TRUE;
}

/**
 * Helper function to check for a non-default installation location.
 */
function _drush_generate_makefile_check_path($project) {
  $info = array();
  $type = $project['type'];
  $path = dirname($project['path']);
  // Check to see if the path is in a subdir sites/all/modules or
  // profiles/profilename/modules
  if (preg_match('@^sites/[a-zA-Z0-9_]*/' . $type . 's/..*@', $path) || preg_match('@^sites/[a-zA-Z0-9_]*/' . $type . 's/..*@', $path)) {
    $subdir = preg_replace(array('@^[a-zA-Z0-9_]*/[a-zA-Z0-9_]*/' . $type . 's/*@', "@/$name" . '$@'), '', $path);
    if (!empty($subdir)) {
      $info['subdir'] = $subdir;
    }
  }
  return $info;
}

/**
 * Generate the actual contents of the .make file.
 */
function _drush_make_generate_makefile_contents($projects, $libraries = array()) {
  $header = array();
  $header[] = '; This file was auto-generated by drush make';
  $header['core'] = drush_get_drupal_core_compatibility();
  $header[] = '';
  $header['api'] = MAKE_API;

  return _drush_make_generate_makefile_body($projects, $header) . _drush_make_generate_makefile_body($libraries);
}

function _drush_make_generate_makefile_body($projects, $output = array()) {
  $custom = FALSE;
  $previous_type = 'core';
  foreach ($projects as $name => $project) {
    $type = (isset($project['type']) && ($project['type'] == 'library')) ? 'libraries' : 'projects';
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
      $output[] = '; Please fill the following out. Type may be one of get, git, bzr or svn,';
      $output[] = '; and url is the url of the download.';
      $output[$base . '[download][type]'] = '""';
      $output[$base . '[download][url]'] = '""';
      unset($project['custom_download']);
    }
    foreach ($project as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $item) {
          $output[$base . '[' . $key . '][]'] = '"' . $item . '"';
        }
      }
      else {
        $output[$base . '[' . $key . ']'] = '"' . $value . '"';
      }
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
    drush_log(dt('Some of the properties in your makefile will have to be manually edited. Please do that now.'), 'warning');
  }
  return $string;
}
