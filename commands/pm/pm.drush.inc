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
define('DRUSH_PM_REQUESTED_NOT_FOUND', 104);

/**
 * Sort callback function for sorting projects
 * First by type, second by package and third by name
 *
 * Special handling for 'Package' on modules and themes.
 */
function _drush_pm_sort_projects($a, $b) {
  if ($a->type == 'module' && $b->type == 'theme') {
    return -1;
  }
  if ($a->type == 'theme' && $b->type == 'module') {
    return 1;
  }
  $cmp = strcasecmp($a->info['package'], $b->info['package']);
  if ($cmp == 0) {
    $cmp = strcasecmp($a->info['name'], $b->info['name']);
  }
  return $cmp;
}

/**
 * Implementation of hook_drush_help().
 */
function pm_drush_help($section) {
  switch ($section) {
    case 'drush:pm-enable':
      return dt('Enable one or more modules or themes. Enable dependant modules as well.');
    case 'drush:pm-disable':
      return dt('Disable one or more modules or themes. Disable dependant modules as well.');
    case 'drush:pm-info':
      return dt('Show detailed info for one or more projects.');
    case 'drush:pm-uninstall':
      return dt('Uninstall one or more modules. Modules must be disabled first.');
    case 'drush:pm-list':
      return dt('Show a list of available modules and themes.');
    case 'drush:pm-refresh':
      return dt('Refresh update status information. Run this before running update or updatecode commands.');
    case 'drush:pm-updatecode':
      return dt("Display available update information and allow updating of all installed project code to the specified version (or latest by default). Note: The user is asked to confirm before the actual update. Use the --yes option to answer yes automatically.");
    case 'drush:pm-update':
      return dt("Display available update information and allow updating of all installed projects to the specified version (or latest by default), followed by applying any database updates required (as with running update.php). Note: The user is asked to confirm before the actual update. Use the --yes option to answer yes automatically.");
      case 'drush:pm-releases':
      return dt("View all releases for a given project (modules, themes, profiles, translations). Useful for deciding which version to install/update.");
    case 'drush:pm-download':
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
  $update_options = array(
    '--lock' => 'Add a persistent lock to remove the specified projects from consideration during updates.  Locks may be removed with the --unlock parameter, or overridden by specifically naming the module as a parameter to pm-update or pm-updatecode.  The lock does not affect pm-download.',
  );
  $update_suboptions = array(
    '--lock' => array(
      '--lock-message' => 'A brief message explaining why a project is being locked; displayed during pm-updatecode.  Optional.',
      '--unlock' => 'Remove the persistent lock from the specified projects so that they may be updated again.',
    ),
  );
  
  $items['pm-enable'] = array(
    'description' => 'Enable one or more modules or themes.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules or themes. You can use the * wildcard at the end of module and theme names to to enable all matches.',
    ),
    'aliases' => array('en'),
    'deprecated-aliases' => array('enable'),
  );
  $items['pm-disable'] = array(
    'description' => 'Disable one or more modules or themes.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules or themes. You can use the * wildcard at the end of module and theme names to disable multiple matches.',
    ),
    'aliases' => array('dis'),
    'deprecated-aliases' => array('disable'),
  );
  $items['pm-info'] = array(
    'description' => 'Show info for one or more projects.',
    'arguments' => array(
      'projects' => 'A space delimited list of projects. You can use the * wildcard at the end of module names to get info for the project and all its sub projects.',
    ),
  );
  // Install command is reserved for the download and enable of projects including dependencies.
  // @see http://drupal.org/node/112692 for more information.
  // $items['install'] = array(
  //     'description' => 'Download and enable one or more modules',
  //   );
  $items['pm-uninstall'] = array(
    'description' => 'Uninstall one or more modules.',
    'arguments' => array(
      'modules' => 'A space delimited list of modules.',
    ),
    'deprecated-aliases' => array('uninstall'),
  );
  $items['pm-list'] = array(
    'description' => 'Show a list of available modules and themes',
    'callback arguments' => array(array(), FALSE),
    'options' => array(
      '--type' => 'Filter by project type. Choices: module, theme.',
      '--status' => 'Filter by project status. Choices: enabled,disable and/or \'not installed\'. You can use multiple comma separated values. (i.e. --status="disabled,not installed").',
      '--package' => 'Filter by project packages. You can use multiple comma separated values. (i.e. --package="Core - required,Other").',
      '--pipe' => 'Returns a space delimited list of the names of the resulting projects.',
    ),
    'aliases' => array('sm'),
    'deprecated-aliases' => array('statusmodules'),
  );
  $items['pm-refresh'] = array(
    'description' => 'Refresh update status information',
    'drupal dependencies' => array($update),
    'aliases' => array('rf'),
    'deprecated-aliases' => array('refresh'),
  );
  $items['pm-updatecode'] = array(
    'description' => 'Update your project code',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'projects' => 'Optional. A space delimited list of installed projects (modules or themes) to update.',
    ),
    'options' => array(
      '--pipe' => 'Returns a space delimited list of enabled modules and their respective version and update information, one module per line. Order: module name, current version, recommended version, update status.',
    ) + $update_options,
    'sub-options' => $update_suboptions,
    'aliases' => array('upc'),
    'deprecated-aliases' => array('updatecode'),
  ) + $engines;
  
  // Merge all items from above.
  $items['pm-update'] = array_merge($items['pm-updatecode'], array(
    'description' => 'Update your project code and apply any database updates required (update.php)',
    'aliases' => array('up'),
    'deprecated-aliases' => array('update'),
  ));
  
  $items['pm-releases'] = array(
    'description' => 'Release information for a project',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'projects' => 'A space separated list of drupal.org project names.',
    ),
    'examples' => array(
      'drush pm-releases cck zen' => 'View releases for cck and Zen projects.',
    ),
    'deprecated-aliases' => array('info'),
  );
  $items['pm-download'] = array(
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
      '--source' => 'The base URL which provides project release history in XML. Defaults to http://updates.drupal.org/release-history.',
      '--variant' => "Only useful for install profiles. Possible values: 'core', 'no-core', 'make'.",
      '--drupal-project-rename' => 'Alternate name for "drupal" directory when downloading drupal project.',
      '--pipe' => 'Returns a list of the names of the modules and themes contained in the downloaded projects.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
    'aliases' => array('dl'),
    'deprecated-aliases' => array('download'),
  ) + $engines;
  return $items;
}

/**
 * Command callback. Show a list of modules and status.
 *
 */
function drush_pm_list() {
  //--package
  $package_filter = array();
  $package = strtolower(drush_get_option('package'));
  if (!empty($package)) {
    $package_filter = explode(',', $package);
  }
  if (empty($package_filter) || count($package_filter) > 1) {
    $header[] = dt('Package');
  }

  $header[] = dt('Name');

  //--type
  $all_types = array('module', 'theme');
  $type_filter = strtolower(drush_get_option('type'));
  if (!empty($type_filter)) {
    $type_filter = explode(',', $type_filter);
  }
  else {
    $type_filter = $all_types;
  }

  if (count($type_filter) > 1) {
    $header[] = dt('Type');
  }
  foreach ($type_filter as $type) {
    if (!in_array($type, $all_types)) { //TODO: this kind of chck can be implemented drush-wide
      return drush_set_error('DRUSH_PM_INVALID_PROJECT_TYPE', dt('!type is not a valid project type.', array('!type' => $type)));
  }
  }

  //--status
  $all_status = array('enabled', 'disabled', 'not installed');
  $status_filter = strtolower(drush_get_option('status'));
  if (!empty($status_filter)) {
    $status_filter = explode(',', $status_filter);
  }
  else {
    $status_filter = $all_status;
  }
  if (count($status_filter) > 1) {
    $header[] = dt('Status');
  }

  foreach ($status_filter as $status) {
    if (!in_array($status, $status_filter)) { //TODO: this kind of chck can be implemented drush-wide
      return drush_set_error('DRUSH_PM_INVALID_PROJECT_TYPE', dt('!status is not a valid project status.', array('!status' => $status)));
  }
  }

  $header[] = dt('Version');
  $rows[] = $header;

  $projects = drush_pm_get_projects();
  uasort($projects, '_drush_pm_sort_projects');

  $major_version = drush_drupal_major_version();
  foreach ($projects as $project) {
    if (!in_array($project->type, $type_filter)) {
      continue;
    }
    $status = drush_get_project_status($project);
    if (!in_array($status, $status_filter)) {
      continue;
    }
    if (($major_version >= 6) and (isset($project->info['hidden']))) {
      continue;
    }

    // filter by package
    if (!empty($package_filter)) {
      if (!in_array(strtolower($project->info['package']), $package_filter)) {
        continue;
      }
    }

    if (empty($package_filter) || count($package_filter) > 1) {
      $row[] = $project->info['package'];
    }

    if (($major_version >= 6)||($project->type == 'module')) {
      $row[] = $project->info['name'].' ('.$project->name.')';
    }
    else {
      $row[] = $project->name;
    }
    if (count($type_filter) > 1) {
      $row[] = ucfirst($project->type);
    }
    if (count($status_filter) > 1) {
      $row[] = ucfirst($status);
    }
    if (($major_version >= 6)||($project->type == 'module')) {
      // Suppress notice when version is not present.
      $row[] = @$project->info['version'];
    }

    $rows[] = $row;
    $pipe[] = $project->name;
    unset($row);
  }
  drush_print_table($rows, TRUE);

  if (isset($pipe)) {
    // Newline-delimited list for use by other scripts. Set the --pipe option.
    drush_print_pipe($pipe);
  }
}

/**
 * Command callback. Enable one or more projects.
 */
function drush_pm_enable() {
  $args = func_get_args();

  $project_info = drush_get_projects();

  // Classify $args in themes, modules or unknown.
  $modules = array();
  $themes = array();
  drush_pm_classify_projects($args, $modules, $themes, $project_info);
  $projects = array_merge($modules, $themes);
  $unknown = array_diff($args, $projects);

  // Discard and set an error for each unknown project.
  foreach ($unknown as $project) {
    drush_set_error('DRUSH_PM_ENABLE_PROJECT_NOT_FOUND', dt('!project was not found and will not be enabled.', array('!project' => $project)));
  }

  // Discard already enabled projects.
  foreach ($projects as $project) {
    if ($project_info[$project]->status) {
      if ($project_info[$project]->type == 'module') {
        unset($modules[$project]);
      }
      else {
        unset($themes[$project]);
      }
      drush_log(dt('!project is already enabled.', array('!project' => $project)), 'ok');
    }
  }

  if (!empty($modules)) {
    // Check module dependencies.
    $dependencies = drush_check_module_dependencies($modules, $project_info);
    $all_dependencies = array();
    foreach ($dependencies as $key => $info) {
      if (isset($info['error'])) {
        unset($modules[$key]);
        drush_set_error($info['error']['code'], $info['error']['message']);
      }
      elseif (!empty($info['dependencies'])) {
        // Make sure we have an assoc array.
        $assoc = drupal_map_assoc($info['dependencies']);
        $all_dependencies = array_merge($all_dependencies, $assoc);
      }
    }
    $enabled = array_keys(array_filter(drush_get_modules(), 'pm_is_enabled'));
    $needed = array_diff($all_dependencies, $enabled);
    $modules = $needed + $modules;

    // Discard modules which doesn't meet requirements.
    require_once drush_get_context('DRUSH_DRUPAL_ROOT') . '/includes/install.inc';
    foreach ($modules as $key => $module) {
      // Check to see if the module can be installed/enabled (hook_requirements).
      // See @system_modules_submit
      if (!drupal_check_module($module)) {
        unset($modules[$key]);
        drush_set_error('DRUSH_PM_ENABLE_MODULE_UNMEET_REQUIREMENTS', dt('Module !module doesn\'t meet the requirements to be enabled.', array('!module' => $module)));
      }
    }
  }

  // Inform the user which projects will finally be enabled.
  $projects = array_merge($modules, $themes);
  if (empty($projects)) {
    return drush_log(dt('There were no projects that could be enabled.'), 'ok');
  }
  else {
    drush_print(dt('The following projects will be enabled: !projects', array('!projects' => implode(', ', $projects))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
  }

  // Enable themes.
  if (!empty($themes)) {
    drush_theme_enable($themes);
  }

  // Enable modules and pass dependency validation in form submit.
  if (!empty($modules)) {
    drush_module_enable($modules);
    $current = drupal_map_assoc($enabled, 'pm_true');
    $processed = drupal_map_assoc($modules, 'pm_true');
    $active_modules = array_merge($current, $processed);
    drush_system_modules_form_submit($active_modules);
  }

  // Inform the user of final status.
  $rsc = drush_db_select('system', array('name', 'status'), 'name IN (:projects)', array(':projects' => $projects));
  while ($project = drush_db_fetch_object($rsc)) {
    if ($project->status) {
      drush_log(dt('!project was enabled successfully.', array('!project' => $project->name)), 'ok');
    }
    else {
      drush_set_error('DRUSH_PM_ENABLE_PROJECT_ISSUE', dt('There was a problem enabling !project.', array('!project' => $project->name)));
    }
  }
}

/**
 * Command callback. Disable one or more projects.
 */
function drush_pm_disable() {
  $args = func_get_args();

  $project_info = drush_get_projects();

  // classify $args in themes, modules or unknown
  $modules = array();
  $themes = array();
  drush_pm_classify_projects($args, $modules, $themes, $project_info);
  $projects = array_merge($modules, $themes);
  $unknown = array_diff($args, $projects);

  // Discard and set an error for each unknown project.
  foreach ($unknown as $project) {
    drush_set_error('DRUSH_PM_ENABLE_PROJECT_NOT_FOUND', dt('!project was not found and will not be disabled.', array('!project' => $project)));
  }

  // Discard already disabled projects.
  foreach ($projects as $project) {
    if (!$project_info[$project]->status) {
      if ($project_info[$project]->type == 'module') {
        unset($modules[$project]);
      }
      else {
        unset($themes[$project]);
      }
      drush_log(dt('!project is already disabled.', array('!project' => $project)), 'ok');
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
    $enabled = array_keys(array_filter(drush_get_modules(), 'pm_is_enabled'));
    $dependents = drush_module_dependents($modules, $project_info);
    $dependents = array_unique($dependents);
    $dependents = array_intersect($dependents, $enabled);
    $modules = array_merge($modules, $dependents);

    // Discard required modules.
    $required = drupal_required_modules();
    foreach ($required as $module) {
      if (isset($modules[$module])) {
        unset($modules[$module]);
        // No message for hidden modules.
        if (!isset($project_info[$module]->info['hidden'])) {
          drush_log(dt('!module is a required module and can\'t be disabled.', array('!module' => $module)), 'ok'); 
        }
      }
    }
  }

  // Inform the user which projects will finally be disabled.
  $projects = array_merge($modules, $themes);
  if (empty($projects)) {
    return drush_log(dt('There were no projects that could be disabled.'), 'ok');
  }
  else {
    drush_print(dt('The following projects will be disabled: !projects', array('!projects' => implode(', ', $projects))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
  }

  // Disable themes.
  if (!empty($themes)) {
    drush_theme_disable($themes);
  }

  // Disable modules and pass dependency validation in form submit.
  if (!empty($modules)) {
    drush_module_disable($modules);
    $active_modules = array_diff($enabled, $modules);
    $active_modules = drupal_map_assoc($active_modules, 'pm_true');
    drush_system_modules_form_submit($active_modules);
  }

  // Inform the user of final status.
  $rsc = drush_db_select('system', array('name', 'status'), 'name IN (:projects)', array(':projects' => $projects));
  while ($project = drush_db_fetch_object($rsc)) {
    if (!$project->status) {
      drush_log(dt('!project was disabled successfully.', array('!project' => $project->name)), 'ok');
    }
    else {
      drush_set_error('DRUSH_PM_DISABLE_PROJECT_ISSUE', dt('There was a problem disabling !project.', array('!project' => $project->name)));
    }
  }
}

/**
 * Wrapper of drupal_get_projects() with additional information used by
 * pm- commands.
 *
 * @return
 *   An array containing info for all available modules and themes w/additional
 *   info.
 */
function drush_pm_get_projects() {
  $projects = drush_get_projects();
  foreach ($projects as $key => $project) {
    if (empty($project->info['package'])) {
      $projects[$key]->info['package'] = dt('Other');
    }
  }
  return $projects;
}

/**
 * Classify projects in modules, themes or unknown ones.
 *
 * @param $projects
 *   Array of project names, by reference.
 * @param $modules
 *   Empty array to be filled with modules in $projects.
 * @param $themes
 *   Empty array to be filled with themes in $projects.
 */
function drush_pm_classify_projects(&$projects, &$modules, &$themes, $project_info) {
  _drush_pm_expand_projects($projects, $project_info);
  foreach ($projects as $project) {
    if (!isset($project_info[$project])) {
      continue;
    }
    if ($project_info[$project]->type == 'module') {
      $modules[$project] = $project;
    }
    else if ($project_info[$project]->type == 'theme') {
      $themes[$project] = $project;
    }
  }
}

/**
 * Command callback. Show detailed info for one or more projects.
 */
function drush_pm_info() {
  $args = func_get_args();

  $project_info = drush_pm_get_projects();
  _drush_pm_expand_projects($args, $project_info);

  foreach ($args as $project) {
    if (isset($project_info[$project])) {
      $info = $project_info[$project];
    }
    else {
      drush_set_error('DRUSH_PM_INFO_PROJECT_NOT_FOUND', dt('!project was not found.', array('!project' => $project)));
      continue;
    }
    if ($info->type == 'module') {
      $data = _drush_pm_info_module($info);
    }
    else {
      $data = _drush_pm_info_theme($info);
    }
    drush_print_table(drush_key_value_to_array_table($data));
    print "\n";
  }
}

/**
 * Return a string with general info of a project (module or theme).
 */
function _drush_pm_info_project($info) {
  $major_version = drush_drupal_major_version();

  $data['Project'] = $info->name;
  $data['Type'] = $info->type;
  if (($info->type == 'module')||($major_version >= 6)) {
    $data['Title'] = $info->info['name'];
    $data['Description'] = $info->info['description'];
    $data['Version'] = $info->info['version'];
  }
  $data['Package'] = $info->info['package'];
  if ($major_version >= 6) {
    $data['Core'] = $info->info['core'];
  }
  if ($major_version == 6) {
    $data['PHP'] = $info->info['php'];
  }
  $data['Status'] = drush_get_project_status($info);
  $path = (($info->type == 'module')&&($major_version == 7))?$info->uri:$info->filename;
  $path = substr($path, 0, strrpos($path, '/'));
  $data['Path'] = $path;

  return $data;
}

/**
 * Return a string with info of a module.
 */
function _drush_pm_info_module($info) {
  $major_version = drush_drupal_major_version();

  $data = _drush_pm_info_project($info);
  if ($info->schema_version > 0) {
    $schema_version = $info->schema_version;
  }
  elseif ($info->schema_version == -1) {
    $schema_version = "no schema installed";
  }
  else {
      $schema_version = "module has no schema";
  }
  $data['Schema version'] = $schema_version;
  if ($major_version == 7) {
    $data['Files'] = implode(', ', $info->info['files']);
  }
  if (count($info->info['dependencies']) > 0) {
    $requires = implode(', ', $info->info['dependencies']);
  }
  else {
    $requires = "none";
  }
  $data['Requires'] = $requires;

  if ($major_version == 6) {
    if (count($info->info['dependents']) > 0) {
      $requiredby = implode(', ', $info->info['dependents']);
    }
    else {
      $requiredby = "none";
    }
    $data['Required by'] = $requiredby;
  }

  return $data;
}

/**
 * Return a string with info of a theme.
 */
function _drush_pm_info_theme($info) {
  $major_version = drush_drupal_major_version();

  $data = _drush_pm_info_project($info);
  if ($major_version == 5) {
    $data['Engine'] = $info->description;
  }
  else {
    $data['Core'] = $info->info['core'];
    $data['PHP'] = $info->info['php'];
    $data['Engine'] = $info->info['engine'];
    $data['Base theme'] = isset($info->base_themes) ? implode($info->base_themes, ', ') : '';
    $regions = implode(', ', $info->info['regions']);
    $data['Regions'] = $regions;
    $features = implode(', ', $info->info['features']);
    $data['Features'] = $features;
    if (count($info->info['stylesheets']) > 0) {
      $data['Stylesheets'] = '';
      foreach ($info->info['stylesheets'] as $media => $files) {
        $files = implode(', ', array_keys($files));
        $data['Media '.$media] = $files;
      }
    }
    if (count($info->info['scripts']) > 0) {
      $scripts = implode(', ', array_keys($info->info['scripts']));
      $data['Scripts'] = $scripts;
    }
  }

  return $data;
}

/**
 * Add sub projects that match project_name*.
 *
 * A helper function for commands that take a space separated list of project
 * names. It will identify project names that have been passed in with a
 * trailing * and add all matching projects to the array that is returned.
 *
 * @param $projects
 *   An array of projects, by reference.
 * @param $project_info
 *   Optional. An array of project info as returned by drush_get_projects().
 */
function _drush_pm_expand_projects(&$projects, $project_info = array()) {
  if (empty($project_info)) {
    $project_info = drush_get_projects();
  }
  foreach ($projects as $key => $project) {
    if (($wildcard = rtrim($project, '*')) !== $project) {
      foreach (array_keys($project_info) as $project_name) {
        if (strpos($project_name, $wildcard) !== FALSE) {
          $projects[] = $project_name;
        }
      }
      unset($projects[$key]);
      continue;
    }
  }
}

/**
 * Command callback. Uninstall one or more modules.
 * // TODO: Use drupal_execute on system_modules_uninstall_confirm_form so that input is validated.
 */
function drush_pm_uninstall() {
  $modules = func_get_args();

  drush_include_engine('drupal', 'environment');
  $module_info = drush_get_modules();

  // Discards modules which are enabled, not found or already uninstalled.
  foreach ($modules as $key => $module) {
    if (!isset($module_info[$module])) {
      // The module does not exist in the system.
      unset($modules[$key]);
      drush_set_error('DRUSH_PM_ENABLE_MODULE_NOT_FOUND', dt('Module !module was not found and will not be uninstalled.', array('!module' => $module)));
    }
    else if ($module_info[$module]->status) {
      // The module is enabled.
      unset($modules[$key]);
      drush_set_error('DRUSH_PM_UNINSTALL_ACTIVE_MODULE', dt('!module is not disabled. Use `pm-disable` command before `pm-uninstall`.', array('!module' => $module)));
    }
    else if ($module_info[$module]->schema_version == -1) { // SCHEMA_UNINSTALLED
      // The module is uninstalled.
      unset($modules[$key]);
      drush_log(dt('!module is already uninstalled.', array('!module' => $module)), 'ok');
    }
  }

  // Inform the user which modules will finally be uninstalled.
  if (empty($modules)) {
    return drush_log(dt('There were no modules that could be uninstalled.'), 'ok');
  }
  else {
    drush_print(dt('The following modules will be uninstalled: !modules', array('!modules' => implode(', ', $modules))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_log(dt('Aborting.'));
    }
  }

  // Disable the modules.
  drush_module_uninstall($modules);

  // Inform the user of final status.
  foreach ($modules as $module) {
    drush_log(dt('!module was successfully uninstalled.', array('!module' => $module)), 'ok');
  }
}

/**
 * Array filter callback to return enabled modules.
 *
 * @param $module
 *  A module object as returned by drush_get_modules().
 */
function pm_is_enabled($module) {
  return $module->status;
}

/**
 * Callback helper.
 */
function pm_true() {
  return TRUE;
}

/**
 * We need to set the project path by looking at the module location. Ideally, update.module would do this for us.
 *
 * TODO: Improve logic so this works even if your project directory is not
 * named the same as the project name.
 */
function pm_get_project_path($projects, $lookup) {
  foreach ($projects as $name => $project) {
    if (!isset($project['path'])  && $name != 'drupal') {
      // looks for an enabled module.
      foreach ($project[$lookup] as $filename => $title) {
        if ($path = drupal_get_path($project['project_type'], $filename)) {
          continue;
        }
      }
      // As some modules are not located in their project's root directory
      // but in a subdirectory (e.g. all the ecommerce modules), we take the module's
      // info file's path, and then move up until we are at a directory with the
      // project's name.
      $parts = explode('/', $path);
      $i = count($parts) - 1;
      $stop = array_search($name, $parts);
      while ($i > $stop) {
        unset($parts[$i]);
        $i--;
      }
      $projects[$name]['path'] = implode('/', $parts);
    }
  }
  return $projects;
}

/**
 * A drush command callback. Show release info for given project(s).
 *
 **/
function drush_pm_releases() {
  // We don't provide for other options here, so we supply an explicit path.
  drush_include_engine('update_info', 'drupal', NULL, DRUSH_BASE_PATH . '/commands/pm/update_info');

  $projects = func_get_args();
  $projects = drupal_map_assoc($projects);
  $info = pm_get_project_info($projects);
  $project_info = drush_get_projects();

  $rows[] = array(dt('Project'), dt('Release'), dt('Date'), dt('Status'));
  foreach ($info as $key => $project) {
    $recommended = isset($project['recommended_major'])?$project['recommended_major']:NULL;
    $supported = isset($project['supported_majors'])?explode(',', $project['supported_majors']):array();
    $default = $project['default_major'];
    $recommended_version = NULL;
    $latest_version = NULL;
    foreach ($project['releases'] as $release) {
      if ($release['version_major'] == $recommended) {
        if (!isset($latest_version)) {
          $latest_version = $release['version'];
        }
        if (empty($release['version_extra'])) {
          if (!isset($recommended_version)) {
            $recommended_version = $release['version'];
          }
        }
      }
    }
    if (!isset($recommended_version)) {
      $recommended_version = $latest_version;
    }
    foreach ($project['releases'] as $release) {
      $status = array();
      $type = array();
      if (($k = array_search($release['version_major'], $supported)) !== FALSE) {
        $status[] = dt('Supported');
        unset($supported[$k]);
      }
      if ((isset($recommended_version)) && ($release['version'] == $recommended_version)) {
        $status[] = dt('Recommended');
      }
      if ($release['version_extra'] == 'dev') {
        $status[] = dt('Development');
      }
      if (isset($project_info[$key])) {
        if ($project_info[$key]->info['version'] == $release['version']) {
          $status[] = dt('Installed');
        }
      }
      if (isset($release['terms']) && array_key_exists('Release type', $release['terms'])) {
        foreach ($release['terms']['Release type'] as $one_type) {
          if ($one_type == 'Security update') {
            $status[] = dt('Security');
          }
        }
      }
      $rows[] = array(
        $key,
        $release['version'],
        format_date($release['date'], 'custom', 'Y-M-d'),
        implode(', ', $status)
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
  // Signal that we will update drush core after the drush modules
  // are updated, if an update to core is available.
  drush_set_context('DRUSH_PM_UPDATE_ALL', TRUE);

  // Call pm-updatecode.  updatedb will be called in the post-update process.
  $args = func_get_args();
  array_unshift($args, 'pm-updatecode');
  call_user_func_array('drush_invoke', $args);

  // pm-updatecode will not do a core update of Drupal
  // on the same invocation where non-core modules are
  // updated.  If there is a core update available, then
  // call pm-updatecode a second time to update core
  // (but only if the first run finished successfully).
  if (drush_get_context('DRUSH_PM_CORE_UPDATE_AVAILABLE', FALSE) && (drush_get_error() == DRUSH_SUCCESS)) {
    call_user_func_array('drush_invoke', array('pm-updatecode', 'drupal'));
  }
}

/**
 * Post-command callback.
 * Execute updatedb command after an updatecode - user requested `update`.
 */
function drush_pm_post_pm_update() {
  // Use drush_backend_invoke to start a subprocess. Cleaner that way.
  drush_backend_invoke('updatedb');
}

/**
 * Validate callback for updatecode command. Abort if 'backup' folder exists.
 */
function drush_pm_updatecode_validate() {
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  if (is_dir($drupal_root . DIRECTORY_SEPARATOR . 'backup')) {
    return drush_set_error('DRUSH_PM_BACKUP_DIR_FORBIDDEN', dt('It\'s a security risk to have a backup folder in your Drupal root. You must move it elsewhere before proceeding.'));
  }
}

/**
 * Post-command callback for updatecode. Notify about any pending DB updates.
 */
function drush_pm_post_pm_updatecode() {
  // Make sure the installation API is available
  require_once drush_get_context('DRUSH_DRUPAL_ROOT') . '/includes/install.inc';

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
 * Determine a candidate destination directory for a particular site path and
 * return it if it exists, optionally attempting to create the directory.
 */
function pm_dl_destination_lookup($type, $drupal_root, $sitepath, $create = FALSE) {
  switch ($type) {
    case 'module':
      // Prefer sites/all/modules/contrib if it exists.
      $destination = $sitepath . 'modules/';
      $contrib = $destination . 'contrib/';
      if (is_dir($contrib)) {
        $destination = $contrib;
      }
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
    if (!is_dir($destination)) {
      drush_print(dt("The directory !destination does not exist.", array('!destination' => $destination)));
      if (!drush_get_context('DRUSH_SIMULATE')) {
        if (drush_confirm(dt('Would you like to create it?'))) {
          @drush_op('mkdir', $destination, 0777, TRUE);
        }
      }
    }
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
  // ignore-bootstrap is a temporary hack. You can't currently download a 7.x
  // module after bootstrapping a 6.x site. This is needed by core-upgrade.
  // Related to http://drupal.org/node/463110.
  $drupal_bootstrap = drush_get_option('bootstrap_cancel') ? !drush_get_option('bootstrap_cancel') : drush_get_context('DRUSH_BOOTSTRAP_PHASE') > 0;
  foreach($requests as $request) {
    $drupal_version = $drupal_version_default;
    $project_version = NULL;
    $version = NULL;
    $project = $request;
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
        // For regular projects the version string includes the Drupal core version.
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
      ),
      'sub-options' => array(
        '--package-handler=cvs' => array(
          '--cvsparams' => 'Add options to the `cvs` program',
          '--cvsmethod' => 'Force cvs updates or checkouts (checkout is default unless the directory is managed by a supported version control system).',
          '--cvscredentials' => 'A username and password that is sent for cvs checkout command. Defaults to anonymous:anonymous',
        ),
      ),
      'examples' => array(
          'drush [command] cck --cvscredentials=\"name:password\"' =>  'Checkout should use these credentials.',
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
    'backup' => array(
      'options' => array(
        '--version-control=backup' => 'Default engine. Backup all project files before updates.',
      ),
      'sub-options' => array(
        '--version-control=backup' => array(
          '--backup-dir' => 'Specify a directory to backup packages into. Defaults to .drush-backups within the home directory of the user running the command. It is forbidden to specify a directory inside your drupal root.',
        ),
      ),
    ),
    'svn' => array(
      'signature' => 'svn info %s',
      'options' => array(
        '--version-control=svn' => 'Quickly add/remove/commit your project changes to Subversion.',
      ),
      'sub-options' => array(
        '--version-control=svn' => array(
          '--svnsync' => 'Automatically add new files to the SVN repository and remove deleted files. Caution.',
          '--svncommit' => 'Automatically commit changes to SVN repository. You must also using the --svnsync option.',
          '--svnmessage' => 'Override default commit message which is: Drush automatic commit: <the drush command line used>',
          '--svnstatusparams' => "Add options to the 'svn status' command",
          '--svnaddparams' => 'Add options to the `svn add` command',
          '--svnremoveparams' => 'Add options to the `svn remove` command',
          '--svnrevertparams' => 'Add options to the `svn revert` command',
          '--svncommitparams' => 'Add options to the `svn commit` command',
        ),
      ),
      'examples' => array(
        'drush [command] cck --svncommitparams=\"--username joe\"' =>  'Commit changes as the user \'joe\' (Quotes are required).'
      ),
    ),
    'bzr' => array(
      'signature' => 'bzr root %s',
      'options' => array(
        '--version-control=bzr' => 'Quickly add/remove/commit your project changes to Bazaar.',
      ),
      'sub-options' => array(
        '--version-control=bzr' => array(
          '--bzrsync' => 'Automatically add new files to the Bazaar repository and remove deleted files. Caution.',
          '--bzrcommit' => 'Automatically commit changes to Bazaar repository. You must also using the --bzrsync option.',
          '  --bzrmessage' => 'Override default commit message which is: Drush automatic commit. Project <name> <type> Command: <the drush command line used>',
        ),
      ),
      'examples' => array(
        'drush dl cck --version-control=bzr --bzrsync --bzrcommit' =>  'Download the cck module and then add it and commit it to Bazaar.'
      ),
    ),
  );
}

/**
 * Interface for version control systems.
 * We use a simple object layer because we conceivably need more than one
 * loaded at a time.
 */
interface drush_pm_version_control {
  function pre_update(&$project);
  function rollback($project);
  function post_update($project);
  function post_download($project);
}

/**
 * A simple factory function that tests for version control systems, in a user
 * specified order, and return the one that appears to be appropriate for a
 * specific directory.
 */
function drush_pm_include_version_control($directory = '.') {
  $version_controls = explode(',', drush_get_option('version-control', 'svn,backup,bzr'));
  $version_control_engines = drush_get_engines('version_control');

  // Find the first valid engine in the list, checking signatures if needed.
  $engine = FALSE;
  while (!$engine && count($version_controls)) {
    $version_control = array_shift($version_controls);
    if (isset($version_control_engines[$version_control])) {
      if (!empty($version_control_engines[$version_control]['signature'])) {
        if (drush_shell_exec($version_control_engines[$version_control]['signature'], $directory)) {
          $engine = $version_control;
        }
      }
      else {
        $engine = $version_control;
      }
    }
  }
  if (!$engine) {
    return drush_set_error('DRUSH_PM_NO_VERSION_CONTROL', dt('No valid version control or backup engine found (the --version-control option was set to "!version-control").', array('!version-control' => $version_control)));
  }
  if (!drush_include_engine('version_control', $engine)) {
    return FALSE;
  }
  $engine = 'drush_pm_version_control_' . $engine;
  return new $engine();
}

// Implement drush_COMMAND_init().
function drush_pm_download_init() {
  // Bootstrap to the highest level possible.
  drush_bootstrap_max();
}

/**
 * Command callback. Download Drupal core or any project.
 */
function drush_pm_download() {
  drush_include_engine('package_handler', drush_get_option('package-handler', 'wget'));

  if (!$requests = func_get_args()) {
    $requests = array('drupal');
  }

  // Parse out project name and version
  $requests = pm_parse_project_version($requests);

  $project_types = pm_project_types();
  $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
  foreach ($requests as $name => $request) {
    // Don't rely on UPDATE_DEFAULT_URL since we are not fully bootstrapped.
    $url = drush_get_option('source', 'http://updates.drupal.org/release-history') . '/' . $request['name'] . '/' . $request['drupal_version'];
    drush_log('Downloading release history from ' . $url);
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
        if (!empty($request['version'])) {
          $releases = $xml->xpath("/project/releases/release[status='published'][version='" . $request['version'] . "']");
          if (empty($releases)) {
            drush_log(dt("Could not locate specified project version, downloading latest stable version"), 'warning');
          }
        }
        // If that did not work, get the first published release for the recommended major version.
        if (empty($releases)) {
          if ($recommended_major = $xml->xpath("/project/recommended_major")) {
            $xpath_releases = "/project/releases/release[status='published'][version_major=" . (string)$recommended_major[0] . "]";
            $releases = @$xml->xpath($xpath_releases);
          }
        }

        // If there are recommended releases (no 'version_extra' elements),
        // then use only recommended releases.  Otherwise, use all; in
        // this case, the recommended release defaults to the latest published
        // release with the right recommended major version number.
        $recommended_releases = array();
        if (!empty($releases)) {
          foreach ($releases as $one_release) {
            if (!array_key_exists('version_extra', $one_release)) {
              $recommended_releases[] = $one_release;
            }
          }
        }

        if (!empty($recommended_releases)) {
          $releases = $recommended_releases;
        }

        if (empty($releases)) {
          drush_log(dt('There is no *recommended* release for project !project on Drupal !drupal_version. Ask the maintainer to review http://drupal.org/node/197584 and create/recommend a release in order to be compatible with drush and the drupal.org security broadcast system. A recommended development snapshot release is sufficient. Alternatively, run pm-releases command and explicity pm-download any non-recommended release that might be available.', array('!drupal_version' => $request['drupal_version'], '!project' => $request['name'])), 'ok');
          continue;
        }

        // Profiles have 3 variants for a given real relase. Admin can specify using option.
        // Depends on a fixed order of variations in releases list.
        // Usually,  the first release is chosen.
        $variations = array('core', 'no-core', 'make');
        $variant = drush_get_option('variant', 'core');
        $release = (array)$releases[array_search($variant, $variations)];

        // Determine what type of project we have, so we know where to put it.
        $release['type'] = 'module';

        if ($types = $xml->xpath('/project/terms/term[name="Projects" and ' . $project_types_xpath . ']')) {
          $release['type'] = array_search($types[0]->value, $project_types);
        }

        // Create basic project array.
        $project = $request;
        if ($project['base_project_path'] = pm_dl_destination($release['type'])) {
          $project['full_project_path'] = $project['base_project_path'] .  $request['name'];
          $project['project_type'] = $release['type'];
          if (!$version_control = drush_pm_include_version_control($project['base_project_path'])) {
            return FALSE;
          }
          if (package_handler_install_project($project, $release)) {
            // If the --destination option was not specified, then
            // allow commandfiles that implement the adjust-download-destination
            // hook to pick a new default location for the project.
            if (drush_get_option('destination', FALSE) === FALSE) {
              drush_pm_relocate_project($project, $release);
            }
            drush_log(dt("Project !project (!version) downloaded to !dest.", array('!project' => $request['name'], '!version' => $release['version'], '!dest' => $project['full_project_path'])), 'success');
            drush_command_invoke_all('drush_pm_post_download', $project, $release);
            $version_control->post_download($project);
            // Find all of the .info files in the destination directory
            $modules_in_project = array();
            foreach(drush_scan_directory($project['full_project_path'], "/.*\.info$/", array('.', '..', 'CVS'), 0, TRUE, 'filename') as $filename => $info) {
              $modules_in_project[] = basename($filename, ".info");
            }
            if (count($modules_in_project) > 0) {
              drush_print_pipe($modules_in_project);

              // If there is only one module / theme in the project, only print out
              // the message if the module / theme name is different than the project name.
              if ((count($modules_in_project) == 1) && ($modules_in_project[0] != $request['name'])) {
                drush_log(dt("Project !project contains a !type named !module.", array('!project' => $request['name'], '!type' => $project['project_type'], '!module' => $modules_in_project[0])), 'success');
              }
              // If there are mulitple modules or themes in the project, list them all.
              else {
                drush_log(dt("Project !project contains !count !type: !modules.", array('!project' => $request['name'], '!count' => count($modules_in_project), '!type' => $project['project_type'] . 's',  '!modules' => implode(', ', $modules_in_project))), 'success');
              }
            }
          }
        }
      }
    }
    else {
      // We are not getting here since drupal.org always serves an XML response.
      drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Could not download project status information from !url', array('!url' => $url)));
    }

    unset($error, $release, $releases, $types);
  }
}

/**
 * drush_pm_relocate_project moves projects that should be relocated to a different
 * installation directory to the location they belong in.  For example,
 * modules that are only collections of drush commands will be installed
 * to $HOME/.drush.
 *
 * This function is called after the project is downloaded so that its
 * contents can be examined to determine its optimal installation location.
 * Every drush commandfile is given a chance to examine the project contents
 * and decide where the project should be located.
 */
function drush_pm_relocate_project(&$project, $release) {
  // Call the get-install-location hook to see if any drush commandfiles
  // would like to adjust the install location for this project.
  // drush_command_invoke_all() allows the first parameter to be passed by reference.
  drush_command_invoke_all_ref('drush_pm_adjust_download_destination', $project, $release);

  if (isset($project['project_install_location']) && ($project['full_project_path'] != $project['project_install_location'])) {
    if (drush_move_dir($project['full_project_path'], $project['project_install_location'], TRUE)) {
      $project['full_project_path'] = $project['project_install_location'];
    }
    else {
      drush_log(dt("Project !project (!version) could not be relocated to !dest.", array('!project' => $project['name'], '!version' => $release['version'], '!dest' => $project['project_install_location'])), 'warning');
    }
  }
  return TRUE;
}

/**
 * Built-in adjust-download-destination hook.  This particular version of
 * the hook will move modules that contain only drush commands to
 * /usr/share/drush/commands if it exists, or $HOME/.drush if the
 * site-wide location does not exist.
 */
function pm_drush_pm_adjust_download_destination(&$project, $release) {
  // If this project is a module, but it has no .module file, then
  // check to see if it contains drush commands.  If that is all
  // that it contains, then install it to $HOME/.drush.
  if ($release['type'] == 'module') {
    $module_files = drush_scan_directory($project['full_project_path'], '/.*\.module/');
    if (empty($module_files)) {
      $drush_command_files = drush_scan_directory($project['full_project_path'], '/.*\.drush.inc/');
      if (!empty($drush_command_files)) {
        $install_dir = drush_get_context('SHARE_PREFIX', '/usr') . '/share/drush/commands/';
        if (!is_dir($install_dir)) {
          $install_dir = drush_server_home() . '/.drush/';
        }
        // Make the .drush dir if it does not already exist
        if (!is_dir($install_dir)) {
          mkdir($install_dir);
        }
        // Change the location if the mkdir worked
        if (is_dir($install_dir)) {
          $project['project_install_location'] = $install_dir . basename($project['full_project_path']);
        }
      }
    }
  }
}

/**
 * Update the locked status of all of the candidate projects
 * to be updated.
 *
 * @param array &$projects
 *   The projects array from pm_updatecode.  $project['locked'] will
 *   be set for every file where a persistent lockfile can be found.
 *   The 'lock' and 'unlock' operations are processed first.
 * @param array $projects_to_lock
 *   A list of projects to create peristent lock files for
 * @param array $projects_to_unlock
 *   A list of projects to clear the persistent lock on
 * @param string $lock_message
 *   The reason the project is being locked; stored in the lockfile.
 *
 * @return array
 *   A list of projects that are locked.
 */
function drush_pm_update_lock(&$projects, $projects_to_lock, $projects_to_unlock, $lock_message = NULL) {
  $locked_result = array();
  
  // Warn about ambiguous lock / unlock values
  if ($projects_to_lock == array('1')) {
    $projects_to_lock = array();
    drush_log(dt('Ignoring --lock with no value.'), 'warning');
  }
  if ($projects_to_unlock == array('1')) {
    $projects_to_unlock = array();
    drush_log(dt('Ignoring --unlock with no value.'), 'warning');
  }
  
  // Log if we are going to lock or unlock anything
  if (!empty($projects_to_unlock)) {
    drush_log(dt('Unlocking !projects', array('!projects' => implode(',', $projects_to_unlock))), 'ok');
  }
  if (!empty($projects_to_lock)) {
    drush_log(dt('Locking !projects', array('!projects' => implode(',', $projects_to_lock))), 'ok');
  }
  
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  foreach ($projects as $name => $project) {
    $message = NULL;
    $lockfile = $drupal_root . '/' . $project['path'] . '/.drush-lock-update';
    
    // Remove the lock file if the --unlock option was specified
    if (((in_array($name, $projects_to_unlock)) || (in_array('all', $projects_to_unlock))) && (file_exists($lockfile))) {
      drush_op('unlink', $lockfile);
    }
    
    // Create the lock file if the --lock option was specified
    if ((in_array($name, $projects_to_lock)) || (in_array('all', $projects_to_lock))) {
      drush_op('file_put_contents', $lockfile, $lock_message != NULL ? $lock_message : "Locked via drush.");
      // Note that the project is locked.  This will work even if we are simulated,
      // or if we get permission denied from the file_put_contents.
      // If the lock is -not- simulated or transient, then the lock message will be
      // read from the lock file below.
      $message = drush_get_context('DRUSH_SIMULATE') ? 'Simulated lock.' : 'Transient lock.';
    }
    
    // If the persistent lock file exists, then mark the project as locked.
    if (file_exists($lockfile)) {
      $message = trim(file_get_contents($lockfile));
    }
    
    // If there is a message set, then mark the project as locked.
    if (isset($message)) {
      $projects[$name]['locked'] = !empty($message) ? $message : "Locked.";
      $locked_result[$name] = $project;
    }
  }
  
  return $locked_result;
}

