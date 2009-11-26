<?php
// $Id$

function drush_make_ensure_version() {
  // Check version 2.1 or up:
  $options = drush_get_option_help();
  if (isset($options['-n, --nocolor'])) {
    return '2.1';
  }
  drush_set_error(dt('Drush version 2.1 or higher is required for drush make to work.'));
  return;
}

function drush_make_base_path_validate($base_path) {
  if ($base_path != '.' && file_exists($base_path)) {
    drush_set_error(dt('Base path %path already exists', array('%path' => $base_path)));
    return FALSE;
  }
  return TRUE;
}

/**
 * Parse Drupal info file format.
 *
 * Copied with modifications from includes/common.inc.
 *
 * @see drupal_parse_info_file
 */
function drush_make_parse_info_file($filename, $queue) {
  $data = file_get_contents($filename);
  if (!$data) {
    return FALSE;
  }

  if (preg_match_all('
    @^\s*                           # Start at the beginning of a line, ignoring leading whitespace
    ((?:
      [^=;\[\]]|                    # Key names cannot contain equal signs, semi-colons or square brackets,
      \[[^\[\]]*\]                  # unless they are balanced and not nested
    )+?)
    \s*=\s*                         # Key/value pairs are separated by equal signs (ignoring white-space)
    (?:
      ("(?:[^"]|(?<=\\\\)")*")|     # Double-quoted string, which may contain slash-escaped quotes/slashes
      (\'(?:[^\']|(?<=\\\\)\')*\')| # Single-quoted string, which may contain slash-escaped quotes/slashes
      ([^\r\n]*?)                   # Non-quoted string
    )\s*$                           # Stop at the next end of a line, ignoring trailing whitespace
    @msx', $data, $matches, PREG_SET_ORDER)) {
    $info = array();
    foreach ($matches as $match) {
      // Fetch the key and value string
      $i = 0;
      foreach (array('key', 'value1', 'value2', 'value3') as $var) {
        $$var = isset($match[++$i]) ? $match[$i] : '';
      }
      $value = stripslashes(substr($value1, 1, -1)) . stripslashes(substr($value2, 1, -1)) . $value3;

      // Parse array syntax
      $keys = preg_split('/\]?\[/', rtrim($key, ']'));
      $last = array_pop($keys);
      $parent = &$info;

      // Create nested arrays
      foreach ($keys as $key) {
        if ($key == '') {
          $key = count($parent);
        }
        if (!isset($parent[$key]) || !is_array($parent[$key])) {
          $parent[$key] = array();
        }
        $parent = &$parent[$key];
      }

      // Handle PHP constants
      if (defined($value)) {
        $value = constant($value);
      }

      // Insert actual value
      if ($last == '') {
        $last = count($parent);
      }
      $parent[$last] = $value;
    }
    return $info;
  }
  return FALSE;
}

function drush_make_validate_info_file($info) {
  // TODO: pretty print errors.
  if (!isset($info['core'])) {
    return FALSE;
  }
  // Standardize on core.
  $core = explode('.', $info['core']);
  if (isset($core[1]) && $core[1] != 'x') {
    $info['core_release'] = $info['core'];
    $info['core'] = $core[0] . '.x';
  }
  elseif (!isset($core[1])) {
    $info['core'] = $core[0] . '.x';
  }
  if (isset($info['projects'])) {
    if (!is_array($info['projects'])) {
      drush_make_error('BUILD_ERROR', dt("Incorrect format for 'projects' attribute ."));
      return FALSE;
    }
    else {
      foreach($info['projects'] as $project => $project_data) {
        if (is_array($project_data)) {
          foreach($project_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('install_path'))) {
              unset($info['projects'][$project][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the filesystem.
            elseif (in_array($attribute, array('subdir', 'directory_name')) && !drush_make_safe_path($value)) {
              drush_make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in project %project.", array('%path' => $value, '%attribute' => $attribute, '%project' => $project)));
              return FALSE;
            }
          }
        }
      }
    }
  }
  if (isset($info['libraries'])) {
    if (!is_array($info['libraries'])) {
      drush_make_error('BUILD_ERROR', dt("Incorrect format for 'libraries' attribute ."));
      return FALSE;
    }
    else {
      foreach($info['libraries'] as $library => $library_data) {
        if (is_array($library_data)) {
          foreach($library_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('install_path'))) {
              unset($info['libraries'][$library][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the filesystem.
            elseif (in_array($attribute, array('destination', 'directory_name')) && !drush_make_safe_path($value)) {
              drush_make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in library %library.", array('%path' => $value, '%attribute' => $attribute, '%library' => $library)));
              return FALSE;
            }
          }
        }
      }
    }
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
function drush_make_valid_url($url, $absolute = FALSE) {
  if ($absolute) {
    return (bool)preg_match("
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
    return (bool)preg_match("/^(?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})+$/i", $url);
  }
}


function drush_make_create_tmp() {
  $tmp = sys_get_temp_dir();
  $tmp .= '/drush_make_tmp_' . time();
  drush_op('mkdir', $tmp);
  drush_log(dt('Created directory temporary directory %tmp', array('%tmp' => $tmp)), 'notice');
  return $tmp;
}

function drush_make_clean_tmp($tmp_dir) {
  if (!drush_get_option('no-clean', FALSE)) {
    drush_shell_exec('rm -r %s', $tmp_dir);
  }
  else {
    drush_log(dt('Tmp dir: %dir', array('%dir' => $tmp_dir)), 'ok');
  }
}

function drush_make_prepare_install($tmp_path, $base_path) {
  $default = $tmp_path . '/__build__/sites/default';
  drush_shell_exec("cp %s %s", $default . '/default.settings.php', $default . '/settings.php');
  drush_shell_exec("mkdir %s", $default . '/files');
  drush_shell_exec("chmod a+w %s %s", $default . '/settings.php', $default . '/files');
}

function drush_make_tar($tmp_path, $base_path) {
  drush_make_fill_path(dirname($base_path));
  $filename = basename($base_path);
  $dirname = basename($base_path, '.tar.gz');
  // Move the build directory to a more human-friendly name, so that tar will
  // use it instead.
  drush_shell_exec("mv %s/__build__ %s/%s", $tmp_path, $tmp_path, $dirname);
  // Only move the tar file to it's final location if it's been built
  // successfully.
  if (drush_shell_exec("tar -C %s -Pczf %s/%s %s", $tmp_path, $tmp_path, $filename, $dirname)) {
    drush_shell_exec("mv %s/%s %s", $tmp_path, $filename, $base_path);
  };
  // Move the build directory back to it's original location for consistency.
  drush_shell_exec("mv %s/%s %s/__build__", $tmp_path, $dirname, $tmp_path);
}

/**
 * Wrapper around drush_op('mkdir') to create all the directories up to and including the basename().
 */
function drush_make_fill_path($path) {
  $built_path = array();
  foreach (explode('/', $path) as $part) {
    if ($part == '.') {
      continue;
    }
    $built_path[] = $part;
    $current_path = implode('/', $built_path);
    if ($current_path && !is_dir($current_path)) {
      drush_op('mkdir', $current_path);
    }
  }
}

/**
 * Logs an error unless the --force-complete command line option is specified.
 */
function drush_make_error($error_code, $message) {
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
 * @param $path
 *   The path to check.
 */
function drush_make_safe_path($path) {
  return !preg_match("+^/|^\.\.|/\.\./+", $path);
}

