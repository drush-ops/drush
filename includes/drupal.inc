<?php

/**
 * @file
 * Utility functions related to Drupal.
 */

use Drush\Log\LogLevel;

/**
 * Loads the Drupal autoloader and returns the instance.
 */
function drush_drupal_load_autoloader($drupal_root) {
  static $autoloader = FALSE;

  if (!$autoloader) {
    $autoloader = require $drupal_root .'/autoload.php';
    if ($autoloader === TRUE) {
      // The autoloader was already require(). Assume that Drush and Drupal share an autoloader per
      // "Point autoload.php to the proper vendor directory" - https://www.drupal.org/node/2404989
      $autoloader = drush_get_context('DRUSH_CLASSLOADER');
    }
  }
  return $autoloader;
}

/**
 * Detects the version number of the current Drupal installation,
 * if any. Returns FALSE if there is no current Drupal installation,
 * or it is somehow broken.
 *
 * @return
 *   A string containing the version number of the current
 *   Drupal installation, if any. Otherwise, return FALSE.
 */
function drush_drupal_version($drupal_root = NULL) {
  static $version = FALSE;

  if (!$version) {
    if (($drupal_root != NULL) || ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT'))) {
      $bootstrap = \Drush::bootstrapManager()->bootstrapObjectForRoot($drupal_root);
      if ($bootstrap) {
        $version = $bootstrap->get_version($drupal_root);
      }
    }
  }
  return $version;
}

function drush_drupal_cache_clear_all() {
  if (drush_drupal_major_version() >= 8) {
    drush_invoke_process('@self', 'cache-rebuild');
  }
  else {
    drush_invoke_process('@self', 'cache-clear', array('all'));
  }
}

/**
 * Returns the Drupal major version number (6, 7, 8 ...)
 */
function drush_drupal_major_version($drupal_root = NULL) {
  $major_version = FALSE;
  if ($version = drush_drupal_version($drupal_root)) {
    $version_parts = explode('.', $version);
    if (is_numeric($version_parts[0])) {
      $major_version = (integer)$version_parts[0];
    }
  }
  return $major_version;
}

function drush_find_profiles($drupal_root , $key = 'name') {
  return drush_scan_directory($drupal_root . '/profiles', "/.*\.profile$/", array('.', '..', 'CVS', 'tests'), 0, 2, $key);
}

/**
 * Parse Drupal info file format.
 *
 * Copied with modifications from includes/common.inc.
 *
 * @see drupal_parse_info_file
 */
function drush_drupal_parse_info_file($filename) {
  if (!file_exists($filename)) {
    return array();
  }

  $data = file_get_contents($filename);
  return _drush_drupal_parse_info_file($data);
}

/**
 * Parse the info file.
 */
function _drush_drupal_parse_info_file($data, $merge_item = NULL) {
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
      // Fetch the key and value string.
      $i = 0;
      foreach (array('key', 'value1', 'value2', 'value3') as $var) {
        $$var = isset($match[++$i]) ? $match[$i] : '';
      }
      $value = stripslashes(substr($value1, 1, -1)) . stripslashes(substr($value2, 1, -1)) . $value3;

      // Parse array syntax.
      $keys = preg_split('/\]?\[/', rtrim($key, ']'));
      $last = array_pop($keys);
      $parent = &$info;

      // Create nested arrays.
      foreach ($keys as $key) {
        if ($key == '') {
          $key = count($parent);
        }
        if (isset($merge_item) && isset($parent[$key]) && !is_array($parent[$key])) {
          $parent[$key] = array($merge_item => $parent[$key]);
        }
        if (!isset($parent[$key]) || !is_array($parent[$key])) {
          $parent[$key] = array();
        }
        $parent = &$parent[$key];
      }

      // Handle PHP constants.
      if (defined($value)) {
        $value = constant($value);
      }

      // Insert actual value.
      if ($last == '') {
        $last = count($parent);
      }
      if (isset($merge_item) && isset($parent[$last]) && is_array($parent[$last])) {
        $parent[$last][$merge_item] = $value;
      }
      else {
        $parent[$last] = $value;
      }
    }
    return $info;
  }
  return FALSE;
}

/**
 * Build a cache id to store the install_profile for a given site.
 */
function drush_cid_install_profile() {
  return drush_get_cid('install_profile', array(), array(drush_get_context('DRUSH_SELECTED_DRUPAL_SITE_CONF_PATH')));
}
