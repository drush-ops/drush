<?php
// $Id$

/**
 * @file
 *  The drush Package Manager
 *
 * Terminology:
 * - Request: a requested package (string or keyed array), with a project name and (optionally) version.
 * - Project: a drupal.org project, such as cck or drush.
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
 * Implementation of hook_drush_help().
 */
function pm_drush_help($section) {
  $handlers = str_replace('pm_', '', drush_command_invoke_all('pm_package_handler'));
  if ($handlers) {
    $handlers[0] = $handlers[0] .' (default)';
    $handler = '--handler - specify which package handler you would like to use.
  Available options: '. implode(', ', $handlers) . "\n";
  }
  else {
    $handler = "ERROR: You must activate a drush Package Manager handler module in order
     to install or update packages.";
  }

  switch ($section) {
    case 'drush:install':
      return dt("Usage: drush [options] pm install <package_1> <package_2> ...
<package_n> is the short name of a project hosted on drupal.org,
or the short name and the version number (Drupal major version is optional).
e.g. project, project-5.x-1.0, project-1.0, project-1.x-dev, project-1.1-rc1
So far, only modules are supported.\n
The modules will be installed into a site specific modules directory if one
exists, otherwise sites/all/modules is used.
After installing, you still have to activate on the normal module
administration page\n\n". $handler);

    case 'drush:update':
      return dt("Usage: drush [options] pm update\n
Displays update status information and allows to update all installed packages
to the specified version (or latest by default).
If you only want to update certain projects, pass those as additional
arguments (e.g. cck devel views ...).
Note: The user is asked to confirm before the actual update is started.
Use the -y option to answer all questions with yes automatically.\n\n". $handler);

    case 'drush:info':
      return dt("Usage: drush [options] pm info <package_1> <package_2> ...\n
View all releases for a given project. Useful for deciding which version to install/update.
    ");

    case 'drush:dl':
      return dt("Quickly download projects from drupal.org. Automatically figures out which module version you want based on its latest release, or you may specify a particular version. Downloads drupal core as well.  If no destination is provided, defaults to a site specific modules directory if available, then to sites/all/modules if available, then to the current working directory.");
  }
}

/**
 * Implementation of hook_drush_command().
 */
function pm_drush_command() {
  $items['enable'] = array(
    'description' => 'Enable one or more modules',
  );
  $items['disable'] = array(
    'description' => 'Disable one or more modules',
  );
  $items['uninstall'] = array(
    'description' => 'Uninstall one or more modules',
  );
  $items['status modules'] = array(
    'description' => 'Show module enabled/disabled status',
  );
  $items['refresh'] = array(
    'description' => 'Refresh update status information',
    'drupal dependencies' => array('update'),
  );
  $items['update'] = array(
    'description' => 'Update your modules',
    'drupal dependencies' => array('update'),
  );
  $items['info'] = array(
    'description' => 'Release information for a module',
    'drupal dependencies' => array('update'),
  );
  $items['dl'] = array(
    'description' => 'Download core Drupal and modules like Drush Extras.',
    'examples' => array(
      'drush dl drupal' => 'Download latest version of Drupal core.', 
      'drush dl drupal-5.x' => 'Download latest version of Drupal core for 5.x release', 
      'drush dl diff-6.x-2.0' => 'Download a specfic version of diff module.',
      'drush dl views' => 'Download latest versions of Views for my version of Drupal.',
    ),
    'arguments' => array(
      'project' => '\'drupal\', or module name, with optional version.',
    ),
    'options' => array(
      '--destination' => 'Path to which the project will be copied.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );
  return $items;
}

/**
 * Command callback. Enables one or more modules.
 */
function pm_enable () {  
  $command = drush_get_command();
}

/**
 * Command callback. Disable one or more modules.
 */
function pm_disable () {
  $command = drush_get_command();
  return pm_module_manage($command['arguments'], FALSE);
}

/**
 * Command callback. Uninstall one or more modules.
 * // TODO: Use drupal_execute on system_modules_uninstall_confirm_form so that input is validated.
 */
function pm_uninstall() {
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
  module_load_include('inc', 'system', 'system.admin');
  $form = system_modules();
  if (empty($modules)) {
    pm_module_status($form);
  }
  else {
    $requested_modules = $modules;
    if ($enable) {
      pm_dependencies($modules, $form);
      if (empty($modules)) {
        return drush_set_error('DRUSH_PM_ENABLE_NO_MODULES', dt('There were no modules that could be enabled.'));
      }
      drush_print(dt('The following modules will be enabled: !modules', array('!modules' => implode(', ', $modules))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
      // We enable the modules explicitly here, to pass dependency validation in the form submit.
      module_enable($modules);
      $current = drupal_map_assoc($form['status']['#default_value'], 'pm_true');
      // Add the list of enabled modules from the active modules list.
      $active_modules = array_merge($current, drupal_map_assoc($modules, 'pm_true'));
    }
    else {
      pm_reverse_dependencies($modules, $form);
      if (empty($modules)) {
        return drush_set_error('DRUSH_PM_DISABLE_NO_MODULES', dt('There were no modules that could be disabled.'));
      }
      drush_print(dt('The following modules will be disabled: !modules', array('!modules' => implode(', ', $modules))));
      if(!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
      // We disable the modules explicitly here, to pass dependency validation in the form submit.
      module_disable($modules);
      // Remove the list of disabled modules from the active modules list.
      $active_modules = array_diff($form['status']['#default_value'], $modules);
      $active_modules = drupal_map_assoc($active_modules, 'pm_true');
    }
    $form_state = array('values' => array('status' => $active_modules));
    drupal_execute('system_modules', $form_state);
    $form = system_modules();
    foreach (array_unique(array_merge($modules)) as $key => $module) {
      if ($enable) { 
        if (array_search($module, $form['status']['#default_value']) !== FALSE) {
          drush_log(dt('!module was enabled successfully.', array('!module' => $form['validation_modules']['#value'][$module]->info['name'])), 'ok');
        }
        else {
          drush_set_error('DRUSH_PM_ENABLE_MODULE_ISSUE', dt('> There was a problem enabling !module.', array('!module' => $module)));
        }
      }
      else { 
        if (array_search($module, $form['status']['#default_value']) === FALSE) {
          drush_log(dt('!module was disabled successfully.', array('!module' => $form['validation_modules']['#value'][$module]->info['name'])), 'ok');
        }
        else {
          drush_set_error('DRUSH_PM_DISABLE_MODULE_ISSUE', dt('> There was a problem disabling !module.', array('!module' => $module)));
        }
      }
    }
  }
}

function pm_module_status($form) {
  $rows[] = array(dt('Name'), dt('Enabled/Disabled'), dt('Description'));
  foreach ($form['status']['#options'] as $module => $value) {
    $enabled = dt('Disabled');
    if (array_search($module, $form['status']['#default_value']) !== FALSE) {
      $enabled = dt('Enabled');
    }
    $info = $form['validation_modules']['#value'][$module]->info;
    $rows[] = array($info['name'] . ' (' . $module . ')', $enabled, truncate_utf8($info['description'], 60, FALSE, TRUE));
  }
  drush_print_table($rows, 2, TRUE);
}

/**
 * This calculates any modules that are the modules the user wants to enable
 * are depending on and enables them progressively so as to allow the
 * system dependency checking to proceed.
 **/
function pm_dependencies(&$modules, $form) {
  $dependencies = array();
  foreach ($modules as $key => $module) {
    if (array_search($module, $form['status']['#default_value']) !== FALSE) {
      // We find the module in the list of modules already enabled, ignore it.
      unset($modules[$key]);
      drush_set_error('DRUSH_PM_ALREADY_ENABLED', dt('!module is already enabled.', array('!module' => $form['validation_modules']['#value'][$module]->info['name'])));
    }
    else if (isset($form['validation_modules']['#value'][$module])) {
      // This module is available, check for dependencies that are not already enabled.
      $new_dependencies = array_diff($form['validation_modules']['#value'][$module]->info['dependencies'], $form['status']['#default_value']);
      $unmet_dependencies = array_diff($new_dependencies, array_keys($form['validation_modules']['#value']));
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
  $modules = array_merge($modules, $dependencies);
}

/**
 * This calculates any modules that are depending on the modules the user
 * wants to disable, and disables them progressively so as to allow the
 * system dependency checking to proceed.
 */
function pm_reverse_dependencies(&$modules, $form) {
  foreach ($modules as $key => $module) {
    if (array_search($module, $form['status']['#default_value']) === FALSE) {
      unset($modules[$key]);
      drush_log(dt('!module is already disabled.', array('!module' => $form['validation_modules']['#value'][$module]->info['name'])), 'warning');
    }
    foreach ($form['validation_modules']['#value'] as $dependent => $dependent_search) {
      if (array_search($module, $dependent_search->info['dependencies']) !== FALSE) {
        $modules[] = $dependent;
        module_disable(array($dependent));
      }
    }
  }
  $modules = array_intersect($form['status']['#default_value'], $modules);
}

function pm_true() {
  return TRUE;
}

/**
 * Get the recommended release for a certain so far uninstalled project.
 *
 * @param $project A project information array for the requested project
 * @param $info A project information array for this project, as returned by an update service from pm_get_project_info()
 */
function pm_get_release($project, $info) {
  $minor = '';
  $version_patch_changed = '';

  if ($project['version']) {
    // The user specified a specific version - try to find that exact version
    foreach($info['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // Straight match
      if (!isset($recommended_version) && $release['version'] == $project['version']) {
        $recommended_version = $version;
      }
      // Shortcut match with ommitted Drupal version
      if (!isset($recommended_version) && $release['version'] == drush_drupal_major_version() . '.x-'. $project['version']) { // MW: TODO
        $recommended_version = $version;
      }
    }
  }
  else {
    // No version specified - try to find the best version we can
    foreach($info['releases'] as $version => $release) {
      // Ignore unpublished releases.
      if ($release['status'] != 'published') {
        continue;
      }

      // If we haven't found a recommended version yet, put the dev
      // version as recommended and hope it gets overwritten later.
      // Look for the 'latest version' if we haven't found it yet.
      // Latest version is defined as the most recent version for the
      // default major version.
      if (!isset($latest_version) && $release['version_major'] == $info['default_major']) {
        $latest_version = $version;
      }

      if (!isset($recommended_version) && $release['version_major'] == $info['default_major']) {
        if ($minor != $release['version_patch']) {
          $minor = $release['version_patch'];
          $version_patch_changed = $version;
        }
        if (empty($release['version_extra']) && $minor == $release['version_patch']) {
          $recommended_version = $version_patch_changed;
        }
        continue;
      }
    }
  }

  if (isset($recommended_version)) {
    return $info['releases'][$recommended_version];
  }
  else if (isset($latest_version)) {
    return $info['releases'][$latest_version];
  }
  else {
    return false;
  }
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
function pm_info() {
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
    return drush_print_table($rows, FALSE, TRUE);
  }
}

/**
 * Command callback. Refresh update status information.
 */
function pm_refresh() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  _pm_refresh();
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


function pm_dl_destination($type) {
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
  $site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT', false); 

  // If a URI is provided and we bootstrapped successfully then we install to
  // that specific site, otherwise we install to sites/all/modules
  if ($site_root) {
    $sitepath = $drupal_root .'/'. $site_root .'/';
  }
  else if ($drupal_root) {
    $sitepath = $drupal_root . '/sites/all/';
  }

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
      $destination = $drupal_root . 'profiles/';
      break;
  }

  // By default (including for core) we always fall back to the current directory.
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
  foreach($requests as $request) {
    // project-HEAD or project-5.x-1.0-beta
    // '5.x-' is optional, as is '-beta'
    preg_match('/-(HEAD|(\d+)\.([\dx]+)(-.+)?)$/', $request, $matches);
    if (!empty($matches[0])) {
      // Specific version requested
      $version = $matches[0];
      $project = substr($request, 0, strlen($request) - strlen($version));
    }
    else {
      // Recommended stable version requested
      $project = $request;
      $version = NULL;
    }
    if (empty($project)) {
      drush_set_error('DRUSH_PM_MISSING_PROJECT_NAME', 'Project name not found. Run drush help pm install for more information.');
    }
    $requestdata[$project] = array(
      'name' => $project,
      'version' => trim($version, ' -'),
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
 * Used by dl and update commands to determine how to download/checkout new projects and acquire updates to projects.
 */
function pm_drush_engine_package_handler() {
  return array(
    'wget' => array(),
    'cvs' => array(
      'description' => 'Use CVS to checkout and update projects.',
      'options' => array(
        '--cvsparams' => 'Add options to the cvs command',
      ),
      'examples' => array(
          '--cvsparams=\"-C\"' =>  'Overwrite all local changes (note that quotes are required)',
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
      'description' => 'Quickly add/remove/commit your project changes to Subversion.',
      'options' => array(
        '--svnsync' => 'Automatically add new files to the SVN repository and remove deleted files. CAUTION.',
        '--svncommit' => 'Automatically commit changes to SVN repository. You must also using the --svnsync option.',
        '--svnmessage' - 'Override default commit message which is: Drush automatic commit: <the drush command line used>',
        '--svnstatusparams' => "Add options to the 'svn status' command",
        '--svnaddparams' => 'Add options to the `svn add` command',
        '--svnremoveparams' => 'Add options to the `svn remove` command',
        '--svncommitparams' => 'Add options to the `svn commit` command',
      ),
      'examples' => array(
        'drush dl votingapi --svncommitparams=\"--username joe\"' =>  'Commit changes as the user \'joe\' (Note that quotes are required).'
      ),
    ),
  );
}

/**
 * Command callback. Download drupal core.
 */
function pm_dl($full_project = NULL) {
  if (empty($full_project)) {
    return drush_set_error('DRUSH_DL_EMPTY_PROJECT', "Please tell me what to download. (eg. `drush dl views` or `drush dl diff-6.x-2.0`)");
  }

  $phases = _drush_bootstrap_phases(); 
  $phase_index = DRUSH_BOOTSTRAP_DRUSH;

  // Try to bootstrap to the maximum possible level, without generating errors
  foreach ($phases as $phase_index) {
    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
        drush_bootstrap($phase_index);
      }
    }
    else {
      break;
    }
  }

  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));
  drush_include_engine('version_control', drush_get_option('version-control', 'svn'));
  
  $requestdata = pm_parse_project_version(explode(' ', $full_project));

  $project_types = pm_project_types();
  $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
  foreach ($requestdata as $package) {
    $version_default = drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION', 6);
    $version_default .= '.x';

    $version = empty($package['version']) ? $version_default : $package['version'];
    $project = $package['name'];
    $major_version = ($project == 'drupal') ? substr($version, 0, 1) .'.x' : substr($version, 0, 3);
    // Don't rely on UPDATE_DEFAULT_URL since we are not fully bootstrapped.
    $url = 'http://updates.drupal.org/release-history' . "/$project/". $major_version;
    if ($xml = @simplexml_load_file($url)) {
      if ($error = $xml->xpath('/error')) {
        drush_set_error('DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE', $error[0]);
      }
      else {
        // Try to get the specified release.
        if ($package['version']) {
          if ($releases = $xml->xpath("/project/releases/release[version='$version']")) {
            $release = (array)$releases[0];
          }
        }
        // If that did not work, get the first listed release.
        if (empty($release)) {
          $release = (array)$xml->releases->release;
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
}
