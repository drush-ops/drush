<?php
// $Id$

define('DRUSH_MAKE_UPDATE_DEFAULT_URL', 'http://updates.drupal.org/release-history');
define('DRUSH_MAKE_VERSION_BEST', 'best');

include_once 'drush_make.utilities.inc';
include_once 'drush_make.download.inc';
include_once 'drush_make.project.inc';
include_once 'drush_make.queue.inc';

/**
 * Implementation of hook_drush_command().
 */
function drush_make_drush_command() {
  $items['make'] = array(
    'description' => 'Turns a makefile into a working drupal install.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['generate makefile'] = array(
    'description' => 'Attempts to generate a makefile from the current drupal install.',
    'callback' => 'drush_generate_makefile',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  );

  $items['convert makefile'] = array(
    'description' => 'Convert the specified makefile to a drupal.org friendly format.',
    'callback' => 'drush_convert_makefile',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function drush_make_drush_help($section) {
  switch ($section) {
    case 'drush:make':
      return dt("@TODO: Print information on the format of the makefile here.");
    case 'drush:generate makefile':
      return dt("This command attempts to generate a makefile from the current drupal install. It is imperfect in that it cannot always find the sources of various components.");
  }
}

/**
 * Drush callback; make a makefile from the current install.
 */
function drush_generate_makefile($file) {
  include_once 'drush_make.generate.inc';
  _drush_generate_makefile($file);
}

/**
 * Drush callback; convert the makefile to a drupal.org friendly format.
 */
function drush_convert_makefile($from, $to) {
  include_once 'drush_make.convert.inc';
  $converter = new DrushMakeDrupalorgConverter($from, $to);
  $converter->run();
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
  if (drush_get_option('tar')) {
    $queue->addItem('drush_make_tar', array(), array($tmp, $base_path));
  }
  else {
    $queue->addItem('drush_make_move_build', array(), array($tmp, $base_path, $valid));
  }
  $queue->addItem('drush_make_clean_tmp', array(), array($tmp));
  $queue->execute();
}

function drush_make_add_projects($recursion, $install_path, $tmp_path, $base_path, $info, $queue) {
  $core = FALSE;
  foreach ($info['projects'] as $key => $project) {
    // Cover if there is no project info, it's just a project name.
    if (is_string($project) && is_numeric($key)) {
      $key = $project;
      $project = array();
    }
    elseif (is_string($project)) {
      $project = array('version' => $project);
    }
    // Merge the known data onto the project info.
    $project += array(
      'name'           => $key,
      'core'           => $info['core'],
      'base_path'      => $base_path,
      'install_path'   => $install_path,
      'tmp_path'       => $tmp_path,
      'version'        => DRUSH_MAKE_VERSION_BEST,
      'location'       => DRUSH_MAKE_UPDATE_DEFAULT_URL,
      'subdir'         => '',
      'directory_name' => '',
      'queue'          => $queue,
    );
    // Special case for Update XML.
    $subqueue = new DrushMakeQueue;
    $update = $subqueue->addItem('drush_make_updatexml', array($project));
    $subqueue->execute();
    $project = $subqueue->getReturn($update);
    if ($project['type'] == 'core') {
      $core = TRUE;
    }

    $init = $queue->addItem('drush_make_initialize_project', array($project));
    $queue->addItem('drush_make_run', array(), $init);
  }
  if (!$core && !$recursion && !drush_get_option('no-core')) {
    drush_set_error(dt('No core project specified.'));
    $queue->terminate();
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
        'version'        => DRUSH_MAKE_VERSION_BEST,
        'location'       => DRUSH_MAKE_UPDATE_DEFAULT_URL,
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
  $xml = file_get_contents($project['location'] . '/' . $project['name'] . '/' . $project['core']);
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
    if ((string) $release->version_patch) {
      $version .= '.' . (string) $release->version_patch;
    }
    else {
      $version .= '.0';
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
  elseif (drush_confirm(dt("Make new site in the current directory?"))) {
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
    drush_shell_exec('ls %s/__build__', $tmp_path);
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
