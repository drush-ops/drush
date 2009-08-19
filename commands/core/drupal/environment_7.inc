<?php
// $Id:
/**
 * @file
 *   Helper functions for /includes/environment.php.
 *   drush_include_engine() magically includes either this file
 *   or environment.php depending on which version of drupal drush
 *   is called from.
 */

/**
 * Get module information for all installed modules.
 *
 * @return
 *   An array containing module info for all installed modules.
 */
function _drush_get_modules() {
  $module_info = drupal_system_listing('/\.module$/', 'modules', 'name', 0);
  system_get_files_database($module_info, 'module');
  foreach ($module_info as $module_name => $module){
    $module_info[$module_name]->info = drupal_parse_info_file(dirname($module->filename) .'/'. $module->name .'.info');
  }
  return $module_info;
}

/**
 * Get theme information for all installed themes.
 *
 * @return
 *   An array containing theme info for all installed themes.
 */
function _drush_get_themes() {
  return _system_get_theme_data();
}