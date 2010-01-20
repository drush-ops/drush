<?php
// $Id$
/**
 * @file
 *   Helper functions for /includes/environment.php.
 *   drush_include_engine() magically includes either this file
 *   or environment_7.php depending on which version of drupal drush
 *   is called from.
 */

/**
 * Get module information for all modules, including disabled. Used by updatedb,
 * among others.
 *
 * @return
 *   An array containing module info for all installed modules.
 */
function _drush_get_modules() {
  $modules = module_rebuild_cache();
  // In Drupal 5, system_modules() returns NULL for the dependency list of the module if there are no dependencies.
  // We will override this to be an empty array instead to be compatible to Drupal 6 and 7
  foreach ($modules as $module) {
    if (empty($module->info['dependencies'])) {
       $module->info['dependencies'] = array();
    }
  }
  return $modules;
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
