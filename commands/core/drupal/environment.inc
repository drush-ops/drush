<?php
/**
 * @file
 *   Specific functions for a drupal 7 environment.
 *   drush_include_engine() magically includes either this file
 *   or environment_X.inc depending on which version of drupal drush
 *   is called from.
 */

/**
 * Get complete information for all available modules.
 *
 * @param $include_hidden
 *   Boolean to indicate whether hidden modules should be excluded or not.
 * @return
 *   An array containing module info for all available modules.
 */
function drush_get_modules($include_hidden = TRUE) {
  $modules = system_rebuild_module_data();
  if (!$include_hidden) {
    foreach ($modules as $key => $module) {
      if (isset($module->info['hidden'])) {
        unset($modules[$key]);
      }
    }
  }

  return $modules;
}

/**
 * Returns drupal required modules, including modules declared as required dynamically.
 */
function _drush_drupal_required_modules($module_info) {
  $required = drupal_required_modules();
  foreach ($module_info as $name => $module) {
    if (isset($module->info['required']) && $module->info['required']) {
      $required[] = $name;
    }
  }
  return array_unique($required);
}

/**
 * Return dependencies and its status for modules.
 *
 * @param $modules
 *   Array of module names
 * @param $module_info
 *   Drupal 'files' array for modules as returned by drush_get_modules().
 * @return
 *   Array with dependencies and status for $modules
 */
function drush_check_module_dependencies($modules, $module_info) {
  $status = array();
  foreach ($modules as $key => $module) {
    $dependencies = array_reverse($module_info[$module]->requires);
    $unmet_dependencies = array_diff(array_keys($dependencies), array_keys($module_info));
    if (!empty($unmet_dependencies)) {
      $status[$key]['error'] = array(
          'code' => 'DRUSH_PM_ENABLE_DEPENDENCY_NOT_FOUND',
          'message' => dt('Module !module cannot be enabled because it depends on the following modules which could not be found: !unmet_dependencies', array('!module' => $module, '!unmet_dependencies' => implode(',', $unmet_dependencies)))
      );
    }
    else {
      // check for version incompatibility
      foreach ($dependencies as $dependency_name => $v) {
        $current_version = $module_info[$dependency_name]->info['version'];
        $current_version = str_replace(DRUPAL_CORE_COMPATIBILITY . '-', '', $current_version);
        $incompatibility = drupal_check_incompatibility($v, $current_version);
        if (!is_null($incompatibility)) {
          $status[$key]['error'] = array(
            'code' => 'DRUSH_PM_ENABLE_DEPENDENCY_VERSION_MISMATCH',
            'message' => dt('Module !module cannot be enabled because it depends on !dependency !required_version but !current_version is available', array('!module' => $module, '!dependency' => $dependency_name, '!required_version' => $incompatibility, '!current_version' => $current_version))
          );
        }
      }
    }
    $status[$key]['unmet-dependencies'] = $unmet_dependencies;
    $status[$key]['dependencies'] = array_keys($dependencies);
  }

  return $status;
}

/**
 * Return dependents of modules.
 *
 * @param $modules
 *   Array of module names
 * @param $module_info
 *   Drupal 'files' array for modules as returned by drush_get_modules().
 * @return
 *   Array with dependents for each one of $modules
 */
function drush_module_dependents($modules, $module_info) {
  $dependents = array();
  foreach ($modules as $module) {
    $dependents = array_merge($dependents, drupal_map_assoc(array_keys($module_info[$module]->required_by)));
  }

  return array_unique($dependents);
}

/**
 * Enable a list of modules. It is assumed the list contains all the dependencies not already enabled.
 *
 * @param $modules
 *   Array of module names
 */
function drush_module_enable($modules) {
  // The list of modules already have all the dependencies, but they might not
  // be in the correct order. Still pass $enable_dependencies = TRUE so that
  // Drupal will enable the modules in the correct order.
  module_enable($modules);
  // Flush all caches.
  drupal_flush_all_caches();
}

/**
 * Disable a list of modules. It is assumed the list contains all dependents not already disabled.
 *
 * @param $modules
 *   Array of module names
 */
function drush_module_disable($modules) {
  // The list of modules already have all the dependencies, but they might not
  // be in the correct order. Still pass $enable_dependencies = TRUE so that
  // Drupal will enable the modules in the correct order.
  module_disable($modules);
  // Flush all caches.
  drupal_flush_all_caches();
}

/**
 * Uninstall a list of modules.
 *
 * @param $modules
 *   Array of module names
 */
function drush_module_uninstall($modules) {
  require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
  drupal_uninstall_modules($modules);
}

/**
 * Get complete information for all available themes.
 *
 * @param $include_hidden
 *   Boolean to indicate whether hidden themes should be excluded or not.
 * @return
 *   An array containing theme info for all available themes.
 */
function drush_get_themes($include_hidden = TRUE) {
  $themes = system_rebuild_theme_data();
  if (!$include_hidden) {
    foreach ($themes as $key => $theme) {
      if (isset($theme->info['hidden'])) {
        unset($themes[$key]);
      }
    }
  }

  return $themes;
}

/**
 * Enable a list of themes.
 *
 * @param $themes
 *  Array of theme names.
 */
function drush_theme_enable($themes) {
  theme_enable($themes);
}

/**
 * Disable a list of themes.
 *
 * @param $themes
 *  Array of theme names.
 */
function drush_theme_disable($themes) {
  theme_disable($themes);
}

/**
 * Helper function to obtain the severity levels based on Drupal version.
 *
 * This is a copy of watchdog_severity_levels() without t().
 *
 * Severity levels, as defined in RFC 3164: http://www.ietf.org/rfc/rfc3164.txt.
 *
 * @return
 *   Array of watchdog severity levels.
 */
function core_watchdog_severity_levels() {
  return array(
    WATCHDOG_EMERGENCY=> 'emergency',
    WATCHDOG_ALERT    => 'alert',
    WATCHDOG_CRITICAL => 'critical',
    WATCHDOG_ERROR    => 'error',
    WATCHDOG_WARNING  => 'warning',
    WATCHDOG_NOTICE   => 'notice',
    WATCHDOG_INFO     => 'info',
    WATCHDOG_DEBUG    => 'debug',
  );
}
