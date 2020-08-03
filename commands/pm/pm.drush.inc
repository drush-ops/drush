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

use Drush\Log\LogLevel;

/**
 * @defgroup update_status_constants Update Status Constants
 * @{
 * Represents update status of projects.
 *
 * The first set is a mapping of some constants declared in update.module.
 * We only declare the ones we're interested in.
 * The rest of the constants are used by pm-updatestatus to represent
 * a status when the user asked for updates to specific versions or
 * other circumstances not managed by Drupal.
 */

/**
 * Project is missing security update(s).
 *
 * Maps UPDATE_NOT_SECURE.
 */
const DRUSH_UPDATESTATUS_NOT_SECURE = 1;

/**
 * Current release has been unpublished and is no longer available.
 *
 * Maps UPDATE_REVOKED.
 */
const DRUSH_UPDATESTATUS_REVOKED = 2;

/**
 * Current release is no longer supported by the project maintainer.
 *
 * Maps UPDATE_NOT_SUPPORTED.
 */
const DRUSH_UPDATESTATUS_NOT_SUPPORTED = 3;

/**
 * Project has a new release available, but it is not a security release.
 *
 * Maps UPDATE_NOT_CURRENT.
 */
const DRUSH_UPDATESTATUS_NOT_CURRENT = 4;

/**
 * Project is up to date.
 *
 * Maps UPDATE_CURRENT.
 */
const DRUSH_UPDATESTATUS_CURRENT = 5;

/**
 * Project's status cannot be checked.
 *
 * Maps UPDATE_NOT_CHECKED.
 */
const DRUSH_UPDATESTATUS_NOT_CHECKED = -1;

/**
 * No available update data was found for project.
 *
 * Maps UPDATE_UNKNOWN.
 */
const DRUSH_UPDATESTATUS_UNKNOWN = -2;

/**
 * There was a failure fetching available update data for this project.
 *
 * Maps UPDATE_NOT_FETCHED.
 */
const DRUSH_UPDATESTATUS_NOT_FETCHED = -3;

/**
 * We need to (re)fetch available update data for this project.
 *
 * Maps UPDATE_FETCH_PENDING.
 */
const DRUSH_UPDATESTATUS_FETCH_PENDING = -4;

/**
 * Project was not packaged by drupal.org.
 */
const DRUSH_UPDATESTATUS_PROJECT_NOT_PACKAGED = 101;

/**
 * Requested project is not updateable.
 */
const DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_UPDATEABLE = 102;

/**
 * Requested project not found.
 */
const DRUSH_UPDATESTATUS_REQUESTED_PROJECT_NOT_FOUND = 103;

/**
 * Requested version not found.
 */
const DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_FOUND = 104;

/**
 * Requested version available.
 */
const DRUSH_UPDATESTATUS_REQUESTED_VERSION_NOT_CURRENT = 105;

/**
 * Requested version already installed.
 */
const DRUSH_UPDATESTATUS_REQUESTED_VERSION_CURRENT = 106;

/**
 * @} End of "defgroup update_status_constants".
 */

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
    case 'drush:pm-download':
      return dt("Download Drupal core or projects from drupal.org (Drupal core, modules, themes or profiles) and other sources. It will automatically figure out which project version you want based on its recommended release, or you may specify a particular version.

If no --destination is provided, then destination depends on the project type:
  - Profiles will be downloaded to profiles/ in your Drupal root.
  - Modules and themes will be downloaded to the site specific directory (sites/example.com/modules|themes) if available, or to the site wide directory otherwise.
  - If you're downloading drupal core or you are not running the command within a bootstrapped drupal site, the default location is the current directory.
  - Drush commands will be relocated to @site_wide_location (if available) or ~/.drush. Relocation is determined once the project is downloaded by examining its content. Note you can provide your own function in a commandfile to determine the relocation of any project.", array('@site_wide_location' => drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES')));
  }
}

/**
 * Implementation of hook_drush_command().
 */
function pm_drush_command() {
  $update_options = array(
    'lock' => array(
      'description' => 'Add a persistent lock to remove the specified projects from consideration during updates.  Locks may be removed with the --unlock parameter, or overridden by specifically naming the project as a parameter to pm-update or pm-updatecode.  The lock does not affect pm-download.  See also the update_advanced project for similar and improved functionality.',
      'example-value' => 'foo,bar',
    ),
  );
  $update_suboptions = array(
    'lock' => array(
      'lock-message' => array(
        'description' => 'A brief message explaining why a project is being locked; displayed during pm-updatecode.  Optional.',
        'example-value' => 'message',
      ),
      'unlock' => array(
        'description' => 'Remove the persistent lock from the specified projects so that they may be updated again.',
        'example-value' => 'foo,bar',
      ),
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
    'aliases' => array('en', 'pm:enable'),
    'engines' => array(
      'release_info' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
  );
  $items['pm-disable'] = array(
    'description' => 'Disable one or more extensions (modules or themes).',
    'arguments' => array(
      'extensions' => 'A list of modules or themes. You can use the * wildcard at the end of extension names to disable multiple matches.',
    ),
    'aliases' => array('dis', 'pm:disable'),
    'engines' => array(
      'version_control',
      'package_handler',
      'release_info' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
  );
  $items['pm-info'] = array(
    'description' => 'Show detailed info for one or more extensions (modules or themes).',
    'arguments' => array(
      'extensions' => 'A list of modules or themes. You can use the * wildcard at the end of extension names to show info for multiple matches. If no argument is provided it will show info for all available extensions.',
    ),
    'aliases' => array('pmi', 'pm:info'),
    'outputformat' => array(
      'default' => 'key-value-list',
      'pipe-format' => 'json',
      'formatted-filter' => '_drush_pm_info_format_table_data',
      'field-labels' => array(
        'extension' => 'Extension',
        'project' => 'Project',
        'type' => 'Type',
        'title' => 'Title',
        'description' => 'Description',
        'version' => 'Version',
        'date' => 'Date',
        'package' => 'Package',
        'core' => 'Core',
        'php' => 'PHP',
        'status' => 'Status',
        'path' => 'Path',
        'schema_version' => 'Schema version',
        'files' => 'Files',
        'requires' => 'Requires',
        'required_by' => 'Required by',
        'permissions' => 'Permissions',
        'config' => 'Configure',
        'engine' => 'Engine',
        'base_theme' => 'Base theme',
        'regions' => 'Regions',
        'features' => 'Features',
        'stylesheets' => 'Stylesheets',
        // 'media_' . $media  => 'Media '. $media for each $info->info['stylesheets'] as $media => $files
        'scripts' => 'Scripts',
      ),
      'output-data-type' => 'format-table',
    ),
  );

  $items['pm-projectinfo'] = array(
    'description' => 'Show a report of available projects and their extensions.',
    'arguments' => array(
      'projects' => 'Optional. A list of installed projects to show.',
    ),
    'options' => array(
      'drush' => 'Optional. Only incude projects that have one or more Drush commands.',
      'status' => array(
        'description' => 'Filter by project status. Choices: enabled, disabled. A project is considered enabled when at least one of its extensions is enabled.',
        'example-value' => 'enabled',
      ),
    ),
    'outputformat' => array(
      'default' => 'key-value-list',
      'pipe-format' => 'json',
      'field-labels' => array(
        'label'      => 'Name',
        'type'       => 'Type',
        'version'    => 'Version',
        'status'     => 'Status',
        'extensions' => 'Extensions',
        'drush'      => 'Drush Commands',
        'datestamp'  => 'Datestamp',
        'path'       => 'Path',
      ),
      'fields-default' => array('label', 'type', 'version', 'status', 'extensions', 'drush', 'datestamp', 'path'),
      'fields-pipe' => array('label'),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('pmpi', 'pm:projectinfo'),
  );

  // Install command is reserved for the download and enable of projects including dependencies.
  // @see http://drupal.org/node/112692 for more information.
  // $items['install'] = array(
  //     'description' => 'Download and enable one or more modules',
  //   );
  $items['pm-uninstall'] = array(
    'description' => 'Uninstall one or more modules and their dependent modules.',
    'arguments' => array(
      'modules' => 'A list of modules.',
    ),
    'aliases' => array('pmu', 'pm:uninstall'),
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
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'list',
      'field-labels' => array('package' => 'Package', 'name' => 'Name', 'type' => 'Type', 'status' => 'Status', 'version' => 'Version'),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('pml', 'pm:list'),
  );
  $items['pm-refresh'] = array(
    'description' => 'Refresh update status information.',
    'engines' => array(
      'update_status' => array(
        'add-options-to-command' => FALSE,
      ),
    ),
    'aliases' => array('rf', 'pm:refresh'),
  );
  $items['pm-updatestatus'] = array(
    'description' => 'Show a report of available minor updates to Drupal core and contrib projects.',
    'arguments' => array(
      'projects' => 'Optional. A list of installed projects to show.',
    ),
    'options' => array(
      'pipe' => 'Return a list of the projects with any extensions enabled that need updating, one project per line.',
    ) + $update_options,
    'sub-options' => $update_suboptions,
    'engines' => array(
      'update_status',
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'list',
      'field-labels' => array('name' => 'Short Name', 'label' => 'Name', 'existing_version' => 'Installed Version', 'status' => 'Status', 'status_msg' => 'Message', 'candidate_version' => 'Proposed version'),
      'fields-default' => array('label', 'existing_version', 'candidate_version', 'status_msg' ),
      'fields-pipe' => array('name', 'existing_version', 'candidate_version', 'status_msg'),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('ups', 'pm:updatestatus'),
  );
  $items['pm-updatecode'] = array(
    'description' => 'Update Drupal core and contrib projects to latest recommended releases.',
    'examples' => array(
      'drush pm-updatecode --no-core' => 'Update contrib projects, but skip core.',
      'drush pm-updatestatus --format=csv --list-separator=" " --fields="name,existing_version,candidate_version,status_msg"' => 'To show a list of projects with their update status, use pm-updatestatus instead of pm-updatecode.',
    ),
    'arguments' => array(
      'projects' => 'Optional. A list of installed projects to update.',
    ),
    'options' => array(
      'notes' => 'Show release notes for each project to be updated.',
      'no-core' => 'Only update modules and skip the core update.',
      'check-updatedb' => 'Check to see if an updatedb is needed after updating the code. Default is on; use --check-updatedb=0 to disable.',
    ) + $update_options,
    'sub-options' => $update_suboptions,
    'aliases' => array('upc', 'pm:updatecode'),
    'topics' => array('docs-policy'),
    'engines' => array(
      'version_control',
      'package_handler',
      'release_info' => array(
        'add-options-to-command' => FALSE,
      ),
      'update_status',
    ),
  );
  // Merge all items from above.
  $items['pm-update'] = array(
    'description' => 'Update Drupal core and contrib projects and apply any pending database updates (Same as pm-updatecode + updatedb).',
    'aliases' => array('up', 'pm:update'),
    'allow-additional-options' => array('pm-updatecode', 'updatedb'),
  );
  $items['pm-updatecode-postupdate'] = array(
    'description' => 'Notify of pending db updates.',
    'hidden' => TRUE,
    'aliases' => array('pm:updatecode:postupdate'),
  );
  $items['pm-releasenotes'] = array(
    'description' => 'Print release notes for given projects.',
    'arguments' => array(
      'projects' => 'A list of project names, with optional version. Defaults to \'drupal\'',
    ),
    'options' => array(
      'html' => dt('Display release notes in HTML rather than plain text.'),
    ),
    'examples' => array(
      'drush rln cck' => 'Prints the release notes for the recommended version of CCK project.',
      'drush rln token-1.13' => 'View release notes of a specfic version of the Token project for my version of Drupal.',
      'drush rln pathauto zen' => 'View release notes for the recommended version of Pathauto and Zen projects.',
    ),
    'aliases' => array('rln', 'pm:releasenotes'),
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
    'options' => array(
      'default-major' => 'Show releases compatible with the specified major version of Drupal.',
    ),
    'aliases' => array('rl', 'pm:releases'),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'csv',
      'field-labels' => array(
        'project' => 'Project',
        'version' => 'Release',
        'date' => 'Date',
        'status' => 'Status',
        'release_link' => 'Release link',
        'download_link' => 'Download link',
      ),
      'fields-default' => array('project', 'version', 'date', 'status'),
      'fields-pipe' => array('project', 'version', 'date', 'status'),
      'output-data-type' => 'format-table',
    ),
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
        'description' => 'Specify the default major version of modules to download when there is no bootstrapped Drupal site.  Defaults to "8".',
        'example-value' => '7',
      ),
      'skip' => 'Skip automatic downloading of libraries (c.f. devel).',
      'pipe' => 'Returns a list of the names of the extensions (modules and themes) contained in the downloaded projects.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('dl', 'pm:download'),
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
  $a_type = drush_extension_get_type($a);
  $b_type = drush_extension_get_type($b);
  if ($a_type == 'module' && $b_type == 'theme') {
    return -1;
  }
  if ($a_type == 'theme' && $b_type == 'module') {
    return 1;
  }
  $cmp = strcasecmp($a->info['package'], $b->info['package']);
  if ($cmp == 0) {
    $cmp = strcasecmp($a->info['name'], $b->info['name']);
  }
  return $cmp;
}

/**
 * Calculate an extension status based on current status and schema version.
 *
 * @param $extension
 *   Object of a single extension info.
 *
 * @return
 *   String describing extension status. Values: enabled|disabled|not installed
 */
function drush_get_extension_status($extension) {
  if ((drush_extension_get_type($extension) == 'module') && ($extension->schema_version == -1)) {
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
    $type = drush_extension_get_type($extension_info[$extension]);
    if ($type == 'module') {
      $modules[$extension] = $extension;
    }
    else if ($type == 'theme') {
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
  if (!isset($extensions)) {
    $extensions = drush_get_extensions();
  }
  $projects = array(
    'drupal' => array(
      'label'      => 'Drupal',
      'version'    => drush_drupal_version(),
      'type'       => 'core',
      'extensions' => array(),
    )
  );
  if (isset($extensions['system']->info['datestamp'])) {
    $projects['drupal']['datestamp'] = $extensions['system']->info['datestamp'];
  }
  foreach ($extensions as $extension) {
    $extension_name = drush_extension_get_name($extension);
    $extension_path = drush_extension_get_path($extension);

    // Obtain the project name. It is not available in this cases:
    //   1. the extension is part of drupal core.
    //   2. the project was checked out from CVS/git and cvs_deploy/git_deploy
    //      is not installed.
    //   3. it is not a project hosted in drupal.org.
    if (empty($extension->info['project'])) {
      if (isset($extension->info['version']) && ($extension->info['version'] == drush_drupal_version())) {
        $project = 'drupal';
      }
      else {
        if (is_dir($extension_path . '/CVS') && (!drush_module_exists('cvs_deploy'))) {
          $extension->vcs = 'cvs';
          drush_log(dt('Extension !extension is fetched from cvs. Ignoring.', array('!extension' => $extension_name)), LogLevel::DEBUG);
        }
        elseif (is_dir($extension_path . '/.git') && (!drush_module_exists('git_deploy'))) {
          $extension->vcs = 'git';
          drush_log(dt('Extension !extension is fetched from git. Ignoring.', array('!extension' => $extension_name)), LogLevel::DEBUG);
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
        // If there's an extension with matching name, pick its label.
        // Otherwise use just the project name. We avoid $extension->label
        // for the project label because the extension's label may have
        // no direct relation with the project name. For example,
        // "Text (text)" or "Number (number)" for the CCK project.
        'label'      => isset($extensions[$project]) ? $extensions[$project]->label : $project,
        'type'       => drush_extension_get_type($extension),
        'version'    => $extension->info['version'],
        'status'     => $extension->status,
        'extensions' => array(),
      );
      if (isset($extension->info['datestamp'])) {
        $projects[$project]['datestamp'] = $extension->info['datestamp'];
      }
      if (isset($extension->info['project status url'])) {
        $projects[$project]['status url'] = $extension->info['project status url'];
      }
    }
    else {
      // If any of the extensions is enabled, consider the project is enabled.
      if ($extension->status != 0) {
        $projects[$project]['status'] = $extension->status;
      }
    }
    $projects[$project]['extensions'][] = drush_extension_get_name($extension);
  }

  // Obtain each project's path and try to provide a better label for ones
  // with machine name.
  $reserved = array('modules', 'sites', 'themes');
  foreach ($projects as $name => $project) {
    if ($name == 'drupal') {
      continue;
    }

    // If this project has no human label, see if we can find
    // one "main" extension whose label we could use.
    if ($project['label'] == $name)  {
      // If there is only one extension, construct a label based on
      // the extension name.
      if (count($project['extensions']) == 1) {
        $extension = $extensions[$project['extensions'][0]];
        $projects[$name]['label'] = $extension->info['name'] .  ' (' . $name . ')';
      }
      else {
        // Make a list of all of the extensions in this project
        // that do not depend on any other extension in this
        // project.
        $candidates = array();
        foreach ($project['extensions'] as $e) {
          $has_project_dependency = FALSE;
          if (isset($extensions[$e]->info['dependencies']) && is_array($extensions[$e]->info['dependencies'])) {
            foreach ($extensions[$e]->info['dependencies'] as $dependent) {
              if (in_array($dependent, $project['extensions'])) {
                $has_project_dependency = TRUE;
              }
            }
          }
          if ($has_project_dependency === FALSE) {
            $candidates[] = $extensions[$e]->info['name'];
          }
        }
        // If only one of the modules is a candidate, use its name in the label
        if (count($candidates) == 1) {
          $projects[$name]['label'] = reset($candidates) .  ' (' . $name . ')';
        }
      }
    }

    drush_log(dt('Obtaining !project project path.', array('!project' => $name)), LogLevel::DEBUG);
    $path = _drush_pm_find_common_path($project['type'], $project['extensions']);
    // Prevent from setting a reserved path. For example it may happen in a case
    // where a module and a theme are declared as part of a same project.
    // There's a special case, a project called "sites", this is the reason for
    // the second condition here.
    if ($path == '.' || (in_array(basename($path), $reserved) && !in_array($name, $reserved))) {
      drush_log(dt('Error while trying to find the common path for enabled extensions of project !project. Extensions are: !extensions.', array('!project' => $name, '!extensions' => implode(', ', $project['extensions']))), LogLevel::ERROR);
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
    drush_log(dt('Unknown path for !extension !type.', array('!extension' => $extension, '!type' => $project_type)), LogLevel::WARNING);
    $extension = array_pop($extensions);
  }

  // If there's only one extension we are done. Otherwise, we need to find
  // the common prefix for all of them.
  if (count($extensions) > 0) {
    // Iterate over the other projects.
    while($extension = array_pop($extensions)) {
      $path2 = drupal_get_path($project_type, $extension);
      if (!$path2) {
        drush_log(dt('Unknown path for !extension !type.', array('!extension' => $extension, '!type' => $project_type)), LogLevel::DEBUG);
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
 * @} End of "defgroup extensions".
 */

/**
 * Command callback. Show a list of extensions with type and status.
 */
function drush_pm_list() {
  // Rebuild the cache if needed (Drupal 9 requirement)
  if (drush_drupal_major_version() >= 9) {
    drupal_flush_all_caches();
  }

  //--package
  $package_filter = array();
  $package = strtolower(drush_get_option('package'));
  if (!empty($package)) {
    $package_filter = explode(',', $package);
  }
  if (!empty($package_filter) && (count($package_filter) == 1)) {
    drush_hide_output_fields('package');
  }

  //--type
  $all_types = array('module', 'theme');
  $type_filter = strtolower(drush_get_option('type'));
  if (!empty($type_filter)) {
    $type_filter = explode(',', $type_filter);
  }
  else {
    $type_filter = $all_types;
  }

  if (count($type_filter) == 1) {
    drush_hide_output_fields('type');
  }
  foreach ($type_filter as $type) {
    if (!in_array($type, $all_types)) { //TODO: this kind of check can be implemented drush-wide
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
  if (count($status_filter) == 1) {
    drush_hide_output_fields('status');
  }

  foreach ($status_filter as $status) {
    if (!in_array($status, $all_status)) { //TODO: this kind of check can be implemented drush-wide
      return drush_set_error('DRUSH_PM_INVALID_PROJECT_STATUS', dt('!status is not a valid project status.', array('!status' => $status)));
  }
  }

  $result = array();
  $extension_info = drush_get_extensions(FALSE);
  uasort($extension_info, '_drush_pm_sort_extensions');

  $major_version = drush_drupal_major_version();
  foreach ($extension_info as $key => $extension) {
    if (!in_array(drush_extension_get_type($extension), $type_filter)) {
      unset($extension_info[$key]);
      continue;
    }
    $status = drush_get_extension_status($extension);
    if (!in_array($status, $status_filter)) {
      unset($extension_info[$key]);
      continue;
    }

    // Filter out core if --no-core specified.
    if (drush_get_option('no-core', FALSE)) {
      if ((($major_version >= 8) && ($extension->origin == 'core')) || (($major_version <= 7) && (strpos($extension->info['package'], 'Core') === 0))) {
        unset($extension_info[$key]);
        continue;
      }
    }

    // Filter out non-core if --core specified.
    if (drush_get_option('core', FALSE)) {
      if ((($major_version >= 8) && ($extension->origin != 'core')) || (($major_version <= 7) && (strpos($extension->info['package'], 'Core') !== 0))) {
        unset($extension_info[$key]);
        continue;
      }
    }

    // Filter by package.
    if (!empty($package_filter)) {
      if (!in_array(strtolower($extension->info['package']), $package_filter)) {
        unset($extension_info[$key]);
        continue;
      }
    }

    $row['package'] = $extension->info['package'];
    $row['name'] = $extension->label;
    $row['type'] = ucfirst(drush_extension_get_type($extension));
    $row['status'] = ucfirst($status);
    // Suppress notice when version is not present.
    $row['version'] = @$extension->info['version'];

    $result[$key] = $row;
    unset($row);
  }
  // In Drush-5, we used to return $extension_info here.
  return $result;
}

/**
 * Helper function for pm-enable.
 */
function drush_pm_enable_find_project_from_extension($extension) {
  $result =  drush_pm_lookup_extension_in_cache($extension);

  if (!isset($result)) {
    $release_info = drush_get_engine('release_info');

    // If we can find info on a project that has the same name
    // as the requested extension, then we'll call that a match.
    $request = pm_parse_request($extension);
    if ($release_info->checkProject($request)) {
      $result = $extension;
    }
  }

  return $result;
}

/**
 * Validate callback. Determine the modules and themes that the user would like enabled.
 */
function drush_pm_enable_validate() {
  // Rebuild the cache if needed (Drupal 9 requirement)
  if (drush_drupal_major_version() >= 9) {
    drupal_flush_all_caches();
  }

  $args = pm_parse_arguments(func_get_args());

  $extension_info = drush_get_extensions();

  $recheck = TRUE;
  $last_download = NULL;
  while ($recheck) {
    $recheck = FALSE;

    // Classify $args in themes, modules or unknown.
    $modules = array();
    $themes = array();
    $download = array();
    drush_pm_classify_extensions($args, $modules, $themes, $extension_info);
    $extensions = array_merge($modules, $themes);
    $unknown = array_diff($args, $extensions);

    // If there're unknown extensions, try and download projects
    // with matching names.
    if (!empty($unknown)) {
      $found = array();
      foreach ($unknown as $name) {
        drush_log(dt('!extension was not found.', array('!extension' => $name)), LogLevel::WARNING);
        $project = drush_pm_enable_find_project_from_extension($name);
        if (!empty($project)) {
          $found[] = $project;
        }
      }
      if (!empty($found)) {
        // Prevent from looping if last download failed.
        if ($found === $last_download) {
          drush_log(dt("Unable to download some or all of the extensions."), LogLevel::WARNING);
          break;
        }
        drush_log(dt("The following projects provide some or all of the extensions not found:\n@list", array('@list' => implode("\n", $found))), LogLevel::OK);
        if (drush_get_option('resolve-dependencies')) {
          drush_log(dt("They are being downloaded."), LogLevel::OK);
        }
        if ((drush_get_option('resolve-dependencies')) || (drush_confirm("Would you like to download them?"))) {
          $download = $found;
        }
      }
    }

    // Discard already enabled and incompatible extensions.
    foreach ($extensions as $name) {
      if ($extension_info[$name]->status) {
        drush_log(dt('!extension is already enabled.', array('!extension' => $name)), LogLevel::OK);
      }
      // Check if the extension is compatible with Drupal core and php version.
      if ($component = drush_extension_check_incompatibility($extension_info[$name])) {
        drush_set_error('DRUSH_PM_ENABLE_MODULE_INCOMPATIBLE', dt('!name is incompatible with the !component version.', array('!name' => $name, '!component' => $component)));
        if (drush_extension_get_type($extension_info[$name]) == 'module') {
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
          foreach ($info['unmet-dependencies'] as $unmet) {
            $unmet_project = (!empty($info['dependencies'][$unmet]['project'])) ? $info['dependencies'][$unmet]['project'] : drush_pm_enable_find_project_from_extension($unmet);
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
        $found = array_merge($download, $unmet_project_list);
        // Prevent from looping if last download failed.
        if ($found === $last_download) {
          drush_log(dt("Unable to download some or all of the extensions."), LogLevel::WARNING);
          break;
        }
        drush_log(dt("The following projects have unmet dependencies:\n!list", array('!list' => implode("\n", $msgs))), LogLevel::OK);
        if (drush_get_option('resolve-dependencies')) {
          drush_log(dt("They are being downloaded."), LogLevel::OK);
        }
        if (drush_get_option('resolve-dependencies') || drush_confirm(dt("Would you like to download them?"))) {
          $download = $found;
        }
      }
    }

    if (!empty($download)) {
      // Disable DRUSH_AFFIRMATIVE context temporarily.
      $drush_affirmative = drush_get_context('DRUSH_AFFIRMATIVE');
      drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
      // Invoke a new process to download dependencies.
      $result = drush_invoke_process('@self', 'pm-download', $download, array(), array('interactive' => TRUE));
      // Restore DRUSH_AFFIRMATIVE context.
      drush_set_context('DRUSH_AFFIRMATIVE', $drush_affirmative);
      // Refresh module cache after downloading the new modules.
      if (drush_drupal_major_version() >= 8) {
        \Drush\Drupal\ExtensionDiscovery::reset();
        system_list_reset();
      }
      $extension_info = drush_get_extensions();
      $last_download = $download;
      $recheck = TRUE;
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
        $dependencies_list = array_keys($info['dependencies']);
        $assoc = array_combine($dependencies_list, $dependencies_list);
        $all_dependencies = array_merge($all_dependencies, $assoc);
      }
    }
    if (!$dependencies_ok) {
      return FALSE;
    }
    $modules = array_diff(array_merge($modules, $all_dependencies), drush_module_list());
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
    $searchpath[] = drush_extension_get_path($extension_info[$name]);
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
    return drush_log(dt('There were no extensions that could be enabled.'), LogLevel::OK);
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
    drush_include_engine('drupal', 'environment');
    drush_module_enable($modules);
  }

  // Inform the user of final status.
  $result_extensions = drush_get_named_extensions_list($extensions);
  $problem_extensions = array();
  $role = drush_role_get_class();
  foreach ($result_extensions as $name => $extension) {
    if ($extension->status) {
      drush_log(dt('!extension was enabled successfully.', array('!extension' => $name)), LogLevel::OK);
      $perms = $role->getModulePerms($name);
      if (!empty($perms)) {
        drush_print(dt('!extension defines the following permissions: !perms', array('!extension' => $name, '!perms' => implode(', ', $perms))));
      }
    }
    else {
      $problem_extensions[] = $name;
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
  drush_include_engine('drupal', 'pm');
  _drush_pm_disable($args);
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
 */
function drush_pm_uninstall() {
  $args = pm_parse_arguments(func_get_args());
  drush_include_engine('drupal', 'pm');
  _drush_pm_uninstall($args);
}

/**
 * Command callback. Show available releases for given project(s).
 */
function drush_pm_releases() {
  $release_info = drush_get_engine('release_info');

  // Obtain requests.
  $requests = pm_parse_arguments(func_get_args(), FALSE);
  if (!$requests) {
    $requests = array('drupal');
  }

  // Get installed projects.
  if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
    $projects = drush_get_projects();
  }
  else {
    $projects = array();
  }

  // Select the filter to apply based on cli options.
  if (drush_get_option('dev', FALSE)) {
    $filter = 'dev';
  }
  elseif (drush_get_option('all', FALSE)) {
    $filter = 'all';
  }
  else {
    $filter = '';
  }

  $status_url = drush_get_option('source');

  $output = array();
  foreach ($requests as $request) {
    $request = pm_parse_request($request, $status_url, $projects);
    $project_name = $request['name'];
    $project_release_info = $release_info->get($request);
    if ($project_release_info) {
      $version = isset($projects[$project_name]) ? $projects[$project_name]['version'] : NULL;
      $releases = $project_release_info->filterReleases($filter, $version);
      foreach ($releases as $key => $release) {
        $output["${project_name}-${key}"] = array(
          'project' => $project_name,
          'version' => $release['version'],
          'date' => gmdate('Y-M-d', $release['date']),
          'status' => implode(', ', $release['release_status']),
        ) + $release;
      }
    }
  }
  if (empty($output)) {
    return drush_log(dt('No valid projects given.'), LogLevel::OK);
  }

  return $output;
}

/**
 * Command callback. Show release notes for given project(s).
 */
function drush_pm_releasenotes() {
  $release_info = drush_get_engine('release_info');

  // Obtain requests.
  if (!$requests = pm_parse_arguments(func_get_args(), FALSE)) {
    $requests = array('drupal');
  }

  // Get installed projects.
  if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_FULL) {
    $projects = drush_get_projects();
  }
  else {
    $projects = array();
  }

  $status_url = drush_get_option('source');

  $output = '';
  foreach($requests as $request) {
    $request = pm_parse_request($request, $status_url, $projects);
    $project_release_info = $release_info->get($request);
    if ($project_release_info) {
      $version = empty($request['version']) ? NULL : $request['version'];
      $output .= $project_release_info->getReleaseNotes($version);
    }
  }
  return $output;
}

/**
 * Command callback. Refresh update status information.
 */
function drush_pm_refresh() {
  $update_status = drush_get_engine('update_status');
  drush_print(dt("Refreshing update status information ..."));
  $update_status->refresh();
  drush_print(dt("Done."));
}

/**
 * Command callback. Execute pm-update.
 */
function drush_pm_update() {
  // Call pm-updatecode.  updatedb will be called in the post-update process.
  $args = pm_parse_arguments(func_get_args(), FALSE);
  drush_set_option('check-updatedb', FALSE);
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
  if (drush_get_option('check-updatedb', TRUE)) {
    if (drush_get_context('DRUSH_PM_UPDATED', FALSE)) {
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
  foreach (drush_module_list() as $module) {
    $updates = drupal_get_schema_versions($module);
    if ($updates !== FALSE) {
      $default = drupal_get_installed_schema_version($module);
      if (max($updates) > $default) {
        drush_log(dt("You have pending database updates. Run `drush updatedb` or visit update.php in your browser."), LogLevel::WARNING);
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
 * Decompound a version string and returns major, minor, patch and extra parts.
 *
 * @see _pm_parse_version_compound()
 * @see pm_parse_version()
 *
 * @param string $version
 *    A version string like X.Y-Z, X.Y.Z-W or a subset.
 *
 * @return array
 *   Array with major, patch and extra keys.
 */
function _pm_parse_version_decompound($version) {
  $pattern = '/^(\d+)(?:.(\d+))?(?:\.(x|\d+))?(?:-([a-z0-9\.-]*))?(?:\+(\d+)-dev)?$/';

  $matches = array();
  preg_match($pattern, $version, $matches);

  $parts = array(
    'major'  => '',
    'minor'  => '',
    'patch'  => '',
    'extra'  => '',
    'offset' => '',
  );
  if (isset($matches[1])) {
    $parts['major'] = $matches[1];
    if (isset($matches[2])) {
      if (isset($matches[3]) && $matches[3] != '') {
        $parts['minor'] = $matches[2];
        $parts['patch'] = $matches[3];
      }
      else {
        $parts['patch'] = $matches[2];
      }
    }
    if (!empty($matches[4])) {
      $parts['extra'] = $matches[4];
    }
    if (!empty($matches[5])) {
      $parts['offset'] = $matches[5];
    }
  }

  return $parts;
}

/**
 * Build a version string from an array of major, minor and extra parts.
 *
 * @see _pm_parse_version_decompound()
 * @see pm_parse_version()
 *
 * @param array $parts
 *    Array of parts.
 *
 * @return string
 *   A Version string.
 */
function _pm_parse_version_compound($parts) {
  $project_version = '';
  if ($parts['patch'] != '') {
    $project_version = $parts['major'];
    if ($parts['minor'] != '') {
      $project_version = $project_version . '.' . $parts['minor'];
    }
    if ($parts['patch'] == 'x') {
      $project_version = $project_version . '.x-dev';
    }
    else {
      $project_version = $project_version . '.' . $parts['patch'];
      if ($parts['extra'] != '') {
        $project_version = $project_version . '-' . $parts['extra'];
      }
    }
    if ($parts['offset'] != '') {
      $project_version = $project_version . '+' . $parts['offset'] . '-dev';
    }
  }

  return $project_version;
}

/**
 * Parses a version string and returns its components.
 *
 * It parses both core and contrib version strings.
 *
 * Core (semantic versioning):
 *   - 8.0.0-beta3+252-dev
 *   - 8.0.0-beta2
 *   - 8.0.x-dev
 *   - 8.1.x
 *   - 8.0.1
 *   - 8
 *
 * Core (classic drupal scheme):
 *   - 7.x-dev
 *   - 7.x
 *   - 7.33
 *   - 7.34+3-dev
 *   - 7
 *
 * Contrib:
 *   - 7.x-1.0-beta1+30-dev
 *   - 7.x-1.0-beta1
 *   - 7.x-1.0+30-dev
 *   - 7.x-1.0
 *   - 1.0-beta1
 *   - 1.0
 *   - 7.x-1.x
 *   - 7.x-1.x-dev
 *   - 1.x
 *
 * TODO: Currently does not support specific version releases of
 *       contrib modules that use SEMVER release versions, e.g. devel-4.0.0.
 *
 * @see pm_parse_request()
 *
 * @param string $version
 *   A core or project version string.
 *
 * @param bool $is_core
 *   Whether this is a core version or a project version.
 *
 * @return array
 *   Version string in parts.
 *   Example for a contrib version (ex: 7.x-3.2-beta1):
 *     - version         : Fully qualified version string.
 *     - drupal_version  : Core compatibility version (ex: 7.x).
 *     - version_major   : Major version (ex: 3).
 *     - version_minor   : Minor version. Not applicable. Always empty.
 *     - version_patch   : Patch version (ex: 2).
 *     - version_extra   : Extra version (ex: beta1).
 *     - project_version : Project specific part of the version (ex: 3.2-beta1).
 *
 *   Example for a core version (ex: 8.1.2-beta2 or 7.0-beta2):
 *     - version         : Fully qualified version string.
 *     - drupal_version  : Core compatibility version (ex: 8.x).
 *     - version_major   : Major version (ex: 8).
 *     - version_minor   : Minor version (ex: 1). Empty if not a semver.
 *     - version_patch   : Patch version (ex: 2).
 *     - version_extra   : Extra version (ex: beta2).
 *     - project_version : Same as 'version'.
 */
function pm_parse_version($version, $is_core = FALSE) {
  $core_parts = _pm_parse_version_decompound($version);

  // If no major version, we have no version at all. Pick a default.
  $drupal_version_default = drush_drupal_major_version();
  // Make 9 act like 8, because release_xml is weird.
  if ($drupal_version_default == 9) {
    $drupal_version_default = 8;
  }
  if ($core_parts['major'] == '') {
    $core_parts['major'] = ($drupal_version_default) ? $drupal_version_default : drush_get_option('default-major', 8);
  }

  if ($is_core) {
    $project_version = _pm_parse_version_compound($core_parts);
    $version_parts = array(
      'version' => $project_version,
      'drupal_version'  => $core_parts['major'] . '.x',
      'project_version' => $project_version,
      'version_major'   => $core_parts['major'],
      'version_minor'   => $core_parts['minor'],
      'version_patch'   => ($core_parts['patch'] == 'x') ? '' : $core_parts['patch'],
      'version_extra'   => ($core_parts['patch'] == 'x') ? 'dev' : $core_parts['extra'],
      'version_offset'  => $core_parts['offset'],
    );
  }
  else {
    // If something as 7.x-1.0-beta1, the project specific version is
    // in $version['extra'] and we need to parse it.
    if (strpbrk($core_parts['extra'], '.-')) {
      $nocore_parts = _pm_parse_version_decompound($core_parts['extra']);
      $nocore_parts['offset'] = $core_parts['offset'];
      $project_version = _pm_parse_version_compound($nocore_parts);
      $version_parts = array(
        'version' => $core_parts['major'] . '.x-' . $project_version,
        'drupal_version'  => $core_parts['major'] . '.x',
        'project_version' => $project_version,
        'version_major'   => $nocore_parts['major'],
        'version_minor'   => $core_parts['minor'],
        'version_patch'   => ($nocore_parts['patch'] == 'x') ? '' : $nocore_parts['patch'],
        'version_extra'   => ($nocore_parts['patch'] == 'x') ? 'dev' : $nocore_parts['extra'],
        'version_offset'  => $core_parts['offset'],
      );
    }
    // At this point we have half a version and must decide if this is a drupal major or a project.
    else {
      // If working on a bootstrapped site, core_parts has the project version.
      if ($drupal_version_default) {
        $project_version = _pm_parse_version_compound($core_parts);
        $version = ($project_version) ? $drupal_version_default . '.x-' . $project_version : '';
        $version_parts = array(
          'version'         => $version,
          'drupal_version'  => $drupal_version_default . '.x',
          'project_version' => $project_version,
          'version_major'   => $core_parts['major'],
          'version_minor'   => $core_parts['minor'],
          'version_patch'   => ($core_parts['patch'] == 'x') ? '' : $core_parts['patch'],
          'version_extra'   => ($core_parts['patch'] == 'x') ? 'dev' : $core_parts['extra'],
          'version_offset'  => $core_parts['offset'],
        );
      }
      // Not working on a bootstrapped site, core_parts is core version.
      else {
        $version_parts = array(
          'version' => '',
          'drupal_version'  => $core_parts['major'] . '.x',
          'project_version' => '',
          'version_major'   => '',
          'version_minor'   => '',
          'version_patch'   => '',
          'version_extra'   => '',
          'version_offset'  => '',
        );
      }
    }
  }

  return $version_parts;
}

/**
 * Parse out the project name and version and return as a structured array.
 *
 * @see pm_parse_version()
 *
 * @param string $request_string
 *   Project name with optional version. Examples: 'ctools-7.x-1.0-beta1'
 *
 * @return array
 *   Array with all parts of the request info.
 */
function pm_parse_request($request_string, $status_url = NULL, &$projects = array()) {
  // Split $request_string in project name and version. Note that hyphens (-)
  // are permitted in project names (ex: field-conditional-state).
  // We use a regex to split the string. The pattern used matches a string
  // starting with hyphen, followed by one or more numbers, any of the valid
  // symbols in version strings (.x-) and a catchall for the rest of the
  // version string.
  $parts = preg_split('/-(?:([\d+\.x].*))?$/', $request_string, NULL, PREG_SPLIT_DELIM_CAPTURE);

  if (count($parts) == 1) {
    // No version in the request string.
    $project = $request_string;
    $version = '';
  }
  else {
    $project = $parts[0];
    $version = $parts[1];
  }

  $is_core = ($project == 'drupal');
  $request = array(
    'name' => $project,
  ) + pm_parse_version($version, $is_core);

  // Set the status url if provided or available in project's info file.
  if ($status_url) {
    $request['status url'] = $status_url;
  }
  elseif (!empty($projects[$project]['status url'])) {
    $request['status url'] = $projects[$project]['status url'];
  }

  return $request;
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
        'cache' => 'Cache release XML and tarballs or git clones. Git clones use git\'s --reference option. Defaults to 1 for downloads, and 0 for git.',
      ),
    ),
    'release_info' => array(
      'add-options-to-command' => TRUE,
    ),
    'update_status' => array(
      'option' => 'update-backend',
      'description' => 'Determine how to fetch update status information.',
      'default' => 'drush',
      'add-options-to-command' => TRUE,
      'options' => array(
        'update-backend' => 'Backend to obtain available updates.',
        'check-disabled' => 'Check for updates of disabled modules and themes.',
        'security-only'  => 'Only update modules that have security updates available.',
      ),
      'combine-help' => TRUE,
    ),
    'version_control' => array(
      'option' => 'version-control',
      'default' => 'backup',
      'description' => 'Integrate with version control systems.',
    ),
  );
}

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * Package handler engine is used by pm-download and
 * pm-updatecode commands to determine how to download/checkout
 * new projects and acquire updates to projects.
 */
function pm_drush_engine_package_handler() {
  return array(
    'wget' => array(
      'description' => 'Download project packages using wget or curl.',
      'options' => array(
        'no-md5' => 'Skip md5 validation of downloads.',
      ),
    ),
    'git_drupalorg' => array(
      'description' => 'Use git.drupalcode.org to checkout and update projects.',
      'options' => array(
        'gitusername' => 'Your git username as shown on user/[uid]/edit/git. Typically, this is set this in drushrc.php. Omitting this prevents users from pushing changes back to git.drupalcode.org.',
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
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * Release info engine is used by several pm commands to obtain
 * releases info from Drupal's update service or external sources.
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
      'class' => 'Drush\UpdateService\ReleaseInfo',
    ),
  );
}

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * Update status engine is used to check available updates for
 * the projects in a Drupal site.
 */
function pm_drush_engine_update_status() {
  return array(
    'drupal' => array(
      'description' => 'Check available updates with update.module.',
      'drupal dependencies' => array('update'),
      'class' => 'Drush\UpdateService\StatusInfoDrupal',
    ),
    'drush' => array(
      'description' => 'Check available updates without update.module.',
      'class' => 'Drush\UpdateService\StatusInfoDrush',
    ),
  );
}

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * Integration with VCS in order to easily commit your changes to projects.
 */
function pm_drush_engine_version_control() {
  return array(
    'backup' => array(
      'description' => 'Backup all project files before updates.',
      'options' => array(
        'no-backup' => 'Do not perform backups. WARNING: Will result in non-core files/dirs being deleted (e.g. .git)',
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
 * specified order, and returns the one that appears to be appropriate for a
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
        drush_log(dt('Verifying signature for !vcs version control engine.', array('!vcs' => $version_control)), LogLevel::DEBUG);
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

  $instance = drush_include_engine('version_control', $engine);
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
    drush_log(dt('Ignoring --lock with no value.'), LogLevel::WARNING);
  }
  if ($projects_to_unlock == array('1')) {
    $projects_to_unlock = array();
    drush_log(dt('Ignoring --unlock with no value.'), LogLevel::WARNING);
  }

  // Log if we are going to lock or unlock anything
  if (!empty($projects_to_unlock)) {
    drush_log(dt('Unlocking !projects', array('!projects' => implode(',', $projects_to_unlock))), LogLevel::OK);
  }
  if (!empty($projects_to_lock)) {
    drush_log(dt('Locking !projects', array('!projects' => implode(',', $projects_to_lock))), LogLevel::OK);
  }

  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  foreach ($projects as $name => $project) {
    $message = NULL;
    if (isset($project['path'])) {
      if ($name == 'drupal') {
        $lockfile = $drupal_root . '/.drush-lock-update';
      }
      else {
        $lockfile = $drupal_root . '/' . $project['path'] . '/.drush-lock-update';
      }

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
    }

    // If there is a message set, then mark the project as locked.
    if (isset($message)) {
      $projects[$name]['locked'] = !empty($message) ? $message : "Locked.";
      $locked_result[$name] = $project;
    }
  }

  return $locked_result;
}

/**
 * Returns the path to the extensions cache file.
 */
function _drush_pm_extension_cache_file() {
  return drush_get_context('DRUSH_PER_USER_CONFIGURATION') . "/drush-extension-cache.inc";
}

/**
 * Load the extensions cache.
 */
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

/**
 * Lookup an extension in the extensions cache.
 */
function drush_pm_lookup_extension_in_cache($extension) {
  $result = NULL;
  $extension_cache = _drush_pm_get_extension_cache();
  if (!empty($extension_cache) && array_key_exists($extension, $extension_cache)) {
    $result = $extension_cache[$extension];
  }
  return $result;
}

/**
 * Persists extensions cache.
 *
 * #TODO# not implemented.
 */
function drush_pm_put_extension_cache($extension_cache) {
}

/**
 * Store extensions founds within a project in extensions cache.
 */
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
 * Find .info.yml files in the project path and identify modules, themes and
 * profiles. It handles two kind of projects: drupal core/profiles and
 * modules/themes.
 * It does nothing with theme engine projects.
 */
function drush_pm_extensions_in_project($project) {
  // Mask for drush_scan_directory, to match .info.yml files.
  $mask = $project['drupal_version'][0] >= 8 ? '/(.*)\.info\.yml$/' : '/(.*)\.info$/';

  // Mask for drush_scan_directory, to avoid tests directories.
  $nomask = array('.', '..', 'CVS', 'tests');

  // Drupal core and profiles can contain modules, themes and profiles.
  if (in_array($project['project_type'], array('core', 'profile'))) {
    $found = array('profile' => array(), 'theme' => array(), 'module' => array());
    // Find all of the .info files
    foreach (drush_scan_directory($project['full_project_path'], $mask, $nomask) as $filename => $info) {
      // Extract extension name from filename.
      $matches = array();
      preg_match($mask, $info->basename, $matches);
      $name = $matches[1];

      // Find the project type corresponding the .info file.
     // (Only drupal >=7.x has .info for .profile)
      $base = dirname($filename) . '/' . $name;
      if (is_file($base . '.module')) {
        $found['module'][] = $name;
      }
      else if (is_file($base . '.profile')) {
        $found['profile'][] = $name;
      }
      else {
        $found['theme'][] = $name;
      }
    }
    // Special case: find profiles for drupal < 7.x (no .info)
    if ($project['drupal_version'][0] < 7) {
      foreach (drush_find_profiles($project['full_project_path']) as $filename => $info) {
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
    drush_log(dt($msg, $args), LogLevel::SUCCESS);
    drush_print_pipe(call_user_func_array('array_merge', array_values($found)));
  }
  // Modules and themes can only contain other extensions of the same type.
  elseif (in_array($project['project_type'], array('module', 'theme'))) {
    $found = array();
    foreach (drush_scan_directory($project['full_project_path'], $mask, $nomask) as $filename => $info) {
      // Extract extension name from filename.
      $matches = array();
      preg_match($mask, $info->basename, $matches);
      $found[] = $matches[1];
    }
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
 * @param int $datestamp
 *   The datestamp of the last commit.
 *
 * @return boolean
 *   TRUE on success, FALSE on any failures appending data to .info files.
 */
function drush_pm_inject_info_file_metadata($project_dir, $project_name, $version, $datestamp) {
  // `drush_drupal_major_version()` cannot be used here because this may be running
  // outside of a Drupal context.
  $yaml_format = substr($version, 0, 1) >= 8;
  $pattern = preg_quote($yaml_format ? '.info.yml' : '.info');
  $info_files = drush_scan_directory($project_dir, '/.*' . $pattern . '$/');
  if (!empty($info_files)) {
    // Construct the string of metadata to append to all the .info files.
    if ($yaml_format) {
      $info = _drush_pm_generate_info_yaml_metadata($version, $project_name, $datestamp);
    }
    else {
      $info = _drush_pm_generate_info_ini_metadata($version, $project_name, $datestamp);
    }
    foreach ($info_files as $info_file) {
      if (!drush_file_append_data($info_file->filename, $info)) {
        return FALSE;
      }
    }
  }
  return TRUE;
}

/**
 * Generate version information for `.info` files in ini format.
 *
 * Taken with some modifications from:
 * http://drupalcode.org/project/drupalorg.git/blob/refs/heads/6.x-3.x:/drupalorg_project/plugins/release_packager/DrupalorgProjectPackageRelease.class.php#l192
 */
function _drush_pm_generate_info_ini_metadata($version, $project_name, $datestamp) {
  $matches = array();
  $extra = '';
  if (preg_match('/^((\d+)\.x)-.*/', $version, $matches) && $matches[2] >= 6) {
    $extra .= "\ncore = \"$matches[1]\"";
  }
  if (!drush_get_option('no-gitprojectinfo', FALSE)) {
    $extra = "\nproject = \"$project_name\"";
  }
  $date = date('Y-m-d', $datestamp);
  $info = <<<METADATA

; Information added by drush on {$date}
version = "{$version}"{$extra}
datestamp = "{$datestamp}"
METADATA;
  return $info;
}

/**
 * Generate version information for `.info` files in YAML format.
 */
function _drush_pm_generate_info_yaml_metadata($version, $project_name, $datestamp) {
  $matches = array();
  $extra = '';
  if (preg_match('/^((\d+)\.x)-.*/', $version, $matches) && $matches[2] >= 6) {
    $extra .= "\ncore: '$matches[1]'";
  }
  if (!drush_get_option('no-gitprojectinfo', FALSE)) {
    $extra = "\nproject: '$project_name'";
  }
  $date = date('Y-m-d', $datestamp);
  $info = <<<METADATA

# Information added by drush on {$date}
version: '{$version}'{$extra}
datestamp: {$datestamp}
METADATA;
  return $info;
}
