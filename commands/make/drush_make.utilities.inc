<?php
// $Id$

function drush_make_base_path_validate($base_path) {
  if (drush_get_option('tar')) {
    $base_path .= '.tar.gz';
  }
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
  if (!isset($info['projects']) || !is_array($info['projects']) || empty($info['projects'])) {
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
  // We don't always need to do the first, but always the second.
  // TODO: Find out a way not to always do the first thing.
  if (!drush_get_option('no-clean', FALSE)) {
    drush_make_shell_exec('mv %s/* .', $tmp_dir);
    drush_make_shell_exec('rm -r %s', $tmp_dir);
  }
  else {
    drush_log(dt('Tmp dir: %dir', array('%dir' => $tmp_dir)), 'ok');
  }
}

function drush_make_prepare_install($tmp_path, $base_path) {
  $default = $tmp_path . '/' . $base_path . '/sites/default';
  drush_make_shell_exec("cp %s/default.settings.php  %s/settings.php", $default, $default);
  drush_make_shell_exec("mkdir %s/files", $default);
  drush_make_shell_exec("chmod a+w %s/settings.php %s/files", $default, $default);
}

function drush_make_tar($tmp_path, $base_path) {
  drush_make_shell_exec("tar -C %s -Pc %s | gzip > %s.tar.gz", $tmp_path, $base_path, $base_path);
}

/*
 * Wrapper around drush_shell_exec to provide sprintf functionality with some more safety.
 */
function drush_make_shell_exec() {
  $args = func_get_args();

  for ($x = 1; $x < count($args); $x++) {
    $args[$x] = escapeshellcmd($args[$x]);
  }

  $command = call_user_func_array('sprintf', $args);

  return drush_shell_exec($command);
}
