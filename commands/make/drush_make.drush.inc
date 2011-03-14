<?php

define('DRUSH_MAKE_UPDATE_DEFAULT_URL', 'http://updates.drupal.org/release-history');
define('DRUSH_MAKE_VERSION_BEST', 'best');
define('DRUSH_MAKE_API', 2);

include_once 'drush_make.utilities.inc';
include_once 'drush_make.download.inc';
include_once 'drush_make.project.inc';

/**
 * Implement EXTENSION_drush_init().
 */
function drush_make_drush_init() {
  // The default URL to use for retrieving update XML files.
  drush_set_default('drush-make-update-default-url', DRUSH_MAKE_UPDATE_DEFAULT_URL);
}

/**
 * Implementation of hook_drush_command().
 */
function drush_make_drush_command() {
  $items['make'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Turns a makefile into a working drupal install.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to use for this build.',
      'build path' => 'The path at which to build the makefile.',
    ),
    'examples' => array(
      'drush make example.make example' => 'Build the example.make makefile in the example directory.',
    ),
    'options' => array(
      '--version' => 'Print the version and exit.',
      '--contrib-destination=path' => 'Specify a path under which modules and themes should be placed. Defaults to sites/all.',
      '--force-complete' => 'Force a complete build even if errors occur.',
      '--ignore-checksums' => 'Ignore md5 checksums for downloads.',
      '--md5' => 'Output an md5 hash of the current build after completion.',
      '--no-clean' => 'Leave temporary build directories in place instead of cleaning up after completion.',
      '--no-core' => 'Do not require a Drupal core project to be specified.',
      '--no-patch-txt' => 'Do not write a PATCHES.txt file in the directory of each patched project.',
      '--prepare-install' => 'Prepare the built site for installation. Generate a properly permissioned settings.php and files directory.',
      '--tar' => 'Generate a tar archive of the build. The output filename will be [build path].tar.gz.',
      '--test' => 'Run a temporary test build and clean up.',
      '--translations=languages' => 'Retrieve translations for the specified comma-separated list of language(s) if available for all projects.',
      '--working-copy' => 'Where possible, retrieve a working copy of projects from their respective repositories.',
      '--download-mechanism' => 'How to download files. Should be autodetected, but this is an override if it doesn\'t work. Options are "curl" and "drush_make" (a native download method).',
    ),
  );

  $items['make-test'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Run a drush make test.',
    'arguments' => array(
      'test' => 'Name of the test to run or ALL to run all tests.',
    ),
    'examples' => array(
      'drush make-test svn' => 'Run tests for SVN integration.',
    ),
  );
  $items['make-generate'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'description' => 'Attempts to generate a makefile from the current Drupal install, specifying project version numbers unless not known or otherwise specified. Unversioned projects will be interpreted later by drush make as "most recent stable release"',
    'examples' => array(
      'drush generate-makefile example.make' => 'Generate a makefile with ALL projects versioned (should a project have a known version number)',
      'drush generate-makefile example.make --exclude-versions' => 'Generate a makefile with NO projects versioned',
      'drush generate-makefile example.make --exclude-versions=drupal,views,cck' => 'Generate a makefile with ALL projects versioned EXCEPT core, Views and CCK',
      'drush generate-makefile example.make --include-versions=admin_menu,og,ctools (--exclude-versions)' => 'Generate a makefile with NO projects versioned EXCEPT Admin Menu, OG and CTools.',
    ),
    'options' => array(
      '--exclude-versions(="list")' => 'Exclude all version numbers (default is include all version numbers) or optionally specify a list of projects to exclude from versioning',
      '--include-versions="list"' => 'Include a specific list of projects, while all other projects remain unversioned in the makefile (so implies --exclude-versions)',
    ),
    'aliases' => array('generate-makefile'),
  );
  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function drush_make_drush_help($section) {
  switch ($section) {
    case 'drush:make':
      return dt('Turns a makefile into a working drupal install. For a full description of options and makefile syntax, see the README.txt included with drush make.');
    case 'drush:make-test':
      return dt('Run a drush make test.');
    case 'drush:make-generate':
      return dt('Attempts to generate a makefile from the current drupal install.');
  }
}

function drush_drush_make_make_validate() {
  drush_make_ensure_version();
}

/**
 * Drush callback; make based on the makefile.
 */
function drush_drush_make_make($makefile = NULL, $build_path = NULL) {
  if (drush_get_option('version', FALSE)) {
    drush_print(drush_make_version());
    return;
  }

  if (!($build_path = drush_make_build_path($build_path))) {
    return;
  }
  $info = drush_make_parse_info_file($makefile);
  if ( $info === FALSE || ($info = drush_make_validate_info_file($info)) === FALSE) {
    return FALSE;
  }

  if (drush_make_projects(FALSE, drush_get_option('contrib-destination', 'sites/all'), $info, $build_path)) {
    drush_make_libraries(drush_get_option('contrib-destination', 'sites/all'), $info, $build_path);

    if (drush_get_option('prepare-install')) {
      drush_make_prepare_install($build_path);
    }
  }
}

/**
 * Implementation of drush_hook_post_COMMAND() for the make command.
 */
function drush_drush_make_post_make($makefile = NULL, $build_path = NULL) {
  if (!($build_path = drush_make_build_path($build_path))) {
    return;
  }

  if (drush_get_option('md5')) {
    drush_make_md5();
  }

  // Only take final build steps if not in testing mode.
  if (!drush_get_option('test')) {
    if (drush_get_option('tar')) {
      drush_make_tar($build_path);
    }
    else {
      drush_make_move_build($build_path);
    }
  }

  drush_make_clean_tmp();
}

function drush_make_projects($recursion, $contrib_destination, $info, $build_path) {
  $projects = array();
  if (empty($info['projects'])) {
    if (drush_get_option('no-core')) {
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
      'version'             => DRUSH_MAKE_VERSION_BEST,
      'location'            => drush_get_option('drush-make-update-default-url'),
      'subdir'              => '',
      'directory_name'      => '',
    );
    if ($project['location'] != 'http://updates.drupal.org/release-history' && !isset($project['type'])) {
      $project = drush_make_updatexml($project);
      $project['download_type'] = ($project['type'] == 'core' ? 'core' : 'contrib');
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
      if ($project['location'] == 'http://updates.drupal.org/release-history' && (!isset($project['type']) || !isset($project['download']))) {
        $project = drush_make_updatexml($project);
      }
      if (!empty($md5)) {
        $project['download']['md5'] = $md5;
      }
      if ($ignore_checksums) {
        unset($project['download']['md5']);
      }
      $class_name = 'DrushMakeProject_' . $project['type'];
      if (class_exists($class_name)) {
        $projects[($project['type'] == 'core' ? 'core' : 'contrib')][$project['name']] = new $class_name($project);
      }
      else {
        drush_make_error('PROJECT-TYPE', dt('Non-existent project type %type on project %project', array('%type' => $project['type'], '%project' => $key)));
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

function drush_make_libraries($contrib_destination, $info, $build_path) {
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
    $class = new DrushMakeProject_Library($library);
    $class->make();
  }
}

function drush_make_updatexml($project) {
  if (isset($project['download']) && isset($project['type'])) {
    return $project;
  }
  if ($filename = _drush_make_download_file($project['location'] . '/' . $project['name'] . '/' . $project['core'])) {
    $release_history = simplexml_load_string(file_get_contents($filename));
    drush_op('unlink', $filename);
  }
  // First, get the release history.
  if (!is_object($release_history) || !$release_history->title) {
    drush_make_error('XML_ERROR', dt("Could not retrieve version information for %project.", array('%project' => $project['name'])));
    return FALSE;
  }
  drush_log(dt('Project information for %project retrieved.', array('%project' => $project['name'])), 'ok');
  $project['release_history'] = $release_history;
  if (!isset($project['type'])) {
    // Determine the project type.
    $term_map = array(
      'Modules' => 'module',
      'Themes' => 'theme',
      'Drupal core' => 'core',
      'Installation profiles' => 'profile',
      'Translations' => 'translation'
    );
    // Iterate through all terms related to this project.
    foreach ($release_history->terms->term as $term) {
      // If we find a term from the term map, add it.
      if (in_array((string) $term->value, array_keys($term_map))) {
        $project['type'] = $term_map[(string)$term->value];
        break;
      }
    }
    if (!isset($project['type'])) {
      drush_make_error('BUILD_ERROR', dt("Unable to determine project type for %project.", array('%project' => $project['name'])));
      return FALSE;
    }
  }
  if (!isset($project['download'])) {
    $project = drush_make_update_xml_download($project);
  }
  return $project;
}

function drush_make_update_xml_download($project) {
  // Make an array of releases.
  foreach ($project['release_history']->releases->release as $release) {
    $version = (string) $release->version_major;
    // there should be version_patch attribute for every stable release
    // so checking whether the attribute exists should be enough
    if (isset($release->version_patch)) {
      $version .= '.' . (string) $release->version_patch;
    }
    // if version_patch attribute does not exist, then it should be a dev release
    // and the version string should be in format MAJOR_VERSION.x-dev
    else {
      $version .= '.x';
    }
    if ($extra_version = (string) $release->version_extra) {
      $version .= '-' . $extra_version;
    }
    $releases[$version] = array(
      'file' => (string) $release->download_link,
      'md5' => (string) $release->mdhash,
      'version' => (string) $release->version,
    );
    foreach (array('major', 'patch', 'extra') as $part) {
      $releases[$version][$part] = (string) $release->{'version_' . $part};
    }
  }

  // Find the best release.
  if ($project['version'] == DRUSH_MAKE_VERSION_BEST) {
    $recommended_major = (string)$project['release_history']->recommended_major;
    if (!$recommended_major && $project['type'] == 'core') {
      $recommended_major = strtok($project['core'], '.');
    }
    $project['version'] = _drush_make_update_xml_best_version($recommended_major, $releases);
  }
  // Find the best release for the specified version.
  else {
    $exploded_version = explode('.', $project['version']);
    if (count($exploded_version) == 1) {
      list($major) = $exploded_version;
      $project['version'] = _drush_make_update_xml_best_version($major, $releases);
    }
    // Otherwise we have a full version.
  }

  $final_version = empty($project['version']) ? '' : $project['version'];

  // Uh oh, couldn't find a valid version.
  if (empty($final_version) || empty($releases[$final_version])) {
    drush_make_error('BUILD_ERROR', dt("Invalid version %version for %project.", array('%version' => $final_version, '%project' => $project['name'])));
    return FALSE;
  }

  $release = $releases[$final_version];
  $project['download'] = array(
    'type' => 'get',
    'url'  => $release['file'],
    'md5'  => $release['md5'],
  );
  return $project;
}

function _drush_make_update_xml_best_version($major, $releases) {
  $best = NULL;
  foreach ($releases as $version => $release_info) {
    if ($release_info['major'] == $major && version_compare($best, $version, '<')) {
      $best = $version;
    }
  }
  return $best;
}

function drush_make_build_path($build_path) {
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
    drush_log(dt('Build aborted.'), 'ok');
    return FALSE;
  }
  if ($build_path != '.' && file_exists($build_path)) {
    drush_set_error(dt('Base path %path already exists', array('%path' => $build_path)));
    return FALSE;
  }
  return $build_path;
}

function drush_make_move_build($build_path) {
  $tmp_path = drush_make_tmp();
  $ret = TRUE;
  if ($build_path == '.') {
    drush_shell_exec('ls -A %s', $tmp_path . '/__build__');
    $info = drush_shell_exec_output();
    foreach ($info as $file) {
      $ret = $ret && drush_shell_exec("cp -Rf %s %s", $tmp_path . '/__build__/' . $file, $build_path);
    }
  }
  else {
    drush_make_mkdir(dirname($build_path));
    drush_shell_exec("mv %s %s", $tmp_path . '/__build__', $tmp_path . '/' . basename($build_path));
    drush_shell_exec("cp -Rf %s %s", $tmp_path . '/' . basename($build_path), dirname($build_path));
  }
  if (!$ret) {
    drush_set_error(dt("Cannot move build into place"));
  }
  return $ret;
}

function drush_make_version() {
  return '2.2';
}

/**
 * Drush callback; make a makefile from the current install.
 */
function drush_drush_make_make_generate($file) {
  include_once 'drush_make.generate.inc';
  _drush_make_generate($file);
}

/**
 * Drush callback; run a drush make test.
 */
function drush_drush_make_make_test($test = NULL) {
  include_once 'drush_make.test.inc';
  drush_make_test($test);
}
