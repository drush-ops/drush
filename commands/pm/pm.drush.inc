<?php
// $Id$

/**
 * @file
 *  The drush Package Manager
 *
 * Terminology:
 * - Request: a requested package (string or keyed array), with a project name and (optionally) version.
 * - Project: a drupal.org project, such as cck or zen.
 * - Version: a requested version, such as 1.0 or 1.x-dev.
 * - Release: a specific release of a project, with associated metadata (from the drupal.org update service).
 * - Package: the collection of files that make up a release.
 */

/**
 * Project is a user requested version update.
 */
define('DRUSH_PM_REQUESTED_UPDATE', 101);

/**
 * User requested version already installed.
 */
define('DRUSH_PM_REQUESTED_CURRENT', 102);

/**
 * User requested version already installed.
 */
define('DRUSH_PM_NO_VERSION', 103);

/**
 * User requested version not found.
 */
define('DRUSH_PM_NOT_FOUND', 104);

/**
 * Implementation of hook_drush_help().
 */
function pm_drush_help($section) {
  switch ($section) {
    case 'drush:enable':
      return dt('Enable one or more modules. Enables dependant modules as well.');
    case 'drush:disable':
      return dt('Disable one or more modules. Disables dependant modules as well.');
    case 'drush:uninstall':
      return dt('Uninstall one or more modules. Modules must be disabled first.');
    case 'drush:statusmodules':
      return dt('Show enabled/disabled status for modules.');
    case 'drush:refresh':
      return dt('Refresh update status information. Run this before running update or updatecode commands.');
    case 'drush:updatecode':
      return dt("Display available update information and allow updating of all installed project code to the specified version (or latest by default). Note: The user is asked to confirm before the actual update. Use the --yes option to answer yes automatically.");
    case 'drush:update':
      return dt("Display available update information and allow updating of all installed projects to the specified version (or latest by default), followed by applying any database updates required (as with running update.php). Note: The user is asked to confirm before the actual update. Use the --yes option to answer yes automatically.");
      case 'drush:info':
      return dt("View all releases for a given project (modules, themes, profiles, translations). Useful for deciding which version to install/update.");
    case 'drush:dl':
      return dt("Quickly download projects (modules, themes, profiles, translations) from drupal.org. Automatically figures out which module version you want based on its latest release, or you may specify a particular version. Downloads drupal core as well.  If no destination is provided, defaults to a site specific modules directory if available, then to sites/all/modules if available, then to the current working directory.");
  }
}

/**
 * Implementation of hook_drush_command().
 */
function pm_drush_command() {
  $update = 'update';
  if (drush_drupal_major_version() == 5) {
    $update = 'update_status';
  }
  $engines = array(
    'engines' => array(
      'version_control' => 'Integration with VCS in order to easily commit your changes to projects.',
      'package_handler' => 'Determine how to download/checkout new projects and acquire updates to projects.',
    ),
  );
  
  $items['enable'] = array(
    'description' => 'Enable one or more modules.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules.',
    ),
  );
  $items['disable'] = array(
    'description' => 'Disable one or more modules.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules.',
    ),
  );
  // Install command is reserved for the download and enable of projects including dependencies. 
  // @see http://drupal.org/node/112692 for more information.
  // $items['install'] = array(
  //     'description' => 'Download and enable one or more modules',
  //   );
  $items['uninstall'] = array(
    'description' => 'Uninstall one or more modules.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules.',
    ),
  );
  $items['statusmodules'] = array(
    'description' => 'Show module enabled/disabled status',
    'callback' => 'pm_module_manage',
    'callback arguments' => array(array(), FALSE),
    'options' => array(
      '--pipe' => 'Returns a space delimited list of enabled modules.',
    ),
  );
  $items['refresh'] = array(
    'description' => 'Refresh update status information',
    'drupal dependencies' => array($update),
  );
  $items['updatecode'] = array(
    'description' => 'Update your project code',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'modules' => 'Optional. A space delimited list of installed projects to update (currently only modules).',
    ),
    'options' => array(
      '--backup-dir' => 'Specify a directory to backup packages into, defaults to a backup directory within your Drupal root.',
    ),
  ) + $engines;
  $items['update'] = array(
    'description' => 'Update your project code and apply any database updates required (update.php)',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'modules' => 'Optional. A space delimited list of installed projects to update (currently only modules).',
    ),
    'options' => array(
      '--backup-dir' => 'Specify a directory to backup packages into, defaults to a backup directory within your Drupal root.',
    ),
  );
  $items['info'] = array(
    'description' => 'Release information for a project',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'projects' => 'A space separated list of drupal.org project names.',
    ),
    'examples' => array(
      'drush info cck zen' => 'View releases for cck and Zen projects.',
    ),
  );
  $items['dl'] = array(
    'description' => 'Download core Drupal and projects like CCK, Zen, etc.',
    'examples' => array(
      'drush dl' => 'Download latest version of Drupal core.', 
      'drush dl drupal' => 'Download latest stable version of Drupal core', 
      'drush dl drupal-7.x' => 'Download latest 7.x development version of Drupal core', 
      'drush dl cck zen es' => 'Download latest versions of CCK, Zen and Spanish translations for my version of Drupal.',
      'drush dl og-1.3' => 'Download a specfic version of Organic groups module for my version of Drupal.',
      'drush dl diff-6.x-2.x' => 'Download a specific development branch of diff module for a specific Drupal version.',
    ),
    'arguments' => array(
      'projects' => 'A space separated list of project names, with optional version. Defaults to \'drupal\'',
    ),
    'options' => array(
      '--destination' => 'Path to which the project will be copied.',
    ), 
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  ) + $engines;
  return $items;
}

/**
 * Command callback. Enables one or more modules.
 */
function drush_pm_enable() {  
  $command = drush_get_command();
  return pm_module_manage($command['arguments'], TRUE);
}

/**
 * Command callback. Disable one or more modules.
 */
function drush_pm_disable() {
  $command = drush_get_command();
  return pm_module_manage($command['arguments'], FALSE);
}

/**
 * Command callback. Uninstall one or more modules.
 * // TODO: Use drupal_execute on system_modules_uninstall_confirm_form so that input is validated.
 */
function drush_pm_uninstall() {
  $command = drush_get_command();
  $modules = $command['arguments'];
    
  drush_print(dt('The following modules will be uninstalled: !modules', array('!modules' => implode(', ', $modules))));
  if(!drush_confirm(dt('Do you really want to continue?'))) {
    drush_die('Aborting.');
  }
  
  // Make sure the install API is available.
  require_once './includes/install.inc';
    
  foreach ($modules as $module) {
    // Minimalist validation.
    if (db_result(db_query("SELECT name FROM {system} WHERE name='%s' AND type = 'module' AND status = 0 AND schema_version > %d", $module, SCHEMA_UNINSTALLED))) {
      drupal_uninstall_module($module);
      drush_log(dt('!module was successfully uninstalled.', array('!module' => $module)), 'ok');
    }
    else {
      drush_set_error('DRUSH_PM_UNINSTALL_ACTIVE_MODULE', dt('!module is not disabled. If active, use `disable` command before `uninstall`.', array('!module' => $module)));
    }    
  }
}

function pm_module_manage($modules = array(), $enable = TRUE) {
  if (function_exists('module_load_include')) {
    module_load_include('inc', 'system', 'system.admin');
  }
  else {
    // Drupal5 only.
    require_once('./'. drupal_get_path('module', 'system') .'/system.module');
  }

  $module_info = module_rebuild_cache();
  $enabled = array();
  foreach ($module_info as $module_name => $module) {
    // In Drupal 5, system_modules() returns NULL for the dependency list of the module if there are no dependencies. 
    // We will override this to be an empty array instead to be compatible to Drupal 6 and 7
    if (!is_array($module->info['dependencies'])){
       $module->info['dependencies'] = array();
    }
    // Add enabled modules to an array for easy reference.
    if ($module_info[$module_name]->status) {
      $enabled[] = $module_name;
    }
  }
  if (empty($modules)) {
    pm_module_status($enabled, $module_info);
  }
  else {
    $requested_modules = $modules;
    if ($enable) {
      pm_dependencies($modules, $enabled, $module_info);
      if (empty($modules)) {
        return drush_log(dt('There were no modules that could be enabled.'), 'ok');
      }
      drush_print(dt('The following modules will be enabled: !modules', array('!modules' => implode(', ', $modules))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
      // We enable the modules explicitly here, to pass dependency validation in the form submit.
      module_enable($modules);
      $current = drupal_map_assoc($enabled, 'pm_true');
      // Add the list of enabled modules from the active modules list.
      $active_modules = array_merge($current, drupal_map_assoc($modules, 'pm_true'));
    }
    else {
      pm_reverse_dependencies($modules, $enabled, $module_info);
      if (empty($modules)) {
        return drush_log(dt('There were no modules that could be disabled.'), 'ok');
      }
      drush_print(dt('The following modules will be disabled: !modules', array('!modules' => implode(', ', $modules))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
      // We disable the modules explicitly here, to pass dependency validation in the form submit.
      module_disable($modules);
      // Remove the list of disabled modules from the active modules list.
      $active_modules = array_diff($enabled, $modules);
      $active_modules = drupal_map_assoc($active_modules, 'pm_true');
    }

    // We submit the system modules form - the modules should already be fully
    // enabled/disabled by this stage, but this makes sure any activities
    // triggered by the form submit (such as admin_role) are completed.
    $form_state = array('values' => array('status' => $active_modules));
    if (function_exists('drupal_execute')) {
      drupal_execute('system_modules', $form_state);
    }
    else {
      // Drupal 7.
      drupal_form_submit('system_modules', $form_state);
    }

    $module_info = module_rebuild_cache();
    foreach ($modules as $module_name) {
      if ($enable) { 
        if ($module_info[$module_name]->status) {
          drush_log(dt('!module was enabled successfully.', array('!module' => $module_info[$module_name]->info['name'])), 'ok');
        }
        else {
          drush_set_error('DRUSH_PM_ENABLE_MODULE_ISSUE', dt('> There was a problem enabling !module.', array('!module' => $module_name)));
        }
      }
      else { 
        if (!$module_info[$module_name]->status) {
          drush_log(dt('!module was disabled successfully.', array('!module' => $module_info[$module_name]->info['name'])), 'ok');
        }
        else {
          drush_set_error('DRUSH_PM_DISABLE_MODULE_ISSUE', dt('> There was a problem disabling !module.', array('!module' => $module_name)));
        }
      }
    }
  }
}

function pm_module_status($enabled, $module_info) {
  $rows[] = array(dt('Name'), dt('Status'), dt('Description'));
  foreach ($module_info as $module_name => $module) {
    $enabled = dt('Disabled');
    if ($module->status) {
      $enabled = dt('Enabled');
      $pipe[] = $module_name;
    }
    $info = $module_info[$module_name]->info;
    $rows[] = array($info['name'] . ' (' . $module_name . ')', $enabled, truncate_utf8($info['description'], 60, FALSE, TRUE));
  }
  drush_print_table($rows, TRUE);
  
  // Space delimited list for use by other scripts. Set the --pipe option.
  drush_print_pipe(implode(' ', $pipe));
}

/**
 * This calculates any modules that are the modules the user wants to enable
 * are depending on and enables them progressively so as to allow the
 * system dependency checking to proceed.
 **/
function pm_dependencies(&$modules, $enabled, $module_info) {
  $dependencies = array();
  foreach ($modules as $key => $module) {
    if (array_search($module, $enabled) !== FALSE) {
      // We find the module in the list of modules already enabled, ignore it.
      unset($modules[$key]);
      drush_set_error('DRUSH_PM_ALREADY_ENABLED', dt('!module is already enabled.', array('!module' => $module_info[$module]->info['name'])));
    }
    else if (isset($module_info[$module])) {
      // This module is available, check for dependencies that are not already enabled.
      $new_dependencies = array_diff($module_info[$module]->info['dependencies'], $enabled);
      $unmet_dependencies = array_diff($new_dependencies, array_keys($module_info));
      if (!empty($unmet_dependencies)) {
        unset($modules[$key]);
        $new_dependencies = array();
        drush_set_error('DRUSH_PM_ENABLE_DEPENDENCY_NOT FOUND', dt('Module !module cannot be enabled because it depends on the following modules which could not be found: !unmet_dependencies', array('!module' => $module, '!unmet_dependencies' => implode(',', $unmet_dependencies))));
      }
      // Store a list of dependencies so we can tell the user what we are going to do.
      // We can't update $modules, because that can cause dependencies to make the foreach loop forever.
      $dependencies = array_merge($dependencies, $new_dependencies);
    }
    else {
      // The module is not available to be activated, ignore it.
      unset($modules[$key]);
      drush_set_error('DRUSH_PM_ENABLE_MODULE_NOT FOUND', dt('Module !module was not found and will not be enabled.', array('!module' => $module)));
    }
  }
  $modules = array_unique(array_merge($modules, $dependencies));
}

/**
 * This calculates any modules that are depending on the modules the user
 * wants to disable, and disables them progressively so as to allow the
 * system dependency checking to proceed.
 */
function pm_reverse_dependencies(&$modules, $enabled, $module_info) {
  foreach ($modules as $key => $module) {
    if (array_search($module, $enabled) === FALSE) {
      unset($modules[$key]);
      drush_log(dt('!module is already disabled.', array('!module' => $module_info[$module]->info['name'])), 'warning');
    }
    foreach ($module_info as $dependent => $dependent_search) {
      if (array_search($module, $dependent_search->info['dependencies']) !== FALSE) {
        $modules[] = $dependent;
        module_disable(array($dependent));
      }
    }
  }
  $modules = array_intersect($enabled, $modules);
}

/**
 * Callback helper.
 */
function pm_true() {
  return TRUE;
}

/**
 * We need to set the project path by looking at the module location. Ideally, update.module would do this for us.
 */
function pm_get_project_path($projects, $lookup) {
  foreach ($projects as $project => $info) {
    if (!isset($info['path'])  && $project != 'drupal') {
      // looks for an enabled module.
      foreach ($info[$lookup] as $module => $name) {
        if ($path = drupal_get_path('module', $module)) {
          continue;
        }
      }
      // As some modules are not located in their project's root directory
      // but in a subdirectory (e.g. all the ecommerce modules), we take the module's
      // info file's path, and then move up until we are at a directory with the
      // project's name.
      $parts = explode('/', $path);
      $i = count($parts) - 1;
      $stop = array_search($project, $parts);
      while ($i > $stop) {
        unset($parts[$i]);
        $i--;
      }
      $projects[$project]['path'] = implode('/', $parts);
    }
  }
  return $projects;
}

/**
 * A drush command callback. Show release info for given project(s).
 *
 **/
function drush_pm_info() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  $projects = func_get_args();
  $projects = drupal_map_assoc($projects);
  $info = pm_get_project_info($projects);

  $rows[] = array(dt('Project'), dt('Release'), dt('Date'));
  foreach ($info as $key => $project) {
    foreach ($project['releases'] as $release) {

      $rows[] = array(
        $key,
        $release['version'],
        format_date($release['date'], 'custom', 'Y-M-d'),
      );
    }
  }
  
  if (count($rows) == 1) {
    return drush_set_error('DRUSH_PM_PROJECT_NOT_FOUND', dt('No information available.'));
  }
  else {
    return drush_print_table($rows, TRUE);
  }
}

/**
 * Command callback. Refresh update status information.
 */
function drush_pm_refresh() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  _pm_refresh();
}

/**
 * Command callback. Execute updatecode.
 */
function drush_pm_update() {
  drush_invoke("updatecode");
}

/**
 * Post-command callback. 
 * Execute updatedb command after an updatecode - user requested `update`.
 */
function drush_pm_post_update() {
  drush_backend_invoke("updatedb");
}

/**
 * Post-command callback for updatecode. Notify about any pending DB updates.
 */
function drush_pm_post_updatecode() {
  // Make sure the installation API is available
  include_once './includes/install.inc';
  
  // Load all .install files.
  drupal_load_updates();
  
  // @see system_requirements().
  foreach (module_list() as $module) {
    $updates = drupal_get_schema_versions($module);
    if ($updates !== FALSE) {
      $default = drupal_get_installed_schema_version($module);
      if (max($updates) > $default) {
        drush_log(dt("You have pending database updates. Please run `drush updatedb` or visit update.php in your browser."), 'warning');
        break;
      }
    }
  }
}


/**
 * Deletes a directory, all files in it and all subdirectories in it (recursively).
 * Use with care!
 * Written by Andreas Kalsch
 */
function delete_dir($dir) {
  if (substr($dir, strlen($dir)-1, 1) != '/')
    $dir .= '/';

  if ($handle = opendir($dir)) {
    while ($obj = readdir($handle)) {
      if ($obj != '.' && $obj != '..') {
        if (is_dir($dir.$obj)) {
          if (!delete_dir($dir.$obj)) {
            return false;
          }
        }
        elseif (is_file($dir.$obj)) {
          if (!unlink($dir.$obj)) {
            return false;
          }
        }
      }
    }

    closedir($handle);

    if (!@rmdir($dir)) {
      return false;
    }
    return true;
  }
  return false;
}

/**
 * Determine a candidate destination directory for a particular site path and
 * return it if it exists, optionally attempting to create the directory.
 */
function pm_dl_destination_lookup($type, $drupal_root, $sitepath, $create = FALSE) {
  switch ($type) {
    case 'module':
      $destination = $sitepath . 'modules/';
      break;
    case 'theme':
      $destination = $sitepath . 'themes/';
      break;
    case 'theme engine':
      $destination = $sitepath . 'themes/engines/';
      break;
    case 'translation':
      $destination = $drupal_root . '/';
      break;
    case 'profile':
      $destination = $drupal_root . '/profiles/';
      break;
  }
  if ($create) {
    drush_log(dt('Attempting to create destination directory at !dir', array('!dir' => $destination)));
    @drush_op('mkdir', $destination, 0777, TRUE);
  }
  if (is_dir($destination)) {
    drush_log(dt('Using destination directory !dir', array('!dir' => $destination)));
    return $destination;
  }
  drush_log(dt('Could not find destination directory at !dir', array('!dir' => $destination)));
  return FALSE;
}

/**
 * Return the best destination for a particular download type we can find,
 * given the drupal and site contexts.
 */
function pm_dl_destination($type) {
  // Attempt 0: Use the user specified destination directory, if it exists. 
  $destination = drush_get_option('destination');
  if (!empty($destination)) {
    $destination = rtrim($destination, '/') . '/';
    if (is_dir($destination)) {
      return $destination;
    }
    else {
      return drush_set_error('DRUSH_PM_NO_DESTINATION', dt('The destination directory !destination does not appear to exist.', array('!destination' => $destination)));
    }
  }
  
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  $site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT', FALSE);
  $full_site_root = $drupal_root .'/'. $site_root . '/';
  $sites_all = $drupal_root . '/sites/all/';

  $in_site_directory = FALSE;
  // Check if we are running within the site directory.
  if ($full_site_root == substr(drush_cwd() . '/', 0, strlen($full_site_root))) {
    $in_site_directory = TRUE;
  }

  // Attempt 1: If we are in a specific site directory, and the destination directory already exists, then we use that.
  if (empty($destination) && $site_root && $in_site_directory) {
    $destination = pm_dl_destination_lookup($type, $drupal_root, $full_site_root);
  }
  // Attempt 2: If the destination directory already exists for sites/all, then we use that.
  if (empty($destination) && $drupal_root) {
    $destination = pm_dl_destination_lookup($type, $drupal_root, $sites_all);
  }
  // Attempt 3: If a specific (non default) site directory exists and sites/all does not exist, then we create destination in the site specific directory.
  if (empty($destination) && $site_root && $site_root !== 'sites/default' && is_dir($full_site_root) && !is_dir($sites_all)) {
    $destination = pm_dl_destination_lookup($type, $drupal_root, $full_site_root, TRUE);
  }
  // Attempt 4: If sites/all exists, then we create destination in the sites/all directory.
  if (empty($destination) && is_dir($sites_all)) {
    $destination = pm_dl_destination_lookup($type, $drupal_root, $sites_all, TRUE);
  }
  // Attempt 5: If site directory exists (even default), then we create destination in the this directory.
  if (empty($destination) && $site_root && is_dir($full_site_root)) {
    $destination = pm_dl_destination_lookup($type, $drupal_root, $full_site_root, TRUE);
  }
  // Attempt 6: If we didn't find a valid directory yet (or we somehow found one that doesn't exist) we always fall back to the current directory.
  if (empty($destination) || !is_dir($destination)) {
    $destination = drush_cwd() . '/';
  }

  return $destination;
}

/**
 * Parse out the project name and version and return as a structured array
 *
 * @param $requests an array of project names
 */
function pm_parse_project_version($requests) {
  $requestdata = array();
  $drupal_version_default = drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION', 6) . '.x';
  $drupal_bootstrap = (drush_get_context('DRUSH_BOOTSTRAP_PHASE') > 0);
  foreach($requests as $request) {
    $drupal_version = $drupal_version_default;
    $project_version = NULL;
    $version = NULL;
    $project = NULL;
    // project-HEAD or project-5.x-1.0-beta
    // '5.x-' is optional, as is '-beta'
    preg_match('/-+(HEAD|(?:(\d+\.x)-+)?(\d+\.[\dx]+.*))$/', $request, $matches);
    if (isset($matches[1])) {
      // The project is whatever we have prior to the version part of the request.
      $project = trim(substr($request, 0, strlen($request) - strlen($matches[0])), ' -');

      if ($matches[1] == 'HEAD' || $matches[2] == 'HEAD') {
        drush_set_error('DRUSH_PM_HEAD', 'Can\'t download HEAD releases because Drupal.org project information only provides for numbered release nodes.');
        continue;
      }
      if (!empty($matches[2])) {
        // We have a specified Drupal core version.
        $drupal_version = trim($matches[2], '-.');
      }
      if (!empty($matches[3])) {
        if (!$drupal_bootstrap && empty($matches[2]) && $project != 'drupal') {
          // We are not working on a bootstrapped site, and the project is not Drupal itself,
          // so we assume this value is the Drupal core version and we want the stable project.
          $drupal_version = trim($matches[3], '-.');
        }
        else {
          // We are working on a bootstrapped site, or the user specified a Drupal version,
          // so this value must be a specified project version.
          $project_version = trim($matches[3], '-.');
          if (substr($project_version, -1, 1) == 'x') {
            // If a dev branch was requested, we add a -dev suffix. 
            $project_version .= '-dev';
          }
        }
      }
    }
    elseif (strpos($request, '-') === FALSE) {
      // We have no findable version part, so we set the project directly (assumes the user wants the latest stable version).
      $project = $request;
    }
    if (empty($project)) {
      drush_set_error('DRUSH_PM_MISSING_PROJECT_NAME', 'Project name not found. Run drush help install for more information.');
      continue;
    }
    if ($project_version) {
      if ($project == 'drupal') {
        // For project Drupal, ensure the major version branch is correct, so
        // we can locate the requested or stable release for that branch.
        $project_version_array = explode('.', $project_version);
        $drupal_version = $project_version_array[0] . '.x';
        // We use the project version only, since it is core.
        $version = $project_version;
      }
      else {
        // For reqular projects the version string includes the Drupal core version.
        $version = $drupal_version . '-' . $project_version;
      }
    }
    $requestdata[$project] = array(
      'name' => $project,
      'version' => $version,
      'drupal_version' => $drupal_version,
      'project_version' => $project_version,
    );
  }
  return $requestdata;
}

function pm_project_types() {
  // Lookup the 'Project type' vocabulary to some standard strings.
  $types = array(
    'core' => 'Drupal project',
    'profile' => 'Installation profiles',
    'module' => 'Modules',
    'theme' => 'Themes',
    'theme engine' => 'Theme engines',
    'translation' => 'Translations',
  );
  return $types;
}

/**
 * Used by dl and updatecode commands to determine how to download/checkout new projects and acquire updates to projects.
 */
function pm_drush_engine_package_handler() {
  return array(
    'wget' => array(),
    'cvs' => array(
      'options' => array(
        '--package-handler=cvs' => 'Use CVS to checkout and update projects.',
        '  --cvsparams' => 'Add options to the `cvs` program',
        '  --cvsmethod' => 'force cvs updates or checkouts (checkout is default unless the directory is managed by a supported version control system).'
      ),
      'examples' => array(
          'drush [command] cck --cvsparams=\"-C\"' =>  'Overwrite all local changes (Quotes are required).',
          'drush [command] cck --cvsmethod=update' =>  'Will update the project, and try to merge changes, rather than overwriting them. Any conflicts will need to be resolved manually.',
      ),
    ),
  );
}

/**
 * Integration with VCS in order to easily commit your changes to projects.
 */
function pm_drush_engine_version_control() {
  return array(
    'svn' => array(
      'options' => array(
        '--version-control=svn' => 'Quickly add/remove/commit your project changes to Subversion.',
        '  --svnsync' => 'Automatically add new files to the SVN repository and remove deleted files. Caution.',
        '  --svncommit' => 'Automatically commit changes to SVN repository. You must also using the --svnsync option.',
        '  --svnmessage' => 'Override default commit message which is: Drush automatic commit: <the drush command line used>',
        '  --svnstatusparams' => "Add options to the 'svn status' command",
        '  --svnaddparams' => 'Add options to the `svn add` command',
        '  --svnremoveparams' => 'Add options to the `svn remove` command',
        '  --svncommitparams' => 'Add options to the `svn commit` command',
      ),
      'examples' => array(
        'drush [command] cck --svncommitparams=\"--username joe\"' =>  'Commit changes as the user \'joe\' (Quotes are required).'
      ),
    ),
  );
}

/**
 * Command callback. Download drupal core.
 */
function drush_pm_dl() {
  // Bootstrap to the highest level possible.
  drush_bootstrap_max();

  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));
  drush_include_engine('version_control', drush_get_option('version-control', 'svn'));
  
  if (!$full_projects = func_get_args()) {
    $full_projects = array('drupal');
  }
  
  foreach ($full_projects as $full_project) {
    $requestdata = pm_parse_project_version(explode(' ', $full_project));
    $project_types = pm_project_types();
    $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
    foreach ($requestdata as $package) {
      $project = $package['name'];
      // Don't rely on UPDATE_DEFAULT_URL since we are not fully bootstrapped.
      $url = 'http://updates.drupal.org/release-history' . "/$project/". $package['drupal_version'];
      
      // A simple download, which is never available via CVS. 
      // Some hosts have allow_url_fopen disabled.
      if (!$xml = @simplexml_load_file($url)) {
        if (!drush_shell_exec("wget $url")) {
          drush_shell_exec("curl -O $url");
        }
        // Get the filename...
        $filename = explode('/', $url);
        $filename = array_pop($filename);
        $xml = simplexml_load_file($filename);
        drush_op('unlink', $filename);
      }
      
      if ($xml) {
        if ($error = $xml->xpath('/error')) {
          drush_set_error('DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE', $error[0]);
        }
        else {
          // Try to get the specified release.
          if ($package['version']) {
            if ($releases = $xml->xpath("/project/releases/release[status='published'][version='" . $package['version'] . "']")) {
              $release = (array)$releases[0];
            }
            if (empty($release)) {
              drush_log(dt("Could not locate specified project version, downloading latest stable version"), 'notice');
            }
          }
          // If that did not work, get the first published release for the recommended major version.
          if (empty($release)) {
            $recommended_major = $xml->xpath("/project/recommended_major");
            $xpath_releases = "/project/releases/release[status='published'][version_major=" . (string)$recommended_major[0] . "]";
            $releases = $xml->xpath($xpath_releases);
            $release = (array)$releases[0];
          }
          // Determine what type of project we have, so we know where to put it.
          $release['type'] = 'module';

          if ($types = $xml->xpath('/project/terms/term[name="Projects" and ' . $project_types_xpath . ']')) {
            $release['type'] = array_search($types[0]->value, $project_types);
          }

          if ($destination = pm_dl_destination($release['type'])) {
            if (package_handler_install_project($project, $release, $destination)) {
              drush_log(dt("Project !project (!version) downloaded to !dest.",
                             array('!project' => $project, '!version' => $release['version'], '!dest' => $destination)), 'success');
              drush_command_invoke_all('drush_pm_post_install', $project, $release, $destination);
              version_control_post_install($project, $release, $destination);
            }
          }
        }
      }
      else {
        // We are not getting here since drupal.org always serves an XML response.
        drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Could not download project status information from !url', array('!url' => $url)));
      }
    }
    
    unset($package, $error, $release, $types);
  }
}
