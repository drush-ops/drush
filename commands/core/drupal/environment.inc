<?php
// $Id:
/**
 * @file
 *   Helper functions for /includes/environment.php.
 *   drush_include_engine() magically includes either this file
 *   or environment_7.php depending on which version of drupal drush
 *   is called from.
 */

/**
 * Get module information for all installed modules.
 *
 * @return
 *   An array containing module info for all installed modules.
 */
function _drush_get_modules() {
  return module_rebuild_cache();
}

/**
 * Get theme information for all installed themes.
 *
 * @return
 *   An array containing theme info for all installed themes.
 */
function _drush_get_themes() {
  return _system_theme_data();
}