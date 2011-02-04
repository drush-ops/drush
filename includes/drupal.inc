<?php
// $Id$

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
      // D7 stores VERSION in bootstrap.inc
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
 * Returns the Drupal major version number (5, 6, 7 ...)
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

  $message = strip_tags(decode_entities($message));

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