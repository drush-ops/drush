<?php

/**
 * @file
 * Utility functions related to Drupal.
 */

use Drush\Log\LogLevel;
use Drupal\Core\Site\Settings;

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
      $bootstrap = drush_bootstrap_class_for_root($drupal_root);
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

/**
 * b/c layer for format_date
 */
function drush_format_date($timestamp, $type = 'medium', $format = '', $timezone = NULL, $langcode = NULL) {
  if (drush_drupal_major_version() >= 9) {
    return \Drupal::service('date.formatter')
      ->format($timestamp, $type, $format, $timezone, $langcode);
  }
  return format_date($timestamp, $type, $format, $timezone, $langcode);
}

/**
 * Returns the path of a configuration directory.
 *
 * Configuration directories are configured using $config_directories in
 * settings.php.
 *
 * @param string $type
 *   The type of config directory to return.
 *
 * @return string
 *   The configuration directory path.
 *
 * @throws \Exception
 */
function drush_config_get_config_directory($type = 'sync') {
    if (drush_drupal_major_version() >= 9) {
      if ($type != 'sync') {
        return '';
      }
      return Settings::get('config_sync_directory', FALSE);
    }
    return config_get_config_directory($type);
}

/**
 * Log Drupal watchdog() calls.
 *
 * A sneaky implementation of hook_watchdog(), for D6/D7.
 */
function system_watchdog($log_entry) {
  // Transform non informative severity levels to 'error' for compatibility with _drush_print_log.
  // Other severity levels are coincident with the ones we use in drush.
  if (drush_drupal_major_version() >= 6 && $log_entry['severity'] <= 2) {
    $severity = 'error';
  }
  else {
    drush_include_engine('drupal', 'environment');
    $levels = drush_watchdog_severity_levels();
    $severity = $levels[$log_entry['severity']];
  }
  // Format the message.
  if (is_array($log_entry['variables'])) {
    $message = strtr($log_entry['message'], $log_entry['variables']);
  }
  else {
    $message = $log_entry['message'];
  }

  // decode_entities() only loaded after FULL bootstrap.
  if (function_exists('decode_entities')) {
    $message = decode_entities($message);
  }
  $message = strip_tags($message);

  // Log or print or ignore. Just printing saves memory but thats rarely needed.
  switch (drush_get_option('watchdog', 'log')) {
    case 'log':
      drush_log('WD '. $log_entry['type'] . ': ' . $message, $severity);
      break;
    case 'print':
      // Disable in backend mode since it logs output and the goal is to conserve memory.
      // @see _drush_bootstrap_drush().
      if (ob_get_length() === FALSE) {
        drush_print('WD '. $severity . ' ' . $log_entry['type'] . ': ' . $message);
      }
      break;
    default:
      // Do nothing.
  }
}

/**
 * Log the return value of Drupal hook_update_n functions.
 *
 * This is used during install and update to log the output
 * of the update process to the logging system.
 */
function _drush_log_update_sql($ret) {
  if (count($ret)) {
    foreach ($ret as $info) {
      if (is_array($info)) {
        if (!$info['success']) {
          drush_set_error('DRUPAL_UPDATE_FAILED', $info['query']);
        }
        else {
          drush_log($info['query'], ($info['success']) ? LogLevel::SUCCESS : LogLevel::ERROR);
        }
      }
    }
  }
}

function drush_drupal_get_profile() {
    if (method_exists('Drupal', 'installProfile')) {
      return \Drupal::installProfile();
    }
    if (function_exists('drupal_get_profile')) {
      return \drupal_get_profile();
    }
    return variable_get('install_profile', '');
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

/*
 * An array of options shared by sql-sanitize and sql-sync commands.
 */
function drupal_sanitize_options() {
  return array(
    'sanitize-password' => array(
      'description' => 'The password to assign to all accounts in the sanitization operation, or "no" to keep passwords unchanged.',
      'example-value' => 'password',
      'value' => 'required',
    ),
    'sanitize-email' => array(
      'description' => 'The pattern for test email addresses in the sanitization operation, or "no" to keep email addresses unchanged.  May contain replacement patterns %uid, %mail or %name.',
      'example-value' => 'user+%uid@localhost',
      'value' => 'required',
    ),
  );
}
