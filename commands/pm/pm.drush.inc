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
    'core' => array(6),
  );
  $items['disable'] = array(
    'description' => 'Disable one or more modules.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules.',
    ),
    'core' => array(6),
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
    'core' => array(6),
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
      'drush dl drupal-5.x' => 'Download latest version of Drupal core for 5.x release', 
      'drush dl diff-6.x-2.0' => 'Download a specfic version of diff module.',
      'drush dl cck zen' => 'Download latest versions of Zen for my version of Drupal.',
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
function pm_enable() {  
  $command = drush_get_command();
  return pm_module_manage($command['arguments'], TRUE);
}

/**
 * Command callback. Disable one or more modules.
 */
function pm_disable() {
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
        return drush_log(dt('There were no modules that could be enabled.'), 'ok');
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
        return drush_log(dt('There were no modules that could be disabled.'), 'ok');
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
  $modules = array_unique(array_merge($modules, $dependencies));
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
 * Command callback. Execute updatecode.
 */
function pm_update() {
  drush_invoke("updatecode");
}

/**
 * Command callback. Execute updatecode and updatedb commands.
 */
function pm_post_update() {
  drush_backend_invoke("updatedb");
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
  else {
    $sitepath = '';
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
  $drupal_version_default = drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION', 6) . '.x';
  foreach($requests as $request) {
    $drupal_version = $drupal_version_default;
    $project_version = NULL;
    $version = NULL;
    // project-HEAD or project-5.x-1.0-beta
    // '5.x-' is optional, as is '-beta'
    preg_match('/-+(HEAD|(?:(\d+\.x)-+)?(\d+\.[\dx]+.*))$/', $request, $matches);
    if (isset($matches[1])) {
      if ($matches[1] == 'HEAD') {
        // HEAD branch (can't normally use this, but we manage it here and handle errors downstream).
        $drupal_version = NULL;
        $version = 'HEAD';
        $project_version = 'HEAD';
      }
      else if (!empty($matches[2])) {
        // We have a specified Drupal core version
        $drupal_version = trim($matches[2], '-.');
      }
      if (!empty($matches[3])) {
        // We have a specified project version
        $project_version = trim($matches[3], '-.');
        if (substr($project_version, -1, 1) == 'x') {
          // If a dev branch was requested, we add a -dev suffix. 
          $project_version .= '-dev';
        }
      }
      // The project is whatever we have prior to the version part of the request.
      $project = trim(substr($request, 0, strlen($request) - strlen($matches[0])), ' -');
    }
    else {
      // We have no version part, so we set the project directly (assumes the user wants the latest stable version).
      $project = $request;
    }
    if (empty($project)) {
      drush_set_error('DRUSH_PM_MISSING_PROJECT_NAME', 'Project name not found. Run drush help install for more information.');
    }
    if (!$version && $project_version) {
      // Set a full version string, if it is not set already, and we have a specific version in mind.
      $version = $drupal_version . '-' . $project_version;
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
function pm_dl() {
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
      if ($xml = @simplexml_load_file($url)) {
        if ($error = $xml->xpath('/error')) {
          drush_set_error('DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE', $error[0]);
        }
        else {
          // Try to get the specified release.
          if ($package['version']) {
            if ($releases = $xml->xpath("/project/releases/release[version='" . $package['version'] . "']")) {
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
