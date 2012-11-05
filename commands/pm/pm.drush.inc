<?php

/**
 * @file
 *  The drush Project Manager
 *
 * Terminology:
 * - Request: a requested project (string or keyed array), with a name and (optionally) version.
 * - Project: a drupal.org project (i.e drupal.org/project/*), such as cck or zen.
 * - Extension: a drupal.org module, theme or profile.
 * - Version: a requested version, such as 1.0 or 1.x-dev.
 * - Release: a specific release of a project, with associated metadata (from the drupal.org update service).
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
 * User requested project was not packaged by drupal.org.
 */
define('DRUSH_PM_REQUESTED_PROJECT_NOT_PACKAGED', 103);

/**
 * User requested version not found.
 */
define('DRUSH_PM_REQUESTED_VERSION_NOT_FOUND', 104);

/**
 * User requested project not found.
 */
define('DRUSH_PM_REQUESTED_PROJECT_NOT_FOUND', 105);

/**
 * User requested project not updateable.
 */
define('DRUSH_PM_REQUESTED_PROJECT_NOT_UPDATEABLE', 106);


/**
 * Implementation of hook_drush_help().
 */
function pm_drush_help($section) {
  switch ($section) {
    case 'meta:pm:title':
      return dt('Project manager commands');
    case 'meta:pm:summary':
      return dt('Download, enable, examine and update your modules and themes.');
    case 'drush:pm-enable':
      return dt('Enable one or more extensions (modules or themes). Enable dependant extensions as well.');
    case 'drush:pm-disable':
      return dt('Disable one or more extensions (modules or themes). Disable dependant extensions as well.');
    case 'drush:pm-updatecode':
    case 'drush:pm-update':
      $message = dt("Display available update information for Drupal core and all enabled projects and allow updating to latest recommended releases.");
      if ($section == 'drush:pm-update') {
        $message .= ' '.dt("Also apply any database updates required (same as pm-updatecode + updatedb).");
      }
      $message .= ' '.dt("Note: The user is asked to confirm before the actual update. Backups are performed unless directory is already under version control. Updated projects can potentially break your site. It is NOT recommended to update production sites without prior testing.");
      return $message;
    case 'drush:pm-updatecode-postupdate':
      return dt("This is a helper command needed by updatecode. It is used to check for db updates in a backend process after code updated have been performed. We need to run this task in a separate process to not conflict with old code already in memory.");
      case 'drush:pm-releases':
      return dt("View all releases for a given drupal.org project. Useful for deciding which version to install/update.");
    case 'drush:pm-download':
      return dt("Download Drupal core or projects from drupal.org (Drupal core, modules, themes or profiles) and other sources. It will automatically figure out which project version you want based on its recommended release, or you may specify a particular version.

If no --destination is provided, then destination depends on the project type:
  - Profiles will be downloaded to profiles/ in your Drupal root.
  - Modules and themes will be downloaded to the site specific directory (sites/example.com/modules|themes) if available, or to sites/all/modules|themes.
  - If you're downloading drupal core or you are not running the command within a bootstrapped drupal site, the default location is the current directory.
  - Drush commands will be relocated to @site_wide_location (if available) or ~/.drush. Relocation is determined once the project is downloaded by examining its content. Note you can provide your own function in a commandfile to determine the relocation of any project.", array('@site_wide_location' => drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES')));
  }
}

/**
 * Implementation of hook_drush_command().
 */
function pm_drush_command() {
  $update = 'update';
  $update_options = array(
    'security-only' => 'Only update modules that have security updates available. However, if there were other releases of a module between the installed version the security update, other changes to features or functionality may occur.',
    'lock' => 'Add a persistent lock to remove the specified projects from consideration during updates.  Locks may be removed with the --unlock parameter, or overridden by specifically naming the project as a parameter to pm-update or pm-updatecode.  The lock does not affect pm-download.  See also the update-advanced project for similar and improved functionality.',
  );
  $update_suboptions = array(
    'lock' => array(
      'lock-message' => array(
        'description' => 'A brief message explaining why a project is being locked; displayed during pm-updatecode.  Optional.',
        'example-value' => 'message',
      ),
      'unlock' => 'Remove the persistent lock from the specified projects so that they may be updated again.',
    ),
  );

  $items['pm-enable'] = array(
    'description' => 'Enable one or more extensions (modules or themes).',
    'arguments' => array(
      'extensions' => 'A list of modules or themes. You can use the * wildcard at the end of extension names to enable all matches.',
    ),
    'options' => array(
      'resolve-dependencies' => 'Attempt to download any missing dependencies. At the moment, only works when the module name is the same as the project name.',
      'skip' => 'Skip automatic downloading of libraries (c.f. devel).',
    ),
    'aliases' => array('en'),
  );
  $items['pm-disable'] = array(
    'description' => 'Disable one or more extensions (modules or themes).',
    'arguments' => array(
      'extensions' => 'A list of modules or themes. You can use the * wildcard at the end of extension names to disable multiple matches.',
    ),
    'aliases' => array('dis'),
  );
  $items['pm-info'] = array(
    'description' => 'Show detailed info for one or more extensions (modules or themes).',
    'arguments' => array(
      'extensions' => 'A list of modules or themes. You can use the * wildcard at the end of extension names to show info for multiple matches. If no argument is provided it will show info for all available extensions.',
    ),
    'aliases' => array('pmi'),
  );
  // Install command is reserved for the download and enable of projects including dependencies.
  // @see http://drupal.org/node/112692 for more information.
  // $items['install'] = array(
  //     'description' => 'Download and enable one or more modules',
  //   );
  $items['pm-uninstall'] = array(
    'description' => 'Uninstall one or more modules.',
    'arguments' => array(
      'modules' => 'A list of modules.',
    ),
  );
  $items['pm-list'] = array(
    'description' => 'Show a list of available extensions (modules and themes).',
    'callback arguments' => array(array(), FALSE),
    'options' => array(
      'type' => array(
        'description' => 'Filter by extension type. Choices: module, theme.',
        'example-value' => 'module',
      ),
      'status' => array(
        'description' => 'Filter by extension status. Choices: enabled, disabled and/or \'not installed\'. You can use multiple comma separated values. (i.e. --status="disabled,not installed").',
        'example-value' => 'disabled',
      ),
      'package' => 'Filter by project packages. You can use multiple comma separated values. (i.e. --package="Core - required,Other").',
      'core' => 'Filter out extensions that are not in drupal core.',
      'no-core' => 'Filter out extensions that are provided by drupal core.',
      'pipe' => 'Returns a whitespace delimited list of the names of the resulting extensions.',
    ),
    'aliases' => array('pml'),
  );
  $items['pm-refresh'] = array(
    'description' => 'Refresh update status information.',
    'drupal dependencies' => array($update),
    'aliases' => array('rf'),
  );
  $items['pm-updatecode'] = array(
    'description' => 'Update Drupal core and contrib projects to latest recommended releases.',
    'drupal dependencies' => array($update),
    'arguments' => array(
      'projects' => 'Optional. A list of installed projects to update.',
    ),
    'options' => array(
      'pipe' => 'Returns a whitespace delimited list of projects with any of its extensions enabled and their respective version and update information, one project per line. Order: project name, current version, recommended version, update status.',
      'notes' => 'Show release notes for each project to be updated.',
      'no-core' => 'Only update modules and skip the core update.',
    ) + $update_options,
    'sub-options' => $update_suboptions,
    'aliases' => array('upc'),
    'topics' => array('docs-policy'),
    'engines' => array(
      'version_control',
      'package_handler',
      'release_info' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
  );
  // Merge all items from above.
  $items['pm-update'] = array(
    'description' => 'Update Drupal core and contrib projects and apply any pending database updates (Same as pm-updatecode + updatedb).',
    'drupal dependencies' => array($update),
    'aliases' => array('up'),
    'allow-additional-options' => array('pm-updatecode', 'updatedb'),
  );
  $items['pm-updatecode-postupdate'] = array(
    'description' => 'Notify of pending db updates.',
    'hidden' => TRUE,
  );
  $items['pm-releasenotes'] = array(
    'description' => 'Print release notes for given projects.',
    'arguments' => array(
      'projects' => 'A list of project names, with optional version. Defaults to \'drupal\'',
    ),
    'options' => array(
      'html' => dt('Display releasenotes in HTML rather than plain text.'),
    ),
    'examples' => array(
      'drush rln cck' => 'Prints the release notes for the recommended version of CCK project.',
      'drush rln token-1.13' => 'View release notes of a specfic version of the Token project for my version of Drupal.',
      'drush rln pathauto zen' => 'View release notes for the recommended version of Pathauto and Zen projects.',
    ),
    'aliases' => array('rln'),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'engines' => array(
      'release_info',
    ),
  );
  $items['pm-releases'] = array(
    'description' => 'Print release information for given projects.',
    'arguments' => array(
      'projects' => 'A list of drupal.org project names. Defaults to \'drupal\'',
    ),
    'examples' => array(
      'drush pm-releases cck zen' => 'View releases for cck and Zen projects for your Drupal version.',
    ),
    'aliases' => array('rl'),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'engines' => array(
      'release_info',
    ),
  );
  $items['pm-download'] = array(
    'description' => 'Download projects from drupal.org or other sources.',
    'examples' => array(
      'drush dl drupal' => 'Download latest recommended release of Drupal core.',
      'drush dl drupal-7.x' => 'Download latest 7.x development version of Drupal core.',
      'drush dl drupal-6' => 'Download latest recommended release of Drupal 6.x.',
      'drush dl cck zen' => 'Download latest versions of CCK and Zen projects.',
      'drush dl og-1.3' => 'Download a specfic version of Organic groups module for my version of Drupal.',
      'drush dl diff-6.x-2.x' => 'Download a specific development branch of diff module for a specific Drupal version.',
      'drush dl views --select' => 'Show a list of recent releases of the views project, prompt for which one to download.',
      'drush dl webform --dev' => 'Download the latest dev release of webform.',
      'drush dl webform --cache' => 'Download webform. Fetch and populate the download cache as needed.',
    ),
    'arguments' => array(
      'projects' => 'A comma delimited list of drupal.org project names, with optional version. Defaults to \'drupal\'',
    ),
    'options' => array(
      'destination' => array(
        'description' => 'Path to which the project will be copied. If you\'re providing a relative path, note it is relative to the drupal root (if bootstrapped).',
        'example-value' => 'path',
      ),
      'use-site-dir' => 'Force to use the site specific directory. It will create the directory if it doesn\'t exist. If --destination is also present this option will be ignored.',
      'notes' => 'Show release notes after each project is downloaded.',
      'variant' => array(
        'description' => "Only useful for install profiles. Possible values: 'full', 'projects', 'profile-only'.",
        'example-value' => 'full',
      ),
      'select' => "Select the version to download interactively from a list of available releases.",
      'drupal-project-rename' => 'Alternate name for "drupal-x.y" directory when downloading Drupal project. Defaults to "drupal".',
      'default-major' => array(
        'description' => 'Specify the default major version of modules to download when there is no bootstrapped Drupal site.  Defaults to "7".',
        'example-value' => '6',
      ),
      'skip' => 'Skip automatic downloading of libraries (c.f. devel).',
      'pipe' => 'Returns a list of the names of the extensions (modules and themes) contained in the downloaded projects.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('dl'),
    'engines' => array(
      'version_control',
      'package_handler',
      'release_info',
    ),
  );
  return $items;
}

/**
 * @defgroup extensions Extensions management.
 * @{
 * Functions to manage extensions.
 */

/**
 * Command argument complete callback.
 */
function pm_pm_enable_complete() {
  return pm_complete_extensions();
}

/**
 * Command argument complete callback.
 */
function pm_pm_disable_complete() {
  return pm_complete_extensions();
}

/**
 * Command argument complete callback.
 */
function pm_pm_uninstall_complete() {
  return pm_complete_extensions();
}

/**
 * Command argument complete callback.
 */
function pm_pm_info_complete() {
  return pm_complete_extensions();
}

/**
 * Command argument complete callback.
 */
function pm_pm_releasenotes_complete() {
  return pm_complete_projects();
}

/**
 * Command argument complete callback.
 */
function pm_pm_releases_complete() {
  return pm_complete_projects();
}

/**
 * Command argument complete callback.
 */
function pm_pm_updatecode_complete() {
  return pm_complete_projects();
}

/**
 * Command argument complete callback.
 */
function pm_pm_update_complete() {
  return pm_complete_projects();
}

/**
 * List extensions for completion.
 *
 * @return
 *  Array of available extensions.
 */
function pm_complete_extensions() {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    $extension_info = drush_get_extensions(FALSE);
    return array('values' => array_keys($extension_info));
  }
}

/**
 * List projects for completion.
 *
 * @return
 *  Array of installed projects.
 */
function pm_complete_projects() {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    return array('values' => array_keys(drush_get_projects()));
  }
}

/**
 * Sort callback function for sorting extensions.
 *
 * It will sort first by type, second by package and third by name.
 */
function _drush_pm_sort_extensions($a, $b) {
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
 * Calculate a extension status based on current status and schema version.
 *
 * @param $extension
 *   Object of a single extension info.
 *
 * @return
 *   String describing extension status. Values: enabled|disabled|not installed
 */
function drush_get_extension_status($extension) {
  if (($extension->type == 'module')&&($extension->schema_version == -1)) {
    $status = "not installed";
  }
  else {
    $status = ($extension->status == 1)?'enabled':'disabled';
  }

  return $status;
}

/**
 * Classify extensions as modules, themes or unknown.
 *
 * @param $extensions
 *   Array of extension names, by reference.
 * @param $modules
 *   Empty array to be filled with modules in the provided extension list.
 * @param $themes
 *   Empty array to be filled with themes in the provided extension list.
 */
function drush_pm_classify_extensions(&$extensions, &$modules, &$themes, $extension_info) {
  _drush_pm_expand_extensions($extensions, $extension_info);
  foreach ($extensions as $extension) {
    if (!isset($extension_info[$extension])) {
      continue;
    }
    if ($extension_info[$extension]->type == 'module') {
      $modules[$extension] = $extension;
    }
    else if ($extension_info[$extension]->type == 'theme') {
      $themes[$extension] = $extension;
    }
  }
}

/**
 * Obtain an array of installed projects off the extensions available.
 *
 * A project is considered to be 'enabled' when any of its extensions is
 * enabled.
 * If any extension lacks project information and it is found that the
 * extension was obtained from drupal.org's cvs or git repositories, a new
 * 'vcs' attribute will be set on the extension. Example:
 *   $extensions[name]->vcs = 'cvs';
 *
 * @param array $extensions
 *   Array of extensions as returned by drush_get_extensions().
 *
 * @return
 *   Array of installed projects with info of version, status and provided
 * extensions.
 */
function drush_get_projects(&$extensions = NULL) {
  if (is_null($extensions)) {
    $extensions = drush_get_extensions();
  }
  $projects = array(
    'drupal' => array(
      'label' => 'Drupal',
      'version' => VERSION,
      'type' => 'core',
      'extensions' => array(),
    )
  );
  foreach ($extensions as $extension) {
    // The project name is not available in this cases:
    // 1. the extension is part of drupal core.
    // 2. the project was checked out from CVS/git and cvs_deploy/git_deploy
    // is not installed.
    // 3. it is not a project hosted in drupal.org.
    if (empty($extension->info['project'])) {
      if (isset($extension->info['version']) && ($extension->info['version'] == VERSION)) {
        $project = 'drupal';
      }
      else {
        if (is_dir(dirname($extension->filename) . '/CVS') && (!module_exists('cvs_deploy'))) {
          $extension->vcs = 'cvs';
          drush_log(dt('Extension !extension is fetched from cvs. Ignoring.', array('!extension' => $extension->name)), 'debug');
        }
        elseif (is_dir(dirname($extension->filename) . '/.git') && (!module_exists('git_deploy'))) {
          $extension->vcs = 'git';
          drush_log(dt('Extension !extension is fetched from git. Ignoring.', array('!extension' => $extension->name)), 'debug');
        }
        continue;
      }
    }
    else {
        $project = $extension->info['project'];
    }
    // Create/update the project in $projects with the project data.
    if (!isset($projects[$project])) {
      $projects[$project] = array(
        'label' => isset($extensions[$project])?$extensions[$project]->label:$project,
        'type' => $extension->type,
        'version' => $extension->info['version'],
        'status' => $extension->status,
        'extensions' => array(),
      );
      if (isset($extension->info['project status url'])) {
        $projects[$project]['status url'] = $extension->info['project status url'];
      }
    }
    elseif ($extension->status != 0) {
      $projects[$project]['status'] = $extension->status;
    }
    $projects[$project]['extensions'][] = $extension->name;
  }

  // Once we have all the extensions for each project, obtain projects' path.
  $reserved = array('modules', 'sites', 'themes');
  foreach ($projects as $name => $project) {
    if ($name == 'drupal') {
      continue;
    }
    drush_log(dt('Obtaining !project project path.', array('!project' => $name)), 'debug');
    $path = _drush_pm_find_common_path($project['type'], $project['extensions']);
    // Prevent from setting a reserved path. For example it may happen in a case
    // where a module and a theme are declared as part of a same project.
    // There's a special case, a project called "sites", this is the reason for
    // the second condition here.
    if ((in_array(basename($path), $reserved)) && (!in_array($name, $reserved))) {
      drush_log(dt('Error while trying to find the common path for enabled extensions of project !project. Extensions are: !extensions.', array('!project' => $name, '!extensions' => implode(', ', $project['extensions']))), 'error');
    }
    else {
      $projects[$name]['path'] = $path;
    }
  }

  return $projects;
}

/**
 * Helper function to find the common path for a list of extensions in the aim to obtain the project name.
 *
 * @param $project_type
 *  Type of project we're trying to find. Valid values: module, theme.
 * @param $extensions
 *  Array of extension names.
 */
function _drush_pm_find_common_path($project_type, $extensions) {
  // Select the first path as the candidate to be the common prefix.
  $extension = array_pop($extensions);
  while (!($path = drupal_get_path($project_type, $extension))) {
    drush_log(dt('Unknown path for !extension !type.', array('!extension' => $extension, '!type' => $project_type)), 'warning');
    $extension = array_pop($extensions);
  }

  // If there's only one extension we are done. Otherwise, we need to find
  // the common prefix for all of them.
  if (count($extensions) > 0) {
    // Iterate over the other projects.
    while($extension = array_pop($extensions)) {
      $path2 = drupal_get_path($project_type, $extension);
      if (!$path2) {
        drush_log(dt('Unknown path for !extension !type.', array('!extension' => $extension, '!type' => $project_type)), 'debug');
        continue;
      }
      // Option 1: same path.
      if ($path == $path2) {
        continue;
      }
      // Option 2: $path is a prefix of $path2.
      if (strpos($path2, $path) === 0) {
        continue;
      }
      // Option 3: $path2 is a prefix of $path.
      if (strpos($path, $path2) === 0) {
        $path = $path2;
        continue;
      }
      // Option 4: no one is a prefix of the other. Find the common
      // prefix by iteratively strip the rigthtmost piece of $path.
      // We will iterate until a prefix is found or path = '.', that on the
      // other hand is a condition theorically impossible to reach.
      do {
        $path = dirname($path);
        if (strpos($path2, $path) === 0) {
          break;
        }
      } while ($path != '.');
    }
  }

  return $path;
}

/**
 * Returns a list of enabled modules.
 *
 * This is a simplified version of module_list().
 */
function pm_module_list() {
  $enabled = array();
  $rsc = drush_db_select('system', 'name', 'type=:type AND status=:status', array(':type' => 'module', ':status' => 1));
  while ($row = drush_db_result($rsc)) {
    $enabled[$row] = $row;
  }

  return $enabled;
}

/**
 * @} End of "defgroup extensions".
 */

/**
 * Command callback. Show a list of extensions with type and status.
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

  $extension_info = drush_get_extensions(FALSE);
  uasort($extension_info, '_drush_pm_sort_extensions');

  $major_version = drush_drupal_major_version();
  foreach ($extension_info as $key => $extension) {
    if (!in_array($extension->type, $type_filter)) {
      unset($extension_info[$key]);
      continue;
    }
    $status = drush_get_extension_status($extension);
    if (!in_array($status, $status_filter)) {
      unset($extension_info[$key]);
      continue;
    }

    // filter out core if --no-core specified
    if (drush_get_option('no-core', FALSE)) {
      if ($extension->info['package'] == 'Core') {
        unset($extension_info[$key]);
        continue;
      }
    }

    // filter out non-core if --core specified
    if (drush_get_option('core', FALSE)) {
      if ($extension->info['package'] != 'Core') {
        unset($extension_info[$key]);
        continue;
      }
    }

    // filter by package
    if (!empty($package_filter)) {
      if (!in_array(strtolower($extension->info['package']), $package_filter)) {
        unset($extension_info[$key]);
        continue;
      }
    }

    if (empty($package_filter) || count($package_filter) > 1) {
      $row[] = $extension->info['package'];
    }

    $row[] = $extension->label;

    if (count($type_filter) > 1) {
      $row[] = ucfirst($extension->type);
    }
    if (count($status_filter) > 1) {
      $row[] = ucfirst($status);
    }
    if (($major_version >= 6)||($extension->type == 'module')) {
      // Suppress notice when version is not present.
      $row[] = @$extension->info['version'];
    }

    $rows[] = $row;
    $pipe[] = $extension->name;
    unset($row);
  }
  drush_print_table($rows, TRUE);

  if (isset($pipe)) {
    // Newline-delimited list for use by other scripts. Set the --pipe option.
    drush_print_pipe($pipe);
  }
  // Return the result for backend invoke
  return $extension_info;
}

/**
 * Helper function for pm-enable.
 */
function drush_pm_find_project_from_extension($extension) {
  $result =  drush_pm_lookup_extension_in_cache($extension);

  if (!isset($result)) {
    drush_include_engine('release_info', 'updatexml');
    // If we can find info on a project that has the same name
    // as the requested extension, then we'll call that a match.
    $request = pm_parse_project_version(array($extension));
    if (release_info_check_project($request[$extension], 'module')) {
      $result = $extension;
    }
  }

  return $result;
}

/**
 * Validate callback. Determine the modules and themes that the user would like enabled.
 */
function drush_pm_enable_validate() {
  $args = pm_parse_arguments(func_get_args());

  $extension_info = drush_get_extensions();

  $recheck = TRUE;
  while ($recheck) {
    $recheck = FALSE;

    // Classify $args in themes, modules or unknown.
    $modules = array();
    $themes = array();
    drush_pm_classify_extensions($args, $modules, $themes, $extension_info);
    $extensions = array_merge($modules, $themes);
    $unknown = array_diff($args, $extensions);

    // Discard and set an error for each unknown extension.
    foreach ($unknown as $name) {
      drush_log(dt('!extension was not found and will not be enabled.', array('!extension' => $name)), 'warning');
    }

    // Discard already enabled and incompatible extensions.
    foreach ($extensions as $name) {
      if ($extension_info[$name]->status) {
        drush_log(dt('!extension is already enabled.', array('!extension' => $name)), 'ok');
      }
      // Check if the extension is compatible with Drupal core and php version.
      if (drush_extension_check_incompatibility($extension_info[$name])) {
        drush_set_error('DRUSH_PM_ENABLE_MODULE_INCOMPATIBLE', dt('!name is incompatible with the Drupal version.', array('!name' => $name)));
        if ($extension_info[$name]->type == 'module') {
          unset($modules[$name]);
        }
        else {
          unset($themes[$name]);
        }
      }
    }

    if (!empty($modules)) {
      // Check module dependencies.
      $dependencies = drush_check_module_dependencies($modules, $extension_info);
      $unmet_dependencies = array();
      foreach ($dependencies as $module => $info) {
        if (!empty($info['unmet-dependencies'])) {
          foreach ($info['unmet-dependencies'] as $unmet_module) {
            $unmet_project = drush_pm_find_project_from_extension($unmet_module);
            if (!empty($unmet_project)) {
              $unmet_dependencies[$module][$unmet_project] = $unmet_project;
            }
          }
        }
      }
      if (!empty($unmet_dependencies)) {
        $msgs = array();
        $unmet_project_list = array();
        foreach ($unmet_dependencies as $module => $unmet_projects) {
          $unmet_project_list = array_merge($unmet_project_list, $unmet_projects);
          $msgs[] = dt("!module requires !unmet-projects", array('!unmet-projects' => implode(', ', $unmet_projects), '!module' => $module));
        }
        if (drush_get_option('resolve-dependencies') || drush_confirm(dt("The following projects have unmet dependencies:\n!list\nWould you like to download them?", array('!list' => implode("\n", $msgs))))) {
          // If we did not already print a log message via drush_confirm, then print one now.
          if (drush_get_option('resolve-dependencies')) {
            drush_log(dt("The following projects have unmet dependencies:\n@list\nThey are being downloaded.", array('@list' => implode("\n", $msgs))));
          }
          // Disable DRUSH_AFFIRMATIVE context temporarily.
          $drush_affirmative = drush_get_context('DRUSH_AFFIRMATIVE');
          drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
          // Invoke a new process to download dependencies.
          $result = drush_invoke_process('@self', 'pm-download', $unmet_project_list, array(), array('interactive' => TRUE));
          // Restore DRUSH_AFFIRMATIVE context.
          drush_set_context('DRUSH_AFFIRMATIVE', $drush_affirmative);
          // Refresh module cache after downloading the new modules.
          $extension_info = drush_get_extensions();
          $recheck = TRUE;
        }
      }
    }
  }

  if (!empty($modules)) {
    $all_dependencies = array();
    $dependencies_ok = TRUE;
    foreach ($dependencies as $key => $info) {
      if (isset($info['error'])) {
        unset($modules[$key]);
        $dependencies_ok = drush_set_error($info['error']['code'], $info['error']['message']);
      }
      elseif (!empty($info['dependencies'])) {
        // Make sure we have an assoc array.
        $assoc = drupal_map_assoc($info['dependencies']);
        $all_dependencies = array_merge($all_dependencies, $assoc);
      }
    }
    if (!$dependencies_ok) {
      return FALSE;
    }
    $modules = array_diff(array_merge($modules, $all_dependencies), pm_module_list());
    // Discard modules which doesn't meet requirements.
    require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
    foreach ($modules as $key => $module) {
      // Check to see if the module can be installed/enabled (hook_requirements).
      // See @system_modules_submit
      if (!drupal_check_module($module)) {
        unset($modules[$key]);
        drush_set_error('DRUSH_PM_ENABLE_MODULE_UNMEET_REQUIREMENTS', dt('Module !module doesn\'t meet the requirements to be enabled.', array('!module' => $module)));
        _drush_log_drupal_messages();
        return FALSE;
      }
    }
  }

  $searchpath = array();
  foreach (array_merge($modules, $themes) as $name) {
    $searchpath[] = dirname($extension_info[$name]->filename);
  }
  // Add all modules that passed validation to the drush
  // list of commandfiles (if they have any).  This
  // will allow these newly-enabled modules to participate
  // in the pre-pm_enable and post-pm_enable hooks.
  if (!empty($searchpath)) {
    _drush_add_commandfiles($searchpath);
  }

  drush_set_context('PM_ENABLE_EXTENSION_INFO', $extension_info);
  drush_set_context('PM_ENABLE_MODULES', $modules);
  drush_set_context('PM_ENABLE_THEMES', $themes);

  return TRUE;
}

/**
 * Command callback. Enable one or more extensions from downloaded projects.
 * Note that the modules and themes to be enabled were evaluated during the
 * pm-enable validate hook, above.
 */
function drush_pm_enable() {
  // Get the data built during the validate phase
  $extension_info = drush_get_context('PM_ENABLE_EXTENSION_INFO');
  $modules = drush_get_context('PM_ENABLE_MODULES');
  $themes = drush_get_context('PM_ENABLE_THEMES');

  // Inform the user which extensions will finally be enabled.
  $extensions = array_merge($modules, $themes);
  if (empty($extensions)) {
    return drush_log(dt('There were no extensions that could be enabled.'), 'ok');
  }
  else {
    drush_print(dt('The following extensions will be enabled: !extensions', array('!extensions' => implode(', ', $extensions))));
    if(!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Enable themes.
  if (!empty($themes)) {
    drush_theme_enable($themes);
  }

  // Enable modules and pass dependency validation in form submit.
  if (!empty($modules)) {
    drush_module_enable($modules);
  }

  // Inform the user of final status.
  $rsc = drush_db_select('system', array('name', 'status'), 'name IN (:extensions)', array(':extensions' => $extensions));
  $problem_extensions = array();
  while ($extension = drush_db_fetch_object($rsc)) {
    if ($extension->status) {
      drush_log(dt('!extension was enabled successfully.', array('!extension' => $extension->name)), 'ok');
    }
    else {
      $problem_extensions[] = $extension->name;
    }
  }
  if (!empty($problem_extensions)) {
    return drush_set_error('DRUSH_PM_ENABLE_EXTENSION_ISSUE', dt('There was a problem enabling !extension.', array('!extension' => implode(',', $problem_extensions))));
  }
  // Return the list of extensions enabled
  return $extensions;
}

/**
 * Command callback. Disable one or more extensions.
 */
function drush_pm_disable() {
  $args = pm_parse_arguments(func_get_args());

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
    $dependents = array_intersect($dependents, pm_module_list());
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
  $rsc = drush_db_select('system', array('name', 'status'), 'name IN (:extensions)', array(':extensions' => $extensions));
  $problem_extensions = array();
  while ($extension = drush_db_fetch_object($rsc)) {
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
 * Add extensions that match extension_name*.
 *
 * A helper function for commands that take a space separated list of extension
 * names. It will identify extensions that have been passed in with a
 * trailing * and add all matching extensions to the array that is returned.
 *
 * @param $extensions
 *   An array of extensions, by reference.
 * @param $extension_info
 *   Optional. An array of extension info as returned by drush_get_extensions().
 */
function _drush_pm_expand_extensions(&$extensions, $extension_info = array()) {
  if (empty($extension_info)) {
    $extension_info = drush_get_extensions();
  }
  foreach ($extensions as $key => $extension) {
    if (($wildcard = rtrim($extension, '*')) !== $extension) {
      foreach (array_keys($extension_info) as $extension_name) {
        if (substr($extension_name, 0, strlen($wildcard)) == $wildcard) {
          $extensions[] = $extension_name;
        }
      }
      unset($extensions[$key]);
      continue;
    }
  }
}

/**
 * Command callback. Uninstall one or more modules.
 * // TODO: Use drupal_execute on system_modules_uninstall_confirm_form so that input is validated.
 */
function drush_pm_uninstall() {
  $modules = pm_parse_arguments(func_get_args());

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

/**
 * Command callback. Show available releases for given project(s).
 */
function drush_pm_releases() {
  if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
    $requests = array('drupal');
  }

  // Parse out project name and version.
  $requests = pm_parse_project_version($requests);

  $info = release_info_get_releases($requests);
  if (!$info) {
    return drush_log(dt('No valid projects given.'), 'ok');
  }

  $all = drush_get_option('all', FALSE);
  $dev = drush_get_option('dev', FALSE);
  foreach ($info as $name => $project) {
    $header = dt('------- RELEASES FOR \'!name\' PROJECT -------', array('!name' => strtoupper($name)));
    $rows = array();
    $rows[] = array(dt('Release'), dt('Date'), dt('Status'));
    $releases = release_info_filter_releases($project['releases'], $all, $dev);
    foreach ($releases as $release) {
      $rows[] = array(
        $release['version'],
        gmdate('Y-M-d', $release['date']),
        implode(', ', $release['release_status'])
      );
    }
    drush_print($header);
    drush_print_table($rows, TRUE, array(0 => 14));
  }

  return $info;
}

/**
 * Command callback. Show release notes for given project(s).
 */
function drush_pm_releasenotes() {
  if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
    $requests = array('drupal');
  }
  $requests = pm_parse_project_version($requests);

  return release_info_print_releasenotes($requests);
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
 * Command callback. Execute pm-update.
 */
function drush_pm_update() {
  // Call pm-updatecode.  updatedb will be called in the post-update process.
  $args = pm_parse_arguments(func_get_args(), FALSE);
  return drush_invoke('pm-updatecode', $args);
}

/**
 * Post-command callback.
 * Execute updatedb command after an updatecode - user requested `update`.
 */
function drush_pm_post_pm_update() {
  // Use drush_invoke_process to start a subprocess. Cleaner that way.
  if (drush_get_context('DRUSH_PM_UPDATED', FALSE) !== FALSE) {
    drush_invoke_process('@self', 'updatedb');
  }
}

/**
 * Validate callback for updatecode command. Abort if 'backup' directory exists.
 */
function drush_pm_updatecode_validate() {
  $path = drush_get_context('DRUSH_DRUPAL_ROOT') . '/backup';
  if (is_dir($path) && (realpath(drush_get_option('backup-dir', FALSE)) != $path)) {
    return drush_set_error('', dt('Backup directory !path found. It\'s a security risk to store backups inside the Drupal tree. Drush now uses by default ~/drush-backups. You need to move !path out of the Drupal tree to proceed. Note: if you know what you\'re doing you can explicitly set --backup-dir to !path and continue.', array('!path' => $path)));
  }
}

/**
 * Post-command callback for updatecode.
 *
 * Execute pm-updatecode-postupdate in a backend process to not conflict with
 * old code already in memory.
 */
function drush_pm_post_pm_updatecode() {
  // Skip if updatecode was invoked by pm-update.
  // This way we avoid being noisy, as updatedb is to be executed.
  $command = drush_get_command();
  if ($command['command'] != 'pm-update') {
    if (drush_get_context('DRUSH_PM_UPDATED', FALSE) !== FALSE) {
      drush_invoke_process('@self', 'pm-updatecode-postupdate');
    }
  }
}

/**
 * Command callback. Execute updatecode-postupdate.
 */
function drush_pm_updatecode_postupdate() {
  // Clear the cache, since some projects could have moved around.
  drush_drupal_cache_clear_all();

  // Notify of pending database updates.
  // Make sure the installation API is available
  require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';

  // Load all .install files.
  drupal_load_updates();

  // @see system_requirements().
  foreach (pm_module_list() as $module) {
    $updates = drupal_get_schema_versions($module);
    if ($updates !== FALSE) {
      $default = drupal_get_installed_schema_version($module);
      if (max($updates) > $default) {
        drush_log(dt("You have pending database updates. Run `drush updatedb` or visit update.php in your browser."), 'warning');
        break;
      }
    }
  }
}

/**
 * Sanitize user provided arguments to several pm commands.
 *
 * Return an array of arguments off a space and/or comma separated values.
 */
function pm_parse_arguments($args, $dashes_to_underscores = TRUE) {
  $arguments = _convert_csv_to_array($args);
  foreach ($arguments as $key => $argument) {
    $argument = ($dashes_to_underscores) ? strtr($argument, '-', '_') : $argument;
  }
  return $arguments;
}

/**
 * Parse out the project name and version and return as a structured array.
 *
 * @param $requests an array of project names
 */
function pm_parse_project_version($requests) {
  $requestdata = array();
  $drupal_version_default = drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION', drush_get_option('default-major', 7)) . '.x';
  $drupal_bootstrap = drush_get_context('DRUSH_BOOTSTRAP_PHASE') > 0;
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
        drush_log('DRUSH_PM_HEAD', 'Can\'t download HEAD releases because Drupal.org project information only provides for numbered release nodes.', 'warning');
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
    // special checking for 'drupal-VERSION', == drupal latest stable release
    elseif ((substr($request,0,7) == 'drupal-') && (is_numeric(substr($request,7)))) {
      $project = 'drupal';
      $drupal_version = substr($request,7) . '.x';
      $project_version = $version;
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


/**
 * @defgroup engines Engine types
 * @{
 */

/**
 * Implementation of hook_drush_engine_type_info().
 */
function pm_drush_engine_type_info() {
  return array(
    'package_handler' => array(
      'option' => 'package-handler',
      'description' => 'Determine how to fetch projects from update service.',
      'default' => 'wget',
      'options' => array(
        'cache' => 'Cache release XML and tarballs or git clones. Git clones use git\'s --reference option.',
      ),
    ),
    'release_info' => array(
      'add-options-to-command' => TRUE,
    ),
    'update_info' => array(
    ),
    'version_control' => array(
      'option' => 'version-control',
      'default' => 'backup',
      'description' => 'Integrate with version control systems.',
    ),
  );
}

/**
 * Used by dl and updatecode commands to determine how to download/checkout new projects and acquire updates to projects.
 */
function pm_drush_engine_package_handler() {
  return array(
    'wget' => array(
      'description' => 'Download project packages using wget or curl.',
    ),
    'git_drupalorg' => array(
      'description' => 'Use git.drupal.org to checkout and update projects.',
      'options' => array(
        'gitusername' => 'Your git username as shown on user/[uid]/edit/git. Typically, this is set this in drushrc.php. Omitting this prevents users from pushing changes back to git.drupal.org.',
        'gitsubmodule' => 'Use git submodules for checking out new projects. Existing git checkouts are unaffected, and will continue to (not) use submodules regardless of this setting.',
        'gitcheckoutparams' => 'Add options to the `git checkout` command.',
        'gitcloneparams' => 'Add options to the `git clone` command.',
        'gitfetchparams' => 'Add options to the `git fetch` command.',
        'gitpullparams' => 'Add options to the `git pull` command.',
        'gitinfofile' => 'Inject version info into each .info file.',
      ),
      'sub-options' => array(
        'gitsubmodule' => array(
          'gitsubmoduleaddparams' => 'Add options to the `git submodule add` command.',
        ),
      ),
    ),
  );
}

/**
 * Used by dl and updatecode commands to determine how to download/checkout new projects and acquire updates to projects.
 */
function pm_drush_engine_release_info() {
  return array(
    'updatexml' => array(
      'description' => 'Drush release info engine for update.drupal.org and compatible services.',
      'options' => array(
        'source' => 'The base URL which provides project release history in XML. Defaults to http://updates.drupal.org/release-history.',
        'dev' => 'Work with development releases solely.',
      ),
      'sub-options' => array(
        'cache' => array(
          'cache-duration-releasexml' => 'Expire duration (in seconds) for release XML. Defaults to 86400 (24 hours).',
        ),
        'select' => array(
          'all' => 'Shows all available releases instead of a short list of recent releases.',
        ),
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
      'description' => 'Backup all project files before updates.',
      'options' => array(
        'no-backup' => 'Do not perform backups.',
        'backup-dir' => 'Specify a directory to backup projects into. Defaults to drush-backups within the home directory of the user running the command. It is forbidden to specify a directory inside your drupal root.',
      ),
    ),
    'bzr' => array(
      'signature' => 'bzr root %s',
      'description' => 'Quickly add/remove/commit your project changes to Bazaar.',
      'options' => array(
        'bzrsync' => 'Automatically add new files to the Bazaar repository and remove deleted files. Caution.',
        'bzrcommit' => 'Automatically commit changes to Bazaar repository. You must also use the --bzrsync option.',
      ),
      'sub-options' => array(
        'bzrcommit' => array(
          'bzrmessage' => 'Override default commit message which is: Drush automatic commit. Project <name> <type> Command: <the drush command line used>',
        ),
      ),
      'examples' => array(
        'drush dl cck --version-control=bzr --bzrsync --bzrcommit' =>  'Download the cck project and then add it and commit it to Bazaar.'
      ),
    ),
    'svn' => array(
      'signature' => 'svn info %s',
      'description' => 'Quickly add/remove/commit your project changes to Subversion.',
      'options' => array(
        'svnsync' => 'Automatically add new files to the SVN repository and remove deleted files. Caution.',
        'svncommit' => 'Automatically commit changes to SVN repository. You must also using the --svnsync option.',
        'svnstatusparams' => "Add options to the 'svn status' command",
        'svnaddparams' => 'Add options to the `svn add` command',
        'svnremoveparams' => 'Add options to the `svn remove` command',
        'svnrevertparams' => 'Add options to the `svn revert` command',
        'svncommitparams' => 'Add options to the `svn commit` command',
      ),
      'sub-options' => array(
        'svncommit' => array(
         'svnmessage' => 'Override default commit message which is: Drush automatic commit: <the drush command line used>',
        ),
      ),
      'examples' => array(
        'drush [command] cck --svncommitparams=\"--username joe\"' =>  'Commit changes as the user \'joe\' (Quotes are required).'
      ),
    ),
  );
}

/**
 * @} End of "Engine types".
 */

/**
 * Interface for version control systems.
 * We use a simple object layer because we conceivably need more than one
 * loaded at a time.
 */
interface drush_version_control {
  function pre_update(&$project);
  function rollback($project);
  function post_update($project);
  function post_download($project);
  static function reserved_files();
}

/**
 * A simple factory function that tests for version control systems, in a user
 * specified order, and return the one that appears to be appropriate for a
 * specific directory.
 */
function drush_pm_include_version_control($directory = '.') {
  $engine_info = drush_get_engines('version_control');
  $version_controls = drush_get_option('version-control', FALSE);
  // If no version control was given, use a list of defaults.
  if (!$version_controls) {
    // Backup engine is the last option.
    $version_controls = array_reverse(array_keys($engine_info['engines']));
  }
  else {
    $version_controls = array($version_controls);
  }

  // Find the first valid engine in the list, checking signatures if needed.
  $engine = FALSE;
  while (!$engine && count($version_controls)) {
    $version_control = array_shift($version_controls);
    if (isset($engine_info['engines'][$version_control])) {
      if (!empty($engine_info['engines'][$version_control]['signature'])) {
        drush_log(dt('Verifying signature for !vcs version control engine.', array('!vcs' => $version_control)), 'debug');
        if (drush_shell_exec($engine_info['engines'][$version_control]['signature'], $directory)) {
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

  $instance = drush_include_engine('version_control', $version_control);
  $instance->engine = $engine;
  return $instance;
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
    if ($name == 'drupal') {
      continue;
    }
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

function _drush_pm_extension_cache_file() {
  return drush_get_context('DRUSH_PER_USER_CONFIGURATION') . "/drush-extension-cache.inc";
}

function _drush_pm_get_extension_cache() {
  $extension_cache = array();
  $cache_file = _drush_pm_extension_cache_file();

  if (file_exists($cache_file)) {
    include $cache_file;
  }
  if (!array_key_exists('extension-map', $extension_cache)) {
    $extension_cache['extension-map'] = array();
  }
  return $extension_cache;
}

function drush_pm_lookup_extension_in_cache($extension) {
  $result = NULL;
  $extension_cache = _drush_pm_get_extension_cache();
  if (!empty($extension_cache) && array_key_exists($extension, $extension_cache)) {
    $result = $extension_cache[$extension];
  }
  return $result;
}

function drush_pm_put_extension_cache($extension_cache) {
}

function drush_pm_cache_project_extensions($project, $found) {
  $extension_cache = _drush_pm_get_extension_cache();
  foreach($found as $extension) {
    // Simple cache does not handle conflicts
    // We could keep an array of projects, and count
    // how many times each one has been seen...
    $extension_cache[$extension] = $project['name'];
  }
  drush_pm_put_extension_cache($extension_cache);
}

/**
 * Print out all extensions (modules/themes/profiles) found in specified project.
 *
 * Find .info files in the project path and identify modules, themes and
 * profiles. It handles two kind of projects: drupal core/profiles and
 * modules/themes.
 * It does nothing with theme engine projects.
 */
function drush_pm_extensions_in_project($project) {
  // Mask for drush_scan_directory, to  avoid tests directories.
  $nomask = array('.', '..', 'CVS', 'tests');

  // Drupal core and profiles can contain modules, themes and profiles.
  if (in_array($project['project_type'], array('core', 'profile'))) {
    $found = array('profile' => array(), 'theme' => array(), 'module' => array());
    // Find all of the .info files
    foreach (drush_scan_directory($project['project_install_location'], "/.*\.info$/", $nomask) as $filename => $info) {
      // Find the project type corresponding the .info file.
     // (Only drupal >=7.x has .info for .profile)
      $base = dirname($filename) . '/' . $info->name;
      if (is_file($base . '.module')) {
        $found['module'][] = $info->name;
      }
      else if (is_file($base . '.profile')) {
        $found['profile'][] = $info->name;
      }
      else {
        $found['theme'][] = $info->name;
      }
    }
    // Special case: find profiles for drupal < 7.x (no .info)
    if ($project['drupal_version'][0] < 7) {
      foreach (drush_find_profiles($project['project_install_location']) as $filename => $info) {
        $found['profile'][] = $info->name;
      }
    }
    // Log results.
    $msg = "Project !project contains:\n";
    $args = array('!project' => $project['name']);
    foreach (array_keys($found) as $type) {
      if ($count = count($found[$type])) {
        $msg .= " - !count_$type !type_$type: !found_$type\n";
        $args += array("!count_$type" => $count, "!type_$type" => $type, "!found_$type" => implode(', ', $found[$type]));
        if ($count > 1) {
          $args["!type_$type"] = $type.'s';
        }
      }
    }
    drush_log(dt($msg, $args), 'success');
    drush_print_pipe(call_user_func_array('array_merge', array_values($found)));
  }
  // Modules and themes can only contain other extensions of the same type.
  elseif (in_array($project['project_type'], array('module', 'theme'))) {
    // Find all of the .info files
    $found = array();
    foreach (drush_scan_directory($project['project_install_location'], "/.*\.info$/", $nomask) as $filename => $info) {
      $found[] = $info->name;
    }
    // Log results.
    // If there is only one module / theme in the project, only print out
    // the message if is different than the project name.
    if (count($found) == 1) {
      if ($found[0] != $project['name']) {
        $msg = "Project !project contains a !type named !found.";
      }
    }
    // If there are multiple modules or themes in the project, list them all.
    else {
      $msg = "Project !project contains !count !types: !found.";
    }
    if (isset($msg)) {
      drush_print(dt($msg, array('!project' => $project['name'], '!count' => count($found), '!type' => $project['project_type'], '!found' => implode(', ', $found))));
    }
    drush_print_pipe($found);
    // Cache results.
    drush_pm_cache_project_extensions($project, $found);
  }
}

/**
 * Return an array of empty directories.
 *
 * Walk a directory and return an array of subdirectories that are empty. Will
 * return the given directory if it's empty.
 * If a list of items to exclude is provided, subdirectories will be condidered
 * empty even if they include any of the items in the list.
 *
 * @param string $dir
 *   Path to the directory to work in.
 * @param array $exclude
 *   Array of files or directory to exclude in the check.
 *
 * @return array
 *   A list of directory paths that are empty. A directory is deemed to be empty
 *   if it only contains excluded files or directories.
 */
function drush_find_empty_directories($dir, $exclude = array()) {
  // Skip files.
  if (!is_dir($dir)) {
    return array();
  }
  $to_exclude = array_merge(array('.', '..'), $exclude);
  $empty_dirs = array();
  $dir_is_empty = TRUE;
  foreach (scandir($dir) as $file) {
    // Skip excluded directories.
    if (in_array($file, $to_exclude)) {
      continue;
    }
    // Recurse into sub-directories to find potentially empty ones.
    $subdir = $dir . '/' . $file;
    $empty_dirs += drush_find_empty_directories($subdir, $exclude);
    // $empty_dir will not contain $subdir, if it is a file or if the
    // sub-directory is not empty. $subdir is only set if it is empty.
    if (!isset($empty_dirs[$subdir])) {
      $dir_is_empty = FALSE;
    }
  }

  if ($dir_is_empty) {
    $empty_dirs[$dir] = $dir;
  }
  return $empty_dirs;
}

/**
 * Inject metadata into all .info files for a given project.
 *
 * @param string $project_dir
 *   The full path to the root directory of the project to operate on.
 * @param string $project_name
 *   The project machine name (AKA shortname).
 * @param string $version
 *   The version string to inject into the .info file(s).
 *
 * @return boolean
 *   TRUE on success, FALSE on any failures appending data to .info files.
 */
function drush_pm_inject_info_file_metadata($project_dir, $project_name, $version) {
  $info_files = drush_scan_directory($project_dir, '/.*\.info$/');
  if (!empty($info_files)) {
    // Construct the string of metadata to append to all the .info files.
    // Taken straight from: http://drupalcode.org/project/drupalorg.git/blob/refs/heads/6.x-3.x:/drupalorg_project/plugins/release_packager/DrupalorgProjectPackageRelease.class.php#l192
    $info = "\n\n; Information added by drush on " . date('Y-m-d') . "\n";
    $info .= "version = \"$version\"\n";
    // .info files started with 5.x, so we don't have to worry about version
    // strings like "4.7.x-1.0" in this regular expression. If we can't parse
    // the version (also from an old "HEAD" release), or the version isn't at
    // least 6.x, don't add any "core" attribute at all.
    $matches = array();
    if (preg_match('/^((\d+)\.x)-.*/', $version, $matches) && $matches[2] >= 6) {
      $info .= "core = \"$matches[1]\"\n";
    }
    $info .= "project = \"$project_name\"\n";
    $info .= 'datestamp = "'. time() ."\"\n";
    $info .= "\n";
    foreach ($info_files as $info_file) {
      if (!drush_file_append_data($info_file->filename, $info)) {
        return FALSE;
      }
    }
  }
  return TRUE;
}
