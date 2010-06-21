<?php
// $Id$

function drush_make_ensure_version() {
  // Check version 2.1 or up:
  $options = drush_get_option_help();
  if (isset($options['-n, --nocolor'])) {
    return '2.1';
  }

  if (isset($options['--nocolor'])) {
    return '2.2';
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
function drush_make_parse_info_file($data) {
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

/**
 * Include any additional info files and merge them into the current file.
 *
 * Files are included in listed order with the source info file appended
 * last. This allows the source info file to override keys of all other
 * included files.
 *
 * @param string $include_path
 *   The path to which include paths are relative.
 * @param array $info
 *   A parsed info file array. Need not be validated.
 * @param string $source
 *   The unparsed source data that provided the original info file array.
 *
 * @return array
 *   A parsed info file array of the combined includes.  
 */
function drush_make_include_info_files($include_path, $info, $source) {
  $includes = array();
  if (!empty($info['includes']) && is_array($info['includes'])) {
    foreach ($info['includes'] as $key => $include) {
      if (is_string($include)) {
        $data = FALSE;
        if (drush_make_valid_url($include, TRUE) && ($data = drush_make_get_data($include))) {
          $includes[$key] = $data;
        }
        else if (file_exists($include_path .'/'. $include) && ($data = drush_make_get_data($include_path .'/'. $include))) {
          $includes[$key] = $data;
        }
        else {
          drush_make_error('BUILD_ERROR', dt("Include file missing: %include", array('%include' => $include)));
        }
      }
    }
  }
  $includes[] = $source;
  $merged = implode("\n", $includes);
  return drush_make_parse_info_file($merged);
}

function drush_make_validate_info_file($info) {
  // Assume no errors to start.
  $errors = FALSE;

  if (empty($info['core'])) {
    drush_make_error('BUILD_ERROR', dt("The 'core' attribute is required"));
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
    drush_make_error('BUILD_ERROR', dt("The 'core' attribute %core has an incorrect format.", array('%core' => $info['core'])));
    $errors = TRUE;
  }
  $names = array();

  // Process projects.
  if (isset($info['projects'])) {
    if (!is_array($info['projects'])) {
      drush_make_error('BUILD_ERROR', dt("'projects' attribute must be an array."));
      $errors = TRUE;
    }
    else {
      foreach ($info['projects'] as $project => $project_data) {
        // Project has an attributes array.
        if (is_string($project) && is_array($project_data)) {
          if (in_array($project, $names)) {
            drush_make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          foreach ($project_data as $attribute => $value) {
            // Unset disallowed attributes.
            if (in_array($attribute, array('install_path'))) {
              unset($info['projects'][$project][$attribute]);
            }
            // Prevent malicious attempts to access other areas of the filesystem.
            elseif (in_array($attribute, array('subdir', 'directory_name')) && !drush_make_safe_path($value)) {
              drush_make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in project %project.", array('%path' => $value, '%attribute' => $attribute, '%project' => $project)));
              $errors = TRUE;
            }
          }
        }
        // Cover if there is no project info, it's just a project name.
        elseif (is_numeric($project) && is_string($project_data)) {
          if (in_array($project_data, $names)) {
            drush_make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project_data)));
            $errors = TRUE;
          }
          $names[] = $project_data;
          unset($info['projects'][$project]);
          $info['projects'][$project_data] = array();
        }
        // Convert shorthand project version style to array format.
        elseif (is_string($project_data)) {
          if (in_array($project, $names)) {
            drush_make_error('BUILD_ERROR', dt("Project %project defined twice (remove the first projects[] = %project).", array('%project' => $project)));
            $errors = TRUE;
          }
          $names[] = $project;
          $info['projects'][$project] = array('version' => $project_data);
        }
        else {
          drush_make_error('BUILD_ERROR', dt('Project %project incorrectly specified.', array('%project' => $project)));
          $errors = TRUE;
        }
      }
    }
  }
  if (isset($info['libraries'])) {
    if (!is_array($info['libraries'])) {
      drush_make_error('BUILD_ERROR', dt("'libraries' attribute must be an array."));
      $errors = TRUE;
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
            elseif (in_array($attribute, array('contrib-destination', 'directory_name')) && !drush_make_safe_path($value)) {
              drush_make_error('BUILD_ERROR', dt("Illegal path %path for '%attribute' attribute in library %library.", array('%path' => $value, '%attribute' => $attribute, '%library' => $library)));
              $errors = TRUE;
            }
          }
        }
      }
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

function drush_make_md5($tmp_path) {
  if (drush_shell_exec("( find %s -type f -exec cksum {} \; )", $tmp_path)) {
    $hashes = array();
    foreach (drush_shell_exec_output() as $line) {
      // Remove the temporary build path which includes a (relatively)
      // unique timestamp.
      $line = str_replace($tmp_path, '', $line);
      // Trim to sanitize.
      $line = trim($line);
      $hashes[] = $line;
    }
    sort($hashes);
    $output = implode("\n", $hashes);
    drush_log(dt('Build hash: %md5', array('%md5' => md5($output))), 'ok');
  }
}

function drush_make_tar($tmp_path, $base_path) {
  drush_shell_exec('mkdir -p %s', dirname($base_path));
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

/**
 * Cd to a path, execute a command, cd back.
 */
function drush_make_cd() {
  $args = func_get_args();
  $directory = array_shift($args);
  $cwd = getcwd();
  chdir($directory);
  $return = call_user_func_array('drush_shell_exec', $args);
  chdir($cwd);
  return $return;
}

/**
 * Reads from STDIN.
 *
 * @return
 *   The contents of STDIN as a string if anything can be read from STDIN,
 *   FALSE otherwise.  Note that this returns the exact contents of STDIN,
 *   including line breaks.
 */
function drush_make_read_stdin() {

  // See http://drupal.org/node/499758 before changing this.
  $stdin = fopen("php://stdin","r");
  $output = '';
  $has_input = FALSE;

  while ($line = fgets($stdin)) {
    $has_input = TRUE;
    $output .= $line;
  }

  if ($has_input) {
    return $output;
  }
  return FALSE;
}

/**
 * Get data based on the source.
 *
 * This is a helper function to abstract the retrieval of data, so that it can
 * come from files, STDIN, etc.  Currently supports filepath and STDIN.
 *
 * @param $data_source
 *   The path to a file, or '-' for STDIN.
 * @return
 *   The raw data as a string.
 */
function drush_make_get_data($data_source) {
  if ($data_source == '-') {
    $data = drush_make_read_stdin();
  }
  else {
    $data = file_get_contents($data_source);
  }
  return $data;
}

/**
 * Helper to get file contents.
 */
function drush_make_get_remote_file($url) {
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    $file_contents = curl_exec($ch);
    curl_close($ch);
    return $file_contents;
  }
  elseif (ini_get('allow_url_fopen')) {
    return file_get_contents($url);
  }
  elseif (drush_shell_exec('wget -qO- %s', $url) || drush_shell_exec('curl -L --silent %s', $url)) {
    return implode("\n", drush_shell_exec_output());
  }
  // drush, we have an error.
  else {
    return drush_set_error('drush_make_download_error', 'Unable to download remote file. No download services supported.');
  }
}

/**
 * Helper to provide sys_get_temp_dir if on php < 5.2.1
 */
if (!function_exists('sys_get_temp_dir')) {
  // Based on http://www.phpit.net/
  // article/creating-zip-tar-archives-dynamically-php/2/
  function sys_get_temp_dir() {
    // Try to get from environment variable
    if (!empty($_ENV['TMP'])){
      return realpath($_ENV['TMP']);
    }
    else if (!empty($_ENV['TMPDIR'])){
      return realpath($_ENV['TMPDIR']);
    }
    else if (!empty($_ENV['TEMP'])){
      return realpath($_ENV['TEMP']);
    }
    else {
      // Detect by creating a temporary file
      // Try to use system's temporary directory
      // as random name shouldn't exist
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
