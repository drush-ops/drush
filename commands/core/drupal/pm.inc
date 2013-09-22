<?php

/**
 * Command callback. Disable one or more extensions.
 */
function _drush_pm_disable($args) {
  $extension_info = drush_get_extensions();

  // classify $args in themes, modules or unknown.
  $modules = array();
  $themes = array();
  drush_pm_classify_extensions($args, $modules, $themes, $extension_info);
  $extensions = array_merge($modules, $themes);
  $unknown = array_diff($args, $extensions);

  // Discard and set an error for each unknown extension.
  foreach ($unknown as $name) {
    drush_log('DRUSH_PM_ENABLE_EXTENSION_NOT_FOUND', dt('!extension was not found and will not be disabled.', array('!extension' => $name)), 'warning');
  }

  // Discard already disabled extensions.
  foreach ($extensions as $name) {
    if (!$extension_info[$name]->status) {
      if ($extension_info[$name]->type == 'module') {
        unset($modules[$name]);
      }
      else {
        unset($themes[$name]);
      }
      drush_log(dt('!extension is already disabled.', array('!extension' => $name)), 'ok');
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
    // Add enabled dependents to the list of modules to disable.
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
          drush_log(dt('!module is a required module and can\'t be disabled.', array('!module' => $module)) . $explanation, 'ok');
        }
      }
    }
  }

  // Inform the user which extensions will finally be disabled.
  $extensions = array_merge($modules, $themes);
  if (empty($extensions)) {
    return drush_log(dt('There were no extensions that could be disabled.'), 'ok');
  }
  else {
    drush_print(dt('The following extensions will be disabled: !extensions', array('!extensions' => implode(', ', $extensions))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Disable themes.
  if (!empty($themes)) {
    drush_theme_disable($themes);
  }

  // Disable modules and pass dependency validation in form submit.
  if (!empty($modules)) {
    drush_module_disable($modules);
  }

  // Inform the user of final status.
  $result_extensions = drush_get_named_extensions_list($extensions);
  $problem_extensions = array();
  foreach ($result_extensions as $name => $extension) {
    if (!$extension->status) {
      drush_log(dt('!extension was disabled successfully.', array('!extension' => $extension->name)), 'ok');
    }
    else {
      $problem_extensions[] = $extension->name;
    }
  }
  if (!empty($problem_extensions)) {
    return drush_set_error('DRUSH_PM_DISABLE_EXTENSION_ISSUE', dt('There was a problem disabling !extension.', array('!extension' => implode(',', $problem_extensions))));
  }
}

/**
 * Command callback. Uninstall one or more modules.
 * // TODO: Use drupal_execute on system_modules_uninstall_confirm_form so that input is validated.
 */
function _drush_pm_uninstall($modules) {
  drush_include_engine('drupal', 'environment');
  $module_info = drush_get_modules();
  $required = drush_drupal_required_modules($module_info);

  // Discards modules which are enabled, not found or already uninstalled.
  foreach ($modules as $key => $module) {
    if (!isset($module_info[$module])) {
      // The module does not exist in the system.
      unset($modules[$key]);
      drush_log(dt('Module !module was not found and will not be uninstalled.', array('!module' => $module)), 'warning');
    }
    else if ($module_info[$module]->status) {
      // The module is enabled.
      unset($modules[$key]);
      drush_log(dt('!module is not disabled. Use `pm-disable` command before `pm-uninstall`.', array('!module' => $module)), 'warning');
    }
    else if ($module_info[$module]->schema_version == -1) { // SCHEMA_UNINSTALLED
      // The module is uninstalled.
      unset($modules[$key]);
      drush_log(dt('!module is already uninstalled.', array('!module' => $module)), 'ok');
    }
    else {
      $dependents = array();
      foreach (drush_module_dependents(array($module), $module_info) as $dependent) {
        if (!in_array($dependent, $required) && ($module_info[$dependent]->schema_version != -1)) {
          $dependents[] = $dependent;
        }
      }
      if (count($dependents)) {
        drush_log(dt('To uninstall !module, the following modules must be uninstalled first: !required', array('!module' => $module, '!required' => implode(', ', $dependents))), 'error');
        unset($modules[$key]);
      }
    }
  }

  // Inform the user which modules will finally be uninstalled.
  if (empty($modules)) {
    return drush_log(dt('There were no modules that could be uninstalled.'), 'ok');
  }
  else {
    drush_print(dt('The following modules will be uninstalled: !modules', array('!modules' => implode(', ', $modules))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Disable the modules.
  drush_module_uninstall($modules);

  // Inform the user of final status.
  foreach ($modules as $module) {
    drush_log(dt('!module was successfully uninstalled.', array('!module' => $module)), 'ok');
  }
}

