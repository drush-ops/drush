<?php
/**
 * @file
 * General utility functions for Drush Make.
 */

/**
 * Parse Drupal info file format.
 *
 * Copied with modifications from includes/common.inc.
 *
 * @see drupal_parse_info_file
 */
function make_parse_info_file($makefile, $parsed = TRUE) {
  if (!($data = make_get_data($makefile))) {
    return drush_set_error(dt('Invalid or empty make file: !makefile', array('!makefile' => $makefile)));
  }
  if (!($info = _drush_drupal_parse_info_file($data))) {
    return FALSE;
  }
  if (!empty($info['includes'])) {
    $include_path = dirname($makefile);
    $includes = array();
    if (!empty($info['includes']) && is_array($info['includes'])) {
      foreach ($info['includes'] as $include) {
        if (is_string($include)) {
          if (make_valid_url($include, TRUE) && ($file = make_parse_info_file($include, FALSE))) {
            $includes[] = $file;
          }
          elseif (file_exists($include_path . '/' . $include) && ($file = make_parse_info_file($include_path . '/' . $include, FALSE))) {
            $includes[] = $file;
          }
          elseif (file_exists($include) && ($file = make_parse_info_file($include, FALSE))) {
            $includes[] = $file;
          }
          else {
            make_error('BUILD_ERROR', dt("Include file missing: !include", array('!include' => $include)));
          }
        }
      }
    }
    $includes[] = $data;
    $data = implode("\n", $includes);
    $info = _drush_drupal_parse_info_file($data);
  }
  if ($parsed) {
    return $info;
  }
  else {
    return $data;
  }
}

/**
 * Remove entries in the info file in accordance with the options passed in.
 * Entries are either explicitly 'allowed' (with the $include_only parameter) in
 * which case all *other* entries will be excluded.
 *
 * @param array $info
 *   A parsed info file.
 *
 * @param array $include_only
 *   (Optional) Array keyed by entry type (e.g. 'libraries') against an array of
 *   allowed keys for that type. The special value '*' means 'all entries of
 *   this type'. If this parameter is omitted, no entries will be excluded.
 *
 * @return array
 *   The $info array, pruned if necessary.
 */
function make_prune_info_file($info, $include_only = array()) {

  // We may get passed FALSE in some cases.
  // Also we cannot prune an empty array, so no point in this code running!
  if (empty($info)) {
    return $info;
  }

  // We will accrue an explanation of our activities here.
  $msg = array();
  $msg['scope'] = dt("Drush make restricted to the following entries:");

  $pruned = FALSE;

  if (count(array_filter($include_only))) {
    $pruned = TRUE;
    foreach ($include_only as $type => $keys) {

      if (!isset($info[$type])) {
        continue;
      }
      // For translating
      // dt("Projects");
      // dt("Libraries");
      $type_title = dt(ucfirst($type));

      // Handle the special '*' value.
      if (in_array('*', $keys)) {
        $msg[$type] = dt("!entry_type: <All>", array('!entry_type' => $type_title));
      }

      // Handle a (possibly empty) array of keys to include/exclude.
      else {
        $info[$type] = array_intersect_key($info[$type], array_fill_keys($keys, 1));
        unset($msg[$type]);
        if (!empty($info[$type])) {
          $msg[$type] = dt("!entry_type: !make_entries", array('!entry_type' => $type_title, '!make_entries' => implode(', ', array_keys($info[$type]))));
        }
      }
    }
  }

  if ($pruned) {
    // Make it clear to the user what's going on.
    drush_log(implode("\n", $msg), 'ok');

    // Throw an error if these restrictions reduced the make to nothing.
    if (empty($info['projects']) && empty($info['libraries'])) {
      // This error mentions the options explicitly to make it as clear as
      // possible to the user why this error has occurred.
      make_error('BUILD_ERROR', dt("All projects and libraries have been excluded. Review the 'projects' and 'libraries' options."));
    }
  }

  return $info;
}

/**
 * Validate the make file.
 */
function make_validate_info_file($info) {
  // Assume no errors to start.
  $errors = FALSE;

  if (empty($info['core'])) {
    make_error('BUILD_ERROR', dt("The 'core' attribute is required"));
    $errors = TRUE;
  }
  // Standardize on core.
  elseif (preg_match('/^(\d+)(\.(x|(\d+)(-[a-z0-9]+)?))?$/', $info['core'], $matches)) {
    // An exact version of core has been specified, so pass that to an
    // internal variable for storage.
    if (isset($matches[4])) {
      $info['core_release'] = $info['core'];
    }
    // Format the core attribute consistently.
    $info['core'] = $matches[1] . '.x';
  }
  else {
    make_error('BUILD_ERROR', dt("The 'core' attribute !core has an incorrect format.", array('!core' => $info['core'])));
    $errors = TRUE;
  }

  if (!isset($info['api'])) {
    $info['api'] = MAKE_API;
    drush_log(dt("You need to specify an API version of two in your makefile:\napi = !api", array("!api" => MAKE_API)), 'warning');
  }
  elseif ($info['api'] != MAKE_API) {
    make_error('BUILD_ERROR', dt("The specified API attribute is incompatible with this version of Drush Make."));
    $errors = TRUE;
  }

  $names = array();

  // Process projects.
  if (isset($info['projects'])) {
    if (!is_array($info['projects'])) {
      make_error('BUILD_ERROR', dt("'projects' attribute must be an array."));
      $errors = TRUE;
    }
    else {
      // Filter out entries that have been forcibly removed via [foo] = FALSE.
      $info['projects'] = array_filter($info['projects']);

      foreach ($info['projects'] as $project => $project_data) {
        // Project has an attributes array.
        if (is_string($project) && is_array($project_data)) {
          if (in_array($project, $names)) {
            make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          foreach ($project_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('contrib_destination'))) {
              unset($info['projects'][$project][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the
            // filesystem.
            elseif (in_array($attribute, array('subdir', 'directory_name')) && !make_safe_path($value)) {
              $args = array(
                '!path' => $value,
                '!attribute' => $attribute,
                '!project' => $project,
              );
              make_error('BUILD_ERROR', dt("Illegal path !path for '!attribute' attribute in project !project.", $args));
              $errors = TRUE;
            }
          }
        }
        // Cover if there is no project info, it's just a project name.
        elseif (is_numeric($project) && is_string($project_data)) {
          if (in_array($project_data, $names)) {
            make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project_data)));
            $errors = TRUE;
          }
          $names[] = $project_data;
          unset($info['projects'][$project]);
          $info['projects'][$project_data] = array();
        }
        // Convert shorthand project version style to array format.
        elseif (is_string($project_data)) {
          if (in_array($project, $names)) {
            make_error('BUILD_ERROR', dt("Project !project defined twice (remove the first projects[] = !project).", array('!project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          $info['projects'][$project] = array('version' => $project_data);
        }
        else {
          make_error('BUILD_ERROR', dt('Project !project incorrectly specified.', array('!project' => $project)));
          $errors = TRUE;
        }
      }
    }
  }
  if (isset($info['libraries'])) {
    if (!is_array($info['libraries'])) {
      make_error('BUILD_ERROR', dt("'libraries' attribute must be an array."));
      $errors = TRUE;
    }
    else {
      // Filter out entries that have been forcibly removed via [foo] = FALSE.
      $info['libraries'] = array_filter($info['libraries']);

      foreach ($info['libraries'] as $library => $library_data) {
        if (is_array($library_data)) {
          foreach ($library_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('contrib_destination'))) {
              unset($info['libraries'][$library][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the
            // filesystem.
            elseif (in_array($attribute, array('contrib-destination', 'directory_name')) && !make_safe_path($value)) {
              $args = array(
                '!path' => $value,
                '!attribute' => $attribute,
                '!library' => $library,
              );
              make_error('BUILD_ERROR', dt("Illegal path !path for '!attribute' attribute in library !library.", $args));
              $errors = TRUE;
            }
          }
        }
      }
    }
  }

  // Apply defaults after projects[] array has been expanded, but prior to
  // external validation.
  make_apply_defaults($info);

  foreach (drush_command_implements('make_validate_info') as $module) {
    $function = $module . '_make_validate_info';
    $return = $function($info);
    if ($return) {
      $info = $return;
    }
    else {
      $errors = TRUE;
    }
  }

  if ($errors) {
    return FALSE;
  }
  return $info;
}

/**
 * Verify the syntax of the given URL.
 *
 * Copied verbatim from includes/common.inc
 *
 * @see valid_url
 */
function make_valid_url($url, $absolute = FALSE) {
  if ($absolute) {
    return (bool) preg_match("
      /^                                                      # Start at the beginning of the text
      (?:ftp|https?):\/\/                                     # Look for ftp, http, or https schemes
      (?:                                                     # Userinfo (optional) which is typically
        (?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*      # a username or a username and password
        (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@          # combination
      )?
      (?:
        (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
        |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
      )
      (?::[0-9]+)?                                            # Server port number (optional)
      (?:[\/|\?]
        (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
      *)?
    $/xi", $url);
  }
  else {
    return (bool) preg_match("/^(?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})+$/i", $url);
  }
}

/**
 * Find, and possibly create, a temporary directory.
 *
 * @param boolean $set
 *   Must be TRUE to create a directory.
 * @param string $directory
 *   Pass in a directory to use. This is required if using any
 *   concurrent operations.
 *
 * @todo Merge with drush_tempdir().
 */
function make_tmp($set = TRUE, $directory = NULL) {
  static $tmp_dir;

  if (isset($directory) && !isset($tmp_dir)) {
    $tmp_dir = $directory;
  }

  if (!isset($tmp_dir) && $set) {
    $tmp_dir = drush_find_tmp();
    if (strrpos($tmp_dir, '/') == strlen($tmp_dir) - 1) {
      $tmp_dir .= 'make_tmp_' . time() . '_' . uniqid();
    }
    else {
      $tmp_dir .= '/make_tmp_' . time() . '_' . uniqid();
    }
    if (!drush_get_option('no-clean', FALSE)) {
      drush_register_file_for_deletion($tmp_dir);
    }
    if (file_exists($tmp_dir)) {
      return make_tmp(TRUE);
    }
    // Create the directory.
    drush_mkdir($tmp_dir);
  }
  return $tmp_dir;
}

/**
 * Removes the temporary build directory. On failed builds, this is handled by
 * drush_register_file_for_deletion().
 */
function make_clean_tmp() {
  if (!($tmp_dir = make_tmp(FALSE))) {
    return;
  }
  if (!drush_get_option('no-clean', FALSE)) {
    drush_delete_dir($tmp_dir);
  }
  else {
    drush_log(dt('Temporary directory: !dir', array('!dir' => $tmp_dir)), 'ok');
  }
}

/**
 * Prepare a Drupal installation, copying default.settings.php to settings.php.
 */
function make_prepare_install($build_path) {
  $default = make_tmp() . '/__build__/sites/default';
  drush_copy_dir($default . DIRECTORY_SEPARATOR . 'default.settings.php', $default . DIRECTORY_SEPARATOR . 'settings.php', TRUE);
  drush_mkdir($default . '/files');
  chmod($default . DIRECTORY_SEPARATOR . 'settings.php', 0666);
  chmod($default . DIRECTORY_SEPARATOR . 'files', 0777);
}

/**
 * Calculate a cksum on each file in the build, and md5 the resulting hashes.
 */
function make_md5() {
  return drush_dir_md5(make_tmp());
}

/**
 * @todo drush_archive_dump() also makes a tar. Consolidate?
 */
function make_tar($build_path) {
  $tmp_path = make_tmp();

  drush_mkdir(dirname($build_path));
  $filename = basename($build_path);
  $dirname = basename($build_path, '.tar.gz');
  // Move the build directory to a more human-friendly name, so that tar will
  // use it instead.
  drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . '__build__', $tmp_path . DIRECTORY_SEPARATOR . $dirname, TRUE);
  // Only move the tar file to it's final location if it's been built
  // successfully.
  if (drush_shell_exec("%s -C %s -Pczf %s %s", drush_get_tar_executable(), $tmp_path, $tmp_path . '/' . $filename, $dirname)) {
    drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . $filename, $build_path, TRUE);
  };
  // Move the build directory back to it's original location for consistency.
  drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . $dirname, $tmp_path . DIRECTORY_SEPARATOR . '__build__');
}

/**
 * Logs an error unless the --force-complete command line option is specified.
 */
function make_error($error_code, $message) {
  if (drush_get_option('force-complete')) {
    drush_log("$error_code: $message -- build forced", 'warning');
  }
  else {
    drush_set_error($error_code, $message);
  }
}

/**
 * Checks an attribute's path to ensure it's not maliciously crafted.
 *
 * @param string $path
 *   The path to check.
 */
function make_safe_path($path) {
  return !preg_match("+^/|^\.\.|/\.\./+", $path);
}
/**
 * Get data based on the source.
 *
 * This is a helper function to abstract the retrieval of data, so that it can
 * come from files, STDIN, etc.  Currently supports filepath and STDIN.
 *
 * @param string $data_source
 *   The path to a file, or '-' for STDIN.
 *
 * @return string
 *   The raw data as a string.
 */
function make_get_data($data_source) {
  if ($data_source == '-') {
    // See http://drupal.org/node/499758 before changing this.
    $stdin = fopen('php://stdin', 'r');
    $data = '';
    $has_input = FALSE;

    while ($line = fgets($stdin)) {
      $has_input = TRUE;
      $data .= $line;
    }

    if ($has_input) {
      return $data;
    }
    return FALSE;
  }
  // Local file.
  elseif (!strpos($data_source, '://')) {
    $data = file_get_contents($data_source);
  }
  // Remote file.
  else {
    $file = _make_download_file($data_source);
    $data = file_get_contents($file);
    drush_op('unlink', $file);
  }
  return $data;
}

/**
 * Helper to provide sys_get_temp_dir if on php < 5.2.1.
 */
if (!function_exists('sys_get_temp_dir')) {
  /**
   * Based on
   * http://www.phpit.net/article/creating-zip-tar-archives-dynamically-php/2/
   */
  function sys_get_temp_dir() {
    // Try to get from environment variable.
    if (!empty($_ENV['TMP'])) {
      return realpath($_ENV['TMP']);
    }
    elseif (!empty($_ENV['TMPDIR'])) {
      return realpath($_ENV['TMPDIR']);
    }
    elseif (!empty($_ENV['TEMP'])) {
      return realpath($_ENV['TEMP']);
    }
    else {
      // Detect by creating a temporary file.
      // Try to use system's temporary directory as random name
      // shouldn't exist.
      $temp_file = tempnam(md5(uniqid(rand(), TRUE)), '');
      if ($temp_file) {
        $temp_dir = realpath(dirname($temp_file));
        unlink($temp_file);
        return $temp_dir;
      }
      else {
        return FALSE;
      }
    }
  }
}

/**
 * Apply any defaults.
 *
 * @param array &$info
 *   A parsed make array.
 */
function make_apply_defaults(&$info) {
  if (isset($info['defaults'])) {
    $defaults = $info['defaults'];
    unset($info['defaults']);

    foreach ($defaults as $type => $default_data) {
      if (isset($info[$type])) {
        foreach ($info[$type] as $project => $data) {
          $info[$type][$project] += $default_data;
        }
      }
      else {
        drush_log(dt("Unknown attribute '@type' in defaults array", array('@type' => $type)), 'warning');
      }
    }
  }
}
