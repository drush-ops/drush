<?php
// $Id$

/**
 * @file
 * Functions used by drush to query the environment and
 * setting the current configuration.
 */

/**
 * The indicator for a Drupal installation folder.
 */
define('DRUSH_DRUPAL_BOOTSTRAP', 'includes/bootstrap.inc');

/**
 * Returns the current working directory.
 *
 * TODO: Could cache result, but it isn't really expensive.
 */
function drush_cwd() {
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find.
  $path = $_SERVER['PWD'];
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = _drush_convert_path($path);

  return $path;
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 */
function _drush_convert_path($path) {
  return str_replace('\\','/', $path);
}

/**
 * Returns parent directory.
 *
 * @param string
 *   Path to start from.
 *
 * @return string
 *   Parent path of given path.
 */
function _drush_shift_path_up($path) {
  if (empty($path)) {
    return FALSE;
  }
  $path = explode('/', $path);
  // Move one directory up.
  array_pop($path);
  return implode('/', $path);
}

/**
 * Like Drupal conf_path, but searching from beneath.
 * Allows proper site uri detection in site sub-directories.
 *
 * Essentially looks for a settings.php file.
 *
 * @param string
 *   Search starting path. Defaults to current working directory.
 *
 * @return
 *   Current site path (folder containing settings.php) or FALSE if not found.
 */
function drush_site_path($path = NULL) {
  static $site_path;
  
  if (!isset($site_path)) {
    $site_path = FALSE;

    $path = empty($path) ? drush_cwd() : $path;
    // Check the current path.
    if (file_exists($path . '/settings.php')) {
      $site_path = $path;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if (file_exists($path . '/settings.php')) {
          $site_path = $path;
          break;
        }
      }
    }
    
    // Last resort: try from site root
    if (!$site_path) {
      if ($site_root = drush_locate_root()) {
        if (file_exists($site_root . '/sites/default/settings.php')) {
          $site_path = $site_root . '/sites/default';
        }
      }
    }
  }

  return $site_path;
}

/**
 * Attempts to determine site URI from the given path, using
 * a couple of heuristics.
 *
 * @param
 *   Starting path or current working directory.
 *
 * @return
 *   Site uri string or false if none was found.
 */
function drush_site_uri($path = NULL) {
  $uri = FALSE;

  if ($site_path = drush_site_path($path)) {
    // TODO: the method of including the settings.php file here is 
    // problematic, since it may contain code that will be executed.
    // An alternative approach would be to use a regexp for extracting
    // a possible $base_url setting.

    // Export the following settings.php variables to the global namespace.
    global $databases, $db_url, $db_prefix, $cookie_domain, $conf, $installed_profile, $update_free_access;
    // If the settings.php has a defined path we use the URI from that.
    include_once($site_path . '/settings.php');
    if (isset($base_url)) {
      $uri = $base_url;
    }
    else {
      // Alternatively we default to the name of the current directory, if it is not 'default'.
      $elements = explode('/', $site_path);
      $current = array_pop($elements);
      if ($current != 'default') {
        $uri = 'http://'. $current;
      }
    }
  }

  return $uri;
}

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run drush from a subdirectory of the drupal root.
 *
 * @param
 *   Search start path. Defaults to current working directory.
 */
function drush_locate_root($path = NULL) {
  $drupal_root = FALSE;

  $path = empty($path) ? drush_cwd() : $path;
  // Check the start path.
  if (drush_valid_drupal_root($path)) {
    $drupal_root = $path;
  }
  else {
    // Move up dir by dir and check each.
    while ($path = _drush_shift_path_up($path)) {
      if (drush_valid_drupal_root($path)) {
        $drupal_root = $path;
        break;
      }
    }
  }

  return $drupal_root;
}

/**
 * Checks whether given path qualifies as a Drupal root.
 *
 * @param string
 *   Path to check.
 *
 * @return boolean
 *   True if given path seems to be a Drupal root, otherwise false.
 */
function drush_valid_drupal_root($path) {
  return !empty($path) && is_dir($path) && file_exists($path . '/' . DRUSH_DRUPAL_BOOTSTRAP);
}
