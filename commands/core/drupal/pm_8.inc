<?php

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
function _drush_pm_uninstall($args) {
  $extension_info = drush_get_extensions();

  // classify $args in themes, modules or unknown.
  $modules = array();
  $themes = array();
  drush_pm_classify_extensions($args, $modules, $themes, $extension_info);
  $extensions = array_merge($modules, $themes);
  $unknown = array_diff($args, $extensions);

  // Discard and set an error for each unknown extension.
  foreach ($unknown as $name) {
    drush_log('DRUSH_PM_DISABLE_EXTENSION_NOT_FOUND', dt('!extension was not found and will not be uninstalled.', array('!extension' => $name)), 'warning');
  }

  // Discard extensions that are not installed.
  foreach ($extensions as $name) {
    if (!$extension_info[$name]->status && $extension_info[$name]->type == 'theme') {
      unset($themes[$name]);
      drush_log(dt('!extension is not installed.', array('!extension' => $name)), 'ok');
    }
  }

  // Discard default theme.
  if (!empty($themes)) {
    $default_theme = drush_theme_get_default();
    if (in_array($default_theme, $themes)) {
      unset($themes[$default_theme]);
      drush_log(dt('!theme is the default theme and can\'t be disabled.', array('!theme' => $default_theme)), 'ok');
    }
  }

  if (!empty($modules)) {
    // Add enabled dependents to the list of modules to uninstall.
    $dependents = drush_module_dependents($modules, $extension_info);
    $dependents = array_intersect($dependents, drush_module_list());
    $modules = array_merge($modules, $dependents);

    // Discard required modules.
    $required = drush_drupal_required_modules($extension_info);
    foreach ($required as $module) {
      if (isset($modules[$module])) {
        unset($modules[$module]);
        $info = $extension_info[$module]->info;
        // No message for hidden modules.
        if (!isset($info['hidden'])) {
          $explanation = !empty($info['explanation']) ? ' ' . dt('Reason: !explanation', array('!explanation' => strip_tags($info['explanation'])))  : '';
          drush_log(dt('!module is a required module and can\'t be uninstalled.', array('!module' => $module)) . $explanation, 'ok');
        }
      }
    }
  }

  // Inform the user which extensions will finally be uninstalled.
  $extensions = array_merge($modules, $themes);
  if (empty($extensions)) {
    return drush_log(dt('There were no extensions that could be uninstall.'), 'ok');
  }
  else {
    drush_print(dt('The following extensions will be uninstalled: !extensions', array('!extensions' => implode(', ', $extensions))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Disable themes.
  if (!empty($themes)) {
    drush_theme_disable($themes);
  }

  // Uninstall modules and pass dependency validation in form submit.
  if (!empty($modules)) {
    drush_module_uninstall($modules);
  }

  // Inform the user of final status.
  $result_extensions = drush_get_named_extensions_list($extensions);
  $problem_extensions = array();
  foreach ($result_extensions as $name => $extension) {
    if (!$extension->status) {
      drush_log(dt('!extension was uninstalled successfully.', array('!extension' => $extension->name)), 'ok');
    }
    else {
      $problem_extensions[] = $extension->name;
    }
  }
  if (!empty($problem_extensions)) {
    return drush_set_error('DRUSH_PM_UNINSTALL_EXTENSION_ISSUE', dt('There was a problem uninstalling !extension.', array('!extension' => implode(',', $problem_extensions))));
  }
}
