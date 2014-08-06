<?php

function _drush_find_commandfiles($phase, $phase_max = FALSE) {
  if (!$phase_max) {
    $phase_max = $phase;
  }

  $searchpath = array();
  switch ($phase) {
    case DRUSH_BOOTSTRAP_DRUPAL_ROOT:
      $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
      $searchpath[] = $drupal_root . '/drush';
      $searchpath[] = $drupal_root . '/sites/all/drush';

      // Add the drupalboot.drush.inc commandfile.
      // $searchpath[] = __DIR__;
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_SITE:
      // If we are going to stop bootstrapping at the site, then
      // we will quickly add all commandfiles that we can find for
      // any module associated with the site, whether it is enabled
      // or not.  If we are, however, going to continue on to bootstrap
      // all the way to DRUSH_BOOTSTRAP_DRUPAL_FULL, then we will
      // instead wait for that phase, which will more carefully add
      // only those Drush commandfiles that are associated with
      // enabled modules.
      if ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
        $searchpath[] = conf_path() . '/modules';
        // Add all module paths, even disabled modules. Prefer speed over accuracy.
        $searchpath[] = 'sites/all/modules';
        // In D8, we search top level directories as well.
        if (drush_drupal_major_version() >=8) {
          $searchpath[] = 'modules';
        }

        // Adding commandfiles located within /profiles. Try to limit to one profile for speed. Note
        // that Drupal allows enabling modules from a non-active profile so this logic is kinda dodgy.
        $cid = drush_cid_install_profile();
        if ($cached = drush_cache_get($cid)) {
          $profile = $cached->data;
          $searchpath[] = "profiles/$profile/modules";
        }
        else {
          // If install_profile is not available, scan all profiles.
          $searchpath[] = "profiles";
          $searchpath[] = "sites/all/profiles";
        }
      }

      // TODO: Treat themes like modules and stop unconditionally searching here.
      $searchpath[] = 'sites/all/themes';
      $searchpath[] = conf_path() . '/themes';
      // In D8, we search top level directories as well.
      if (drush_drupal_major_version() >=8) {
        $searchpath[] = 'themes';
      }
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION:
      // Nothing to do here anymore. Left for documentation.
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_FULL:
      // Add enabled module paths. Since we are bootstrapped,
      // we can use the Drupal API.
      $ignored_modules = drush_get_option_list('ignored-modules', array());
      foreach (array_diff(drush_module_list(), $ignored_modules) as $module) {
        $filename = drupal_get_filename('module', $module);
        $searchpath[] = dirname($filename);
      }
      break;
  }

  _drush_add_commandfiles($searchpath, $phase);
}

/**
 * Check that a command has its declared dependencies available or have no
 * dependencies.
 *
 * @param $command
 *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
 *
 * @return
 *   TRUE if command is valid.
 */
function drush_enforce_requirement_drupal_dependencies(&$command) {
  // If the command bootstrap is DRUSH_BOOTSTRAP_MAX, then we will
  // allow the requirements to pass if we have not successfully
  // bootstrapped Drupal.  The combination of DRUSH_BOOTSTRAP_MAX
  // and 'drupal dependencies' indicates that the drush command
  // will use the dependent modules only if they are available.
  if ($command['bootstrap'] == DRUSH_BOOTSTRAP_MAX) {
    // If we have not bootstrapped, then let the dependencies pass;
    // if we have bootstrapped, then enforce them.
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      return TRUE;
    }
  }
  // If there are no drupal dependencies, then do nothing
  if (!empty($command['drupal dependencies'])) {
    foreach ($command['drupal dependencies'] as $dependency) {
      if(!drush_module_exists($dependency)) {
        $command['bootstrap_errors']['DRUSH_COMMAND_DEPENDENCY_ERROR'] = dt('Command !command needs the following modules installed/enabled to run: !dependencies.', array('!command' => $command['command'], '!dependencies' => implode(', ', $command['drupal dependencies'])));
        return FALSE;
      }
    }
  }
  return TRUE;
}

/**
 * Check if the given command belongs to a disabled module.
 *
 * @return array
 *   Array with a command-like bootstrap error or FALSE if Drupal was not
 *   bootstrapped fully or the command does not belong to a disabled module.
 */
function drush_command_belongs_to_disabled_module() {
  if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUPAL_SITE, DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
    $commands = drush_get_commands();
    $arguments = drush_get_arguments();
    $command_name = array_shift($arguments);
    if (isset($commands[$command_name])) {
      // We found it. Load its module name and set an error.
      if (is_array($commands[$command_name]['drupal dependencies']) && count($commands[$command_name]['drupal dependencies'])) {
        $modules = implode(', ', $commands[$command_name]['drupal dependencies']);
      }
      else {
        // The command does not define Drupal dependencies. Derive them.
        $command_files = drush_get_context('DRUSH_COMMAND_FILES', array());
        $command_path = $commands[$command_name]['path'] . DIRECTORY_SEPARATOR . $commands[$command_name]['commandfile'] . '.drush.inc';
        $modules = array_search($command_path, $command_files);
      }
      return array(
        'bootstrap_errors' => array(
          'DRUSH_COMMAND_DEPENDENCY_ERROR' => dt('Command !command needs the following module(s) enabled to run: !dependencies.', array(
            '!command' => $command_name,
            '!dependencies' => $modules,
          )),
        ),
      );
    }
  }

  return FALSE;
}
