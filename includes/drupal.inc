<?php

/**
 * @file
 * Utility functions related to Drupal.
 */

use Drush\Drush;

/**
 * Detects the version number of the current Drupal installation,
 * if any. Returns FALSE if there is no current Drupal installation,
 * or it is somehow broken.
 *
 * @deprecated See \Drush\Boot\Boot::getVersion.
 *
 * @return
 *   A string containing the version number of the current
 *   Drupal installation, if any. Otherwise, return FALSE.
 */
function drush_drupal_version($drupal_root = NULL) {
  static $version = FALSE;

  if (!$version) {
    if (($drupal_root != NULL) || ($drupal_root = Drush::bootstrapManager()->getRoot())) {
      $bootstrap = Drush::bootstrapManager()->bootstrapObjectForRoot($drupal_root);
      if ($bootstrap) {
        $version = $bootstrap->getVersion($drupal_root);
      }
    }
  }
  return $version;
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
