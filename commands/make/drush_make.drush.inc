<?php
// $Id$

define('DRUSH_MAKE_UPDATE_DEFAULT_URL', 'http://updates.drupal.org/release-history');
define('DRUSH_MAKE_VERSION_BEST', 'best');

include_once 'drush_make.utilities.inc';
include_once 'drush_make.download.inc';
include_once 'drush_make.project.inc';
include_once 'drush_make.queue.inc';

/**
 * Implement EXTENSION_drush_init().
 */
function drush_make_drush_init() {
  // The default URL to use for retrieving update XML files.
  drush_set_default('drush-make-update-default-url', DRUSH_MAKE_UPDATE_DEFAULT_URL);
  // The default method drush_make will use to find the best release.
  drush_set_default('drush-make-version-best', DRUSH_MAKE_VERSION_BEST);
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
      '--contrib-destination=path' => 'Specify a path under which modules and themes should be placed. Defaults to sites/all.',
      '--force-complete' => 'Force a complete build even if errors occur.',
      '--md5' => 'Output an md5 hash of the current build after completion.',
      '--no-clean' => 'Leave temporary build directories in place instead of cleaning up after completion.',
      '--no-core' => 'Do not require a Drupal core project to be specified.',
      '--no-patch-txt' => 'Do not write a PATCHES.txt file in the directory of each patched project.',
      '--prepare-install' => 'Prepare the built site for installation. Generate a properly permissioned settings.php and files directory.',
      '--tar' => 'Generate a tar archive of the build. The output filename will be [build path].tar.gz.',
      '--test' => 'Run a temporary test build and clean up.',
      '--working-copy' => 'Where possible, retrieve a working copy of projects from their respective repositories.',
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

  $items['generate-makefile'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'description' => 'Attempts to generate a makefile from the current drupal install.',
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
    case 'drush:generate-makefile':
      return dt('Attempts to generate a makefile from the current drupal install.');
  }
}

/**
 * Drush callback; make a makefile from the current install.
 */
function drush_drush_make_generate_makefile($file) {
  include_once 'drush_make.generate.inc';
  _drush_generate_makefile($file);
}

/**
 * Drush callback; run a drush make test.
 */
function drush_drush_make_make_test($test = NULL) {
  include_once 'drush_make.test.inc';
  drush_make_test($test);
}

/**
 * Drush callback; make based on the makefile.
 */
function drush_drush_make_make($makefile = NULL, $base_path = NULL) {
  $queue = new DrushMakeQueue();
  $drush_version = $queue->addItem('drush_make_ensure_version');
  $base_path = $queue->addItem('drush_make_base_path', array($base_path), array($drush_version));
  $file_exists = $queue->addItem('drush_make_base_path_validate', array(), $base_path);
  $info  = $queue->addItem('drush_make_parse_info_file', $makefile, $file_exists);
  $valid = $queue->addItem('drush_make_validate_info_file', array(), $info);
  $tmp = $queue->addItem('drush_make_create_tmp', array(), $valid);
  $queue->addItem('drush_make_add_projects', array(FALSE, drush_get_option('contrib-destination', 'sites/all')), array($tmp, $base_path, $valid));
  $queue->addItem('drush_make_add_libraries', array(drush_get_option('contrib-destination', 'sites/all')), array($tmp, $base_path, $valid));
  $queue->execute();

  // Add clean up tasks.
  if (drush_get_option('prepare-install')) {
    $queue->addItem('drush_make_prepare_install', array(), array($tmp, $base_path));
  }
  if (drush_get_option('md5')) {
    $queue->addItem('drush_make_md5', array(), array($tmp));
  }

  // Only take final build steps if not in testing mode.
  if (!drush_get_option('test')) {
    if (drush_get_option('tar')) {
      $queue->addItem('drush_make_tar', array(), array($tmp, $base_path));
    }
    else {
      $queue->addItem('drush_make_move_build', array(), array($tmp, $base_path, $valid));
    }
  }

  $queue->addItem('drush_make_clean_tmp', array(), array($tmp));
  $queue->execute();
}

function drush_make_add_projects($recursion, $install_path, $tmp_path, $base_path, $info, $queue) {
  $projects = array();
  foreach ($info['projects'] as $key => $project) {
    // Merge the known data onto the project info.
    $project += array(
      'name'           => $key,
      'core'           => $info['core'],
      'base_path'      => $base_path,
      'install_path'   => $install_path,
      'tmp_path'       => $tmp_path,
      'version'        => drush_get_option('drush-make-version-best'),
      'location'       => drush_get_option('drush-make-update-default-url'),
      'subdir'         => '',
      'directory_name' => '',
      'queue'          => $queue,
    );
    // Special case for Update XML.
    $subqueue = new DrushMakeQueue;
    $update = $subqueue->addItem('drush_make_updatexml', array($project));
    $subqueue->execute();
    $project = $subqueue->getReturn($update);

    $project_queue = new DrushMakeQueue;
    $init = $project_queue->addItem('drush_make_initialize_project', array($project));
    $project_queue->addItem('drush_make_run', array(), $init);
    $projects[($project['type'] == 'core' ? 'core' : 'contrib')][$project['name']] = $project_queue;
  }
  $cores = count($projects['core']);
  if (drush_get_option('no-core')) {
    unset($projects['core']);
  }
  elseif ($cores == 0 && !$recursion) {
    drush_set_error(dt('No core project specified.'));
    $queue->terminate();
    return FALSE;
  }
  elseif ($cores == 1 && $recursion) {
    unset($projects['core']);
  }
  elseif ($cores > 1) {
    drush_set_error(dt('More than one core project specified.'));
    $queue->terminate();
    return FALSE;
  }
  if (isset($projects['core'])) {
    foreach ($projects['core'] as $project) {
      $project->execute();
    }
  }
  if (isset($projects['contrib'])) {
    foreach ($projects['contrib'] as $project) {
      $project->execute();
    }
  }
}

function drush_make_add_libraries($install_path, $tmp_path, $base_path, $info, $queue) {
  if (!empty($info['libraries'])) {
    foreach ($info['libraries'] as $key => $library) {
      if (!is_string($key) || !is_array($library)) {
        // TODO Print a prettier message
        continue;
      }
      // Merge the known data onto the library info.
      $library += array(
        'name'           => $key,
        'core'           => $info['core'],
        'base_path'      => $base_path,
        'install_path'   => $install_path,
        'tmp_path'       => $tmp_path,
        'version'        => drush_get_option('drush-make-version-best'),
        'location'       => drush_get_option('drush-make-update-default-url'),
        'subdir'         => '',
        'directory_name' => $key,
        'queue'          => $queue,
        'type'           => 'library',
      );
      $init = $queue->addItem('drush_make_initialize_project', array($library));
      $queue->addItem('drush_make_run', array(), $init);
    }
  }
}

function drush_make_updatexml($project) {
  if (isset($project['download']) && isset($project['type'])) {
    return $project;
  }
  $xml = drush_make_get_remote_file($project['location'] . '/' . $project['name'] . '/' . $project['core']);
  // First, get the release history.
  $release_history = simplexml_load_string($xml);
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
      'Drupal project' => 'core',
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
  if ($project['version'] == drush_get_option('drush-make-version-best')) {
    $recommended_major = (string)$project['release_history']->recommended_major;
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

function drush_make_initialize_project($project) {
  $class_name = 'DrushMakeProject_' . $project['type'];
  $class = new $class_name($project);
  return $class;
}

function drush_make_run($project) {
  $project->make();
}

function drush_make_base_path($base_path) {
  // Determine the base of the build.
  if (drush_get_option('tar')) {
    $base_path = dirname($base_path) . '/' . basename($base_path, '.tar.gz') . '.tar.gz';
  }
  elseif (isset($base_path) && (!empty($base_path) || $base_path == '.')) {
    $base_path = rtrim($base_path, '/');
  }
  // Allow tests to run without a specified base path.
  elseif (drush_get_option('test') || drush_confirm(dt("Make new site in the current directory?"))) {
    $base_path = '.';
  }
  else {
    drush_log(dt('Build aborted.'), 'ok');
    return FALSE;
  }
  return $base_path;
}

function drush_make_move_build($tmp_path, $base_path) {
  if ($base_path == '.') {
    drush_shell_exec('ls -A %s/__build__', $tmp_path);
    $info = drush_shell_exec_output();
    foreach ($info as $file) {
      drush_shell_exec("mv %s/__build__/%s %s/%s", $tmp_path, $file, $base_path, $file);
    }
  }
  else {
    drush_make_fill_path(dirname($base_path));
    drush_shell_exec("mv %s/__build__ %s", $tmp_path, $base_path);
  }
  return TRUE;
}
