<?php

/**
 * @file
 *   Functions used when Drush is starting up.
 *
 * This file is included and used early in the Drush
 * startup process, before any other Drush APIs are
 * available.
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
