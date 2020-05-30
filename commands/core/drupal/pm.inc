<?php

use Drush\Log\LogLevel;

/**
 * Command callback. Drupal 8 does not support disabled modules.
 *
 * @param array $args
 *   Arguments from the command line.
 */
function _drush_pm_disable($args) {
  drush_include_engine('drupal', 'environment');
  // To be consistent call the environment.inc function which will show the user
  // an error.
  drush_module_disable($args);
}

/**
 * Command callback. Uninstall one or more extensions.
 *
 * @param array $args
 *   Arguments from the command line.
 */
function _drush_pm_uninstall($extensions) {
  $extension_info = drush_get_extensions();
  $required = drush_drupal_required_modules($extension_info);

  // Discards extensions which are enabled, not found or already uninstalled.
  $extensions = array_combine($extensions, $extensions);
  foreach ($extensions as $extension) {
    if (!isset($extension_info[$extension])) {
      unset($extensions[$extension]);
      drush_log(dt('Extension !extension was not found and will not be uninstalled.', array('!extension' => $extension)), LogLevel::WARNING);
    }
    elseif (in_array($extension, $required)) {
      unset($extensions[$extension]);
      $info = $extension_info[$extension]->info;
      $explanation = !empty($info['explanation']) ? ' ' . dt('Reason: !explanation.', array('!explanation' => strip_tags($info['explanation'])))  : '';
      drush_log(dt('!extension is a required extension and can\'t be uninstalled.', array('!extension' => $extension)) . $explanation, LogLevel::OK);
    }
    elseif (!$extension_info[$extension]->status) {
      unset($extensions[$extension]);
      drush_log(dt('!extension is already uninstalled.', array('!extension' => $extension)), LogLevel::OK);
    }
    elseif (drush_extension_get_type($extension_info[$extension]) == 'module') {
      // Add installed dependencies to the list of modules to uninstall.
      foreach (drush_module_dependents(array($extension), $extension_info) as $dependent) {
        // Check if this dependency is not required, already enabled, and not already already in the list of modules to uninstall.
        if (!in_array($dependent, $required) && ($extension_info[$dependent]->status) && !in_array($dependent, $extensions)) {
          $extensions[] = $dependent;
        }
      }
    }
  }

  // Discard default theme.
  $default_theme = drush_theme_get_default();
  if (in_array($default_theme, $extensions)) {
    unset($extensions[$default_theme]);
    drush_log(dt('!theme is the default theme and can\'t be uninstalled.', array('!theme' => $default_theme)), LogLevel::OK);
  }

  // Inform the user which extensions will finally be disabled.
  if (empty($extensions)) {
    return drush_log(dt('There were no extensions that could be uninstalled.'), LogLevel::OK);
  }
  else {
    drush_print(dt('The following extensions will be uninstalled: !extensions', array('!extensions' => implode(', ', $extensions))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Classify extensions in themes and modules.
  $modules = array();
  $themes = array();
  drush_pm_classify_extensions($extensions, $modules, $themes, $extension_info);

  drush_module_uninstall($modules);
  drush_theme_uninstall($themes);

  // Inform the user of final status.
  foreach ($extensions as $extension) {
    drush_log(dt('!extension was successfully uninstalled.', array('!extension' => $extension)), LogLevel::OK);
  }
}
