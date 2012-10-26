<?php

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
      // Path to files in D6 and D7 where VERSION is defined.
      $version_constant_paths = array('/modules/system/system.module', '/includes/bootstrap.inc');
      foreach ($version_constant_paths as $path) {
        if (file_exists($drupal_root . $path)) {
          require_once $drupal_root . $path;
        }
      }
      // We just might be dealing with an early Drupal version (pre 4.7)
      if (defined('VERSION')) {
        $version = VERSION;
      }
    }
  }
  return $version;
}

function drush_drupal_cache_clear_all() {
  $prior = drush_get_context('DRUSH_AFFIRMATIVE');
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  drush_invoke('cache-clear', 'all');
  drush_set_context('DRUSH_AFFIRMATIVE', $prior);
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
 * Log Drupal watchdog() calls.
 *
 * A sneaky implementation of hook_watchdog().
 */
function system_watchdog($log_entry) {
  // Transform non informative severity levels to 'error' for compatibility with _drush_print_log.
  // Other severity levels are coincident with the ones we use in drush.
  if (drush_drupal_major_version() >= 6 && $log_entry['severity'] <= 2) {
    $severity = 'error';
  }
  else {
    drush_include_engine('drupal', 'environment');
    $levels = core_watchdog_severity_levels();
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
  if (sizeof($ret)) {
    foreach ($ret as $info) {
      if (is_array($info)) {
        if (!$info['success']) {
          drush_set_error('DRUPAL_UPDATE_FAILED', $info['query']);
        }
        else {
          drush_log($info['query'], ($info['success']) ? 'success' : 'error');
        }
      }
    }
  }
}

function drush_find_profiles($drupal_root , $key = 'name') {
  return drush_scan_directory($drupal_root . '/profiles', "/.*\.profile$/", array('.', '..', 'CVS', 'tests'), 0, 2, $key);
}

/**
 * Log the given user in to a bootstrapped Drupal site.
 *
 * @param mixed
 *   Numeric user id or user name.
 *
 * @return boolean
 *   TRUE if user was logged in, otherwise FALSE.
 */
function drush_drupal_login($drush_user) {
  global $user;
  if (drush_drupal_major_version() >= 7) {
    $user = is_numeric($drush_user) ? user_load($drush_user) : user_load_by_name($drush_user);
  }
  else {
    $user = user_load(is_numeric($drush_user) ? array('uid' => $drush_user) : array('name' => $drush_user));
  }

  if (empty($user)) {
    if (is_numeric($drush_user)) {
      $message = dt('Could not login with user ID #!user.', array('!user' => $drush_user));
      if ($drush_user === 0) {
        $message .= ' ' . dt('This is typically caused by importing a MySQL database dump from a faulty tool which re-numbered the anonymous user ID in the users table. See !link for help recovering from this situation.', array('!link' => 'http://drupal.org/node/1029506'));
      }
    }
    else {
      $message = dt('Could not login with user account `!user\'.', array('!user' => $drush_user));
    }
    return drush_set_error('DRUPAL_USER_LOGIN_FAILED', $message);
  }
  else {
    $name = $user->name ? $user->name : variable_get('anonymous', t('Anonymous'));
    drush_log(dt('Successfully logged into Drupal as !name', array('!name' => $name . " (uid=$user->uid)")), 'bootstrap');
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
function _drush_drupal_parse_info_file($data) {
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
      $parent[$last] = $value;
    }
    return $info;
  }
  return FALSE;
}

