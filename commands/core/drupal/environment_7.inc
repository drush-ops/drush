<?php
// $Id$
/**
 * @file
 *   Specific functions for a drupal 5 environment.
 *   drush_include_engine() magically includes either this file
 *   or environment_X.inc depending on which version of drupal drush
 *   is called from.
 */

/**
 * Get complete information for all available modules.
 *
 * @return
 *   An array containing module info for all installed modules.
 */
function drush_get_modules() {
  return system_rebuild_module_data();
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
    $dependencies = $module_info[$module]->requires;
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
        $incompatibility = drupal_check_incompatibility($v, $current_version);
        if (!is_null($incompatibility)) {
          $status[$key]['error'] = array(
            'code' => 'DRUSH_PM_ENABLE_DEPENDENCY_VERSION_MISMATCH',
            'message' => dt('Module !module cannot be enabled because it depends on !dependency !required_version but !current_version is available', array('!module' => $module, '!dependency' => $dependency_name, '!required_version' => $incompatibility, '!current_version' => $current_version))
          );
        }
      }
    }
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
    $dependents = array_merge($dependents, array_keys($module_info[$module]->required_by));
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
  module_enable($modules, FALSE);
}

/**
 * Disable a list of modules. It is assumed the list contains all dependents not already disabled.
 *
 * @param $modules
 *   Array of module names
 */
function drush_module_disable($modules) {
  module_disable($modules, FALSE);
}

/**
 * Uninstall a list of modules.
 *
 * @param $modules
 *   Array of module names
 */
function drush_module_uninstall($modules) {
  require_once drush_get_context('DRUSH_DRUPAL_ROOT') . '/includes/install.inc';
  drupal_uninstall_modules($modules);
}

/**
 * Submit the system modules form.
 *
 * The modules should already be fully enabled/disabled before calling this
 * function. Calling this function just makes sure any activities triggered by
 * the form submit (such as admin_role) are completed.
 */
function drush_system_modules_form_submit($active_modules) {
  module_load_include('inc', 'system', 'system.admin');
  $form_state = array('values' => array('status' => $active_modules));
  drupal_form_submit('system_modules', $form_state);
}

/**
 * Get complete information for all available themes.
 *
 * @return
 *   An array containing theme info for all available themes.
 */
function drush_get_themes() {
  return system_rebuild_theme_data();
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

