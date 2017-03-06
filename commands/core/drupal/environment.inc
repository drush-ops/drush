<?php
/**
 * @file
 *   Specific functions for a drupal 8+ environment.
 *   drush_include_engine() magically includes either this file
 *   or environment_X.inc depending on which version of drupal Drush
 *   is called from.
 */

use Drupal\Core\Site\Settings;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Returns a list of enabled modules.
 *
 * This is a wrapper for module_list().
 */
function drush_module_list() {
  $modules = array_keys(\Drupal::moduleHandler()->getModuleList());
  return array_combine($modules, $modules);
}

/**
 * Determines which modules are implementing a hook.
 *
 * @param string $hook
 *   The hook name.
 * @param bool $sort
 *  Not used in Drupal 8 environment.
 * @param bool $reset
 *  TRUE to reset the hook implementation cache.
 *
 * @see \Drupal\Core\Extension\ModuleHandlerInterface::getImplementations().
 * @see \Drupal\Core\Extension\ModuleHandlerInterface::resetImplementations().
 *
 */
function drush_module_implements($hook, $sort = FALSE, $reset = FALSE) {
  // $sort is there for consistency, but looks like Drupal 8 has no equilavient for it.
  // We can sort the list manually later if really needed.
  if ($reset == TRUE){
    \Drupal::moduleHandler()->resetImplementations();
  }
  return \Drupal::moduleHandler()->getImplementations($hook);
}
