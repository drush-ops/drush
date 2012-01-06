<?php

define('MAKE_DEFAULT_L10N_SERVER', 'http://localize.drupal.org/l10n_server.xml');

/**
 * Make refuses to build makefiles whose api version is mismatched
 * with make command.
 */
define('MAKE_API', 2);

include_once 'make.utilities.inc';
include_once 'make.download.inc';
include_once 'make.project.inc';

/**
 * Implementation of hook_drush_command().
 */
function make_drush_command() {
  $items['make'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Turns a makefile into a working Drupal codebase.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to use for this build.',
      'build path' => 'The path at which to build the makefile.',
    ),
    'examples' => array(
      'drush make example.make example' => 'Build the example.make makefile in the example directory.',
      'drush make --no-core --contrib-destination=. installprofile.make' => 'Build an installation profile within an existing Drupal site',
    ),
    'options' => array(
      'version' => 'Print the version and exit.',
      'contrib-destination' => 'Specify a path under which modules and themes should be placed. Defaults to sites/all.',
      'force-complete' => 'Force a complete build even if errors occur.',
      'ignore-checksums' => 'Ignore md5 checksums for downloads.',
      'md5' => 'Output an md5 hash of the current build after completion. Use --md5=print to print to stdout.',
      'make-update-default-url' => 'The default location to load the XML update information from.',
      'no-cache' => 'Do not use the pm-download caching (defaults to using this).',
      'no-clean' => 'Leave temporary build directories in place instead of cleaning up after completion.',
      'no-core' => 'Do not require a Drupal core project to be specified.',
      'no-patch-txt' => 'Do not write a PATCHES.txt file in the directory of each patched project.',
      'prepare-install' => 'Prepare the built site for installation. Generate a properly permissioned settings.php and files directory.',
      'tar' => 'Generate a tar archive of the build. The output filename will be [build path].tar.gz.',
      'test' => 'Run a temporary test build and clean up.',
      'translations' => 'Retrieve translations for the specified comma-separated list of language(s) if available for all projects.',
      'working-copy' => 'Preserves VCS directories, like .git, for projects downloaded using such methods.',
      'download-mechanism' => 'How to download files. Should be autodetected, but this is an override if it doesn\'t work. Options are "curl" and "make" (a native download method).',
    ),
    'topics' => array('docs-make', 'docs-make-example'),
  );

  $items['make-generate'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'description' => 'Generate a makefile from the current Drupal site.',
    'examples' => array(
      'drush generate-makefile example.make' => 'Generate a makefile with ALL projects versioned (should a project have a known version number)',
      'drush generate-makefile example.make --exclude-versions' => 'Generate a makefile with NO projects versioned',
      'drush generate-makefile example.make --exclude-versions=drupal,views,cck' => 'Generate a makefile with ALL projects versioned EXCEPT core, Views and CCK',
      'drush generate-makefile example.make --include-versions=admin_menu,og,ctools (--exclude-versions)' => 'Generate a makefile with NO projects versioned EXCEPT Admin Menu, OG and CTools.',
    ),
    'options' => array(
      'exclude-versions' => 'Exclude all version numbers (default is include all version numbers) or optionally specify a list of projects to exclude from versioning',
      'include-versions' => 'Include a specific list of projects, while all other projects remain unversioned in the makefile (so implies --exclude-versions)',
    ),
    'aliases' => array('generate-makefile'),
  );

  // Add docs topic.
  $make_dir = dirname(__FILE__);
  $items['docs-make'] = array(
    'description' => 'Drush Make overview with examples',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($make_dir . '/README.txt'),
  );
  $items['docs-make-example'] = array(
    'description' => 'Drush Make example makefile',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($make_dir . '/EXAMPLE.make'),
  );
  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function make_drush_help($section) {
  switch ($section) {
    case 'drush:make':
      return 'Turns a makefile into a Drupal codebase. For a full description of options and makefile syntax, see the README.txt included with drush make.';
    case 'drush:make-generate':
      return 'Generate a makefile from the current Drupal site, specifying project version numbers unless not known or otherwise specified. Unversioned projects will be interpreted later by drush make as "most recent stable release"';
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Strong glob of files to complete on.
 */
function make_make_complete() {
  return array(
    'files' => array(
      'make' => array(
        'pattern' => '*.make',
      ),
    ),
  );
}

/**
 * Drush callback; make based on the makefile.
 */
function drush_make($makefile = NULL, $build_path = NULL) {
  // If --version option is supplied, print it and bail.
  if (drush_get_option('version', FALSE)) {
    $version = make_version();
    drush_print($version);
    return $version;
  }

  if (!($build_path = make_build_path($build_path))) {
    return FALSE;
  }

  $info = make_parse_info_file($makefile);
  if ($info === FALSE || ($info = make_validate_info_file($info)) === FALSE) {
    return FALSE;
  }

  if (make_projects(FALSE, drush_get_option('contrib-destination', 'sites/all'), $info, $build_path)) {
    make_libraries(drush_get_option('contrib-destination', 'sites/all'), $info, $build_path);

    if (drush_get_option('prepare-install')) {
      make_prepare_install($build_path);
    }
  }
}

/**
 * Implementation of drush_hook_post_COMMAND() for the make command.
 */
function drush_make_post_make($makefile = NULL, $build_path = NULL) {
  if (!($build_path = make_build_path($build_path))) {
    return;
  }

  if ($option = drush_get_option('md5')) {
    $md5 = make_md5();
    if ($option === 'print') {
      drush_print($md5);
    }
    else {
      drush_log(dt('Build hash: %md5', array('%md5' => $md5)), 'ok');
    }
  }

  // Only take final build steps if not in testing mode.
  if (!drush_get_option('test')) {
    if (drush_get_option('tar')) {
      make_tar($build_path);
    }
    else {
      make_move_build($build_path);
    }
  }

  make_clean_tmp();
}

function make_projects($recursion, $contrib_destination, $info, $build_path) {
  $release_info = drush_get_option('release-info', 'updatexml');
  drush_include_engine('release_info', $release_info);

  $projects = array();
  if (empty($info['projects'])) {
    if (drush_get_option('no-core') || $recursion) {
      return TRUE;
    }
    else {
      drush_set_error(dt('No core project specified.'));
      return FALSE;
    }
  }
  $ignore_checksums = drush_get_option('ignore-checksums');
  foreach ($info['projects'] as $key => $project) {
    $md5 = '';
    if (isset($project['md5'])) {
      $md5 = $project['md5'];
    }
    // Merge the known data onto the project info.
    $project += array(
      'name'                => $key,
      'core'                => $info['core'],
      'build_path'          => $build_path,
      'contrib_destination' => $contrib_destination,
      'version'             => '',
      'location'            => drush_get_option('make-update-default-url', RELEASE_INFO_DEFAULT_URL),
      'subdir'              => '',
      'directory_name'      => '',
    );
    // If download components are specified, but not the download
    // type, default to git. Additionally, if the 'revision' parameter is
    // passed at the top level, this is short-hand for download revision.
    if (isset($project['revision']) && !isset($project['download'])) {
      $project['download']['revision'] = $project['revision'];
    }
    if (isset($project['download']) && !isset($project['download']['type'])) {
      $project['download']['type'] = 'git';
    }
    if (!isset($project['l10n_url']) && ($project['location'] == RELEASE_INFO_DEFAULT_URL)) {
      $project['l10n_url'] = MAKE_DEFAULT_L10N_SERVER;
    }

    // For convenience: define $request to be compatible with release_info engine.
    // TODO: refactor to enforce 'make' to internally work with release_info keys.
    $request = make_prepare_request($project);

    if ($project['location'] != RELEASE_INFO_DEFAULT_URL && !isset($project['type'])) {
      // Set the cache option based on our '--no-cache' option.
      $cache_before = drush_get_option('cache');
      if (!drush_get_option('no-cache', FALSE)) {
        drush_set_option('cache', TRUE);
      }
      $project_type = release_info_check_project($request, 'core');
      // Restore the previous '--cache' option value.
      drush_set_option('cache', $cache_before);
      $project['download_type'] = ($project_type ? 'core' : 'contrib');
    }
    elseif (!empty($project['type'])) {
      $project['download_type'] = ($project['type'] == 'core' ? 'core' : 'contrib');
    }
    else {
      $project['download_type'] = ($project['name'] == 'drupal' ? 'core' : 'contrib');
    }
    $projects[$project['download_type']][$project['name']] = $project;
  }

  $cores = !empty($projects['core']) ? count($projects['core']) : 0;

  if (drush_get_option('no-core')) {
    unset($projects['core']);
  }
  elseif ($cores == 0 && !$recursion) {
    drush_set_error(dt('No core project specified.'));
    return FALSE;
  }
  elseif ($cores == 1 && $recursion) {
    unset($projects['core']);
  }
  elseif ($cores > 1) {
    drush_set_error(dt('More than one core project specified.'));
    return FALSE;
  }

  foreach ($projects as $type => $type_projects) {
    foreach ($type_projects as $project) {
      if ($project['location'] == RELEASE_INFO_DEFAULT_URL && (!isset($project['type']) || !isset($project['download']))) {
        // For convenience: define $request to be compatible with release_info engine.
        // TODO: refactor to enforce 'make' to internally work with release_info keys.
        $request = make_prepare_request($project, $type);
        // Set the cache option based on our '--no-cache' option.
        $cache_before = drush_get_option('cache');
        if (!drush_get_option('no-cache', FALSE)) {
          drush_set_option('cache', TRUE);
        }
        $release = release_info_fetch($request);
        // Restore the previous '--cache' option value.
        drush_set_option('cache', $cache_before);
        // Translate release_info key for project_type to drush make.
        $project['type'] = $request['project_type'];
        if (!isset($project['download'])) {
          $project['download'] = array(
            'type' => 'pm',
            'release' => $release,
          );
        }
      }
      if (!empty($md5)) {
        $project['download']['md5'] = $md5;
      }
      if ($ignore_checksums) {
        unset($project['download']['md5']);
      }
      if ($instance = DrushMakeProject::getInstance($project['type'], $project)) {
        $projects[($project['type'] == 'core' ? 'core' : 'contrib')][$project['name']] = $instance;
      }
      else {
        make_error('PROJECT-TYPE', dt('Non-existent project type %type on project %project', array('%type' => $project['type'], '%project' => $project['name'])));
      }
    }
  }

  if (isset($projects['core'])) {
    foreach ($projects['core'] as $project) {
      $project->make();
    }
  }

  if (isset($projects['contrib'])) {
    foreach ($projects['contrib'] as $project) {
      $project->make();
    }
  }
  return TRUE;
}

function make_libraries($contrib_destination, $info, $build_path) {
  if (empty($info['libraries'])) {
    return;
  }
  $ignore_checksums = drush_get_option('ignore-checksums');
  foreach ($info['libraries'] as $key => $library) {
    if (!is_string($key) || !is_array($library)) {
      // TODO Print a prettier message
      continue;
    }
    // Merge the known data onto the library info.
    $library += array(
      'name'                => $key,
      'core'                => $info['core'],
      'build_path'          => $build_path,
      'contrib_destination' => $contrib_destination,
      'subdir'              => '',
      'directory_name'      => $key,
    );
    if ($ignore_checksums) {
      unset($library['download']['md5']);
    }
    $class = DrushMakeProject::getInstance('library', $library);
    $class->make();
  }
}

function make_build_path($build_path) {
  static $saved_path;
  if (isset($saved_path)) {
    return $saved_path;
  }

  // Determine the base of the build.
  if (drush_get_option('tar')) {
    $build_path = dirname($build_path) . '/' . basename($build_path, '.tar.gz') . '.tar.gz';
  }
  elseif (isset($build_path) && (!empty($build_path) || $build_path == '.')) {
    $build_path = rtrim($build_path, '/');
  }
  // Allow tests to run without a specified base path.
  elseif (drush_get_option('test') || drush_confirm(dt("Make new site in the current directory?"))) {
    $build_path = '.';
  }
  else {
    return drush_user_abort(dt('Build aborted.'));
  }
  if ($build_path != '.' && file_exists($build_path)) {
    return drush_set_error(dt('Base path %path already exists', array('%path' => $build_path)));
  }
  $saved_path = $build_path;
  return $build_path;
}

function make_move_build($build_path) {
  $tmp_path = make_tmp();
  $ret = TRUE;
  if ($build_path == '.') {
    drush_shell_exec('ls -A %s', $tmp_path . '/__build__');
    $info = drush_shell_exec_output();
    foreach ($info as $file) {
      $ret = $ret && drush_shell_exec("cp -Rf %s %s", $tmp_path . '/__build__/' . $file, $build_path);
    }
  }
  else {
    drush_mkdir(dirname($build_path));
    drush_shell_exec("mv %s %s", $tmp_path . '/__build__', $tmp_path . '/' . basename($build_path));
    drush_shell_exec("cp -Rf %s %s", $tmp_path . '/' . basename($build_path), dirname($build_path));
  }
  if (!$ret) {
    drush_set_error(dt("Cannot move build into place"));
  }
  return $ret;
}

/**
 * Create a request array for use with release_info_fetch().
 *
 * @param $project
 *   Project array.
 * @param $type
 *   'contrib' or 'core'.
 */
function make_prepare_request($project, $type = 'contrib') {
  $request = array(
    'name' => $project['name'],
    'drupal_version' => $project['core'],
    'status url' => $project['location'],
  );
  if ($project['version'] != '') {
    $request['project_version'] = $project['version'];
    $request['version'] = $type == 'core' ? $project['version'] : $project['core'] . '-' . $project['version'];
  }
  return $request;
}

function make_version() {
  return '2.0-dev';
}
