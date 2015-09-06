<?php

/**
 * @file
 *   Functions used when Drush is starting up.
 *
 * This file is included and used early in the Drush
 * startup process, before any other Drush code is
 * loaded.  Therefore, the code in this file only uses
 * standard PHP library functions, and the other functions
 * also defined directly in this file.
 */


/**
 * Checks the provided location and return the appropriate
 * drush wrapper or drush launcher script, if found.
 */
function find_wrapper_or_launcher($location) {
  // We will look for a "drush wrapper" script that might
  // be stored in the root of a site site.  If there is
  // no wrapper script, then we will look for the
  // drush.launcher script in vendor/bin.  We try just a
  // few of the most common locations; if the user relocates
  // their vendor directory anywhere else, then they must
  // use a wrapper script to locate it.  See the comment in
  // 'examples/drush' for details.
  //
  // We are somewhat "loose" about whether we are looking
  // for "drush" or "drush.launcher", because in old versions
  // of Drush, the "drush launcher" was named "drush".
  // Otherwise, there wouldn't be any point in looking for
  // "drush.launcher" at the root, or "drush" in a vendor directory.
  // We also allow users to rename their drush wrapper to
  // 'drush.wrapper' to avoid conflicting with a directory named
  // 'drush' at the site root.
  $drush_locations = array(
    "drush",
    "vendor/bin/drush",
    "../vendor/bin/drush",
    "sites/all/vendor/bin/drush",
  );

  foreach ($drush_locations as $d) {
    foreach (array('.launcher', '.wrapper', '') as $suffix) {
      if (is_file("$location/$d$suffix")) {
        return "$location/$d$suffix";
      }
    }
  }
  return "";
}

/**
 * Return the user's home directory.
 */
function drush_server_home() {
  // Cannot use $_SERVER superglobal since that's empty during UnitUnishTestCase
  // getenv('HOME') isn't set on Windows and generates a Notice.
  $home = getenv('HOME');
  if (!empty($home)) {
    // home should never end with a trailing slash.
    $home = rtrim($home, '/');
  }
  elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
    // home on windows
    $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    // If HOMEPATH is a root directory the path can end with a slash. Make sure
    // that doesn't happen.
    $home = rtrim($home, '\\/');
  }
  return empty($home) ? NULL : $home;
}

/**
 * Get the path to the directory that holds the
 * information about the sites 'used' via site-set.
 */
function drush_get_use_dir() {
  return drush_server_home() . "/.drush/use";
}

/**
 * Return the path where the terminal-specific symlink
 * for the site-specific Drush script will be created.  This
 * is typically $HOME/.drush/use/PPID/bin/drush.
 *
 * Note that this requires the posix_getppid() function in
 * order to work correctly.  On systems missing this function,
 * the global symlink path will be returned instead.
 */
function drush_get_terminal_specific_use_link_path($use_dir = FALSE) {
  if (!$use_dir) {
    $use_dir = drush_get_use_dir();
  }
  // Drush site-set will always write both the terminal-specific
  // and global links.  We should always use the terminal-specific
  // link if posix_getppid() is available; otherwise, we will use
  // the global location.
  if (function_exists('posix_getppid')) {
    return $use_dir . '/' . posix_getppid() . '/bin/drush';
  }
  else {
    return drush_get_global_use_link_path($use_dir);
  }
}

/**
 * Return the path where the global symlink for the
 * site-specific Drush script will be created.  This
 * is typically $HOME/.drush/use/bin/drush.
 */
function drush_get_global_use_link_path($use_dir = FALSE) {
  if (!$use_dir) {
    $use_dir = drush_get_use_dir();
  }
  return "$use_dir/bin/drush";
}
