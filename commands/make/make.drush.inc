<?php
/**
 * @file
 * Drush Make commands.
 */

/**
 * Default localization server for downloading translations.
 */
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
 * Implements hook_drush_command().
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
      'drush make http://example.com/example.make example' => 'Build the remote example.make makefile in the example directory.',
    ),
    'options' => array(
      'version' => 'Print the make API version and exit.',
      'concurrency' => array(
        'description' => 'Set the number of concurrent projects that will be processed at the same time. The default is 1.',
        'example-value' => '1',
      ),
      'contrib-destination' => 'Specify a path under which modules and themes should be placed. Defaults to sites/all for Drupal 6,7 and the corresponding directory in the Drupal root for Drupal 8 and above.',
      'force-complete' => 'Force a complete build even if errors occur.',
      'ignore-checksums' => 'Ignore md5 checksums for downloads.',
      'md5' => array(
        'description' => 'Output an md5 hash of the current build after completion. Use --md5=print to print to stdout.',
        'example-value' => 'print',
        'value' => 'optional',
      ),
      'make-update-default-url' => 'The default location to load the XML update information from.',
      'no-cache' => 'Do not use the pm-download caching (defaults to cache enabled).',
      'no-clean' => 'Leave temporary build directories in place instead of cleaning up after completion.',
      'no-core' => 'Do not require a Drupal core project to be specified.',
      'no-patch-txt' => 'Do not write a PATCHES.txt file in the directory of each patched project.',
      'no-gitinfofile' => 'Do not modify .info files when cloning from Git.',
      'prepare-install' => 'Prepare the built site for installation. Generate a properly permissioned settings.php and files directory.',
      'tar' => 'Generate a tar archive of the build. The output filename will be [build path].tar.gz.',
      'test' => 'Run a temporary test build and clean up.',
      'translations' => 'Retrieve translations for the specified comma-separated list of language(s) if available for all projects.',
      'working-copy' => 'Preserves VCS directories, like .git, for projects downloaded using such methods.',
      'download-mechanism' => 'How to download files. Should be autodetected, but this is an override if it doesn\'t work. Options are "curl" and "make" (a native download method).',
      'projects' => array(
        'description' => 'Restrict the make to this comma-separated list of projects. To specify all projects, pass *.',
        'example' => 'views,ctools',
      ),
      'libraries' => array(
        'description' => 'Restrict the make to this comma-separated list of libraries. To specify all libraries, pass *.',
        'example' => 'tinymce',
      ),
      'allow-override' => array(
        'description' => 'Restrict the make options to this comma-separated list of options.',
        'example' => 'all or none or working-copy or no-core, working-copy',
      ),
    ),
    'engines' => array('release_info'),
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
    'engines' => array('release_info'),
    'aliases' => array('generate-makefile'),
  );

  // Hidden command to build a group of projects.
  $items['make-process'] = array(
    'hidden' => TRUE,
    'arguments' => array(
      'directory' => 'The temporary working directory to use',
    ),
    'options' => array(
      'projects' => 'An array of projects generated by make_projects()',
      'manifest' => 'An array of projects already being processed',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'engines' => array('release_info'),
  );

  // Add docs topic.
  $docs_dir = drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH);
  $items['docs-make'] = array(
    'description' => 'Drush Make overview with examples',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/make.txt'),
  );
  $items['docs-make-example'] = array(
    'description' => 'Drush Make example makefile',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.make'),
  );
  return $items;
}

/**
 * Implements hook_drush_help().
 */
function make_drush_help($section) {
  switch ($section) {
    case 'drush:make':
      return 'Turns a makefile into a Drupal codebase. For a full description of options and makefile syntax, see docs/make.txt and examples/example.make.';
    case 'drush:make-generate':
      return 'Generate a makefile from the current Drupal site, specifying project version numbers unless not known or otherwise specified. Unversioned projects will be interpreted later by drush make as "most recent stable release"';
  }
}

/**
 * Command argument complete callback.
 *
 * @return array
 *   Strong glob of files to complete on.
 */
function make_make_complete() {
  return array(
    'files' => array(
      'directories' => array(
        'pattern' => '*',
        'flags' => GLOB_ONLYDIR,
      ),
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
    drush_print(dt('drush make API version !version', array('!version' => MAKE_API)));
    drush_print_pipe(MAKE_API);
    return;
  }

  if (!($build_path = make_build_path($build_path))) {
    return FALSE;
  }

  $info = make_parse_info_file($makefile);

  // Support making just a portion of a make file.
  $include_only = array(
    'projects' => array_filter(drush_get_option_list('projects')),
    'libraries' => array_filter(drush_get_option_list('libraries')),
  );
  $info = make_prune_info_file($info, $include_only);

  if ($info === FALSE || ($info = make_validate_info_file($info)) === FALSE) {
    return FALSE;
  }

  drush_log(dt('Beginning to build !makefile.', array('!makefile' => $makefile)), 'ok');

  $make_dir = realpath(dirname($makefile));
  $core_version = str_replace('.x', '', $info['core'][0]);
  $sitewide = drush_drupal_sitewide_directory($core_version);
  if (make_projects(FALSE, drush_get_option('contrib-destination', $sitewide), $info, $build_path, $make_dir)) {
    make_libraries(drush_get_option('contrib-destination', $sitewide), $info, $build_path, $make_dir);

    if (drush_get_option('prepare-install')) {
      make_prepare_install($build_path);
    }
  }
  return $info;
}

/**
 * Drush callback: hidden file to process an individual project.
 */
function drush_make_process($directory) {
  // Set the temporary directory.
  make_tmp(TRUE, $directory);
  $projects = drush_get_option('projects', FALSE);

  $manifest = drush_get_option('manifest', FALSE);

  foreach ($projects as $project) {
    if ($instance = DrushMakeProject::getInstance($project['type'], $project)) {
      $instance->setManifest($manifest);
      $instance->make();
    }
    else {
      make_error('PROJECT-TYPE', dt('Non-existent project type %type on project %project', array('%type' => $project['type'], '%project' => $project['name'])));
    }
  }
}

/**
 * Implements drush_hook_post_COMMAND() for the make command.
 */
function drush_make_post_make($makefile = NULL, $build_path = NULL) {
  if (drush_get_option('version')) {
    return;
  }
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

/**
 * Process all projects specified in the make file.
 */
function make_projects($recursion, $contrib_destination, $info, $build_path, $make_dir) {
  $projects = array();
  if (empty($info['projects'])) {
    if (drush_get_option('no-core') || $recursion) {
      return TRUE;
    }
    else {
      drush_set_error('MAKE_NO_CORE', dt('No core project specified.'));
      return FALSE;
    }
  }
  $ignore_checksums = drush_get_option('ignore-checksums');
  $translations = array();
  if (isset($info['translations'])) {
    $translations = $info['translations'];
  }
  if ($arg_translations = drush_get_option('translations', FALSE)) {
    $translations = array_merge(explode(',', $arg_translations), $translations);
  }
  foreach ($info['projects'] as $key => $project) {
    $md5 = '';
    if (isset($project['md5'])) {
      $md5 = $project['md5'];
    }
    // Merge the known data onto the project info.
    $project += array(
      'name'                => $key,
      'core'                => $info['core'],
      'translations'        => $translations,
      'build_path'          => $build_path,
      'contrib_destination' => $contrib_destination,
      'version'             => '',
      'location'            => drush_get_option('make-update-default-url', RELEASE_INFO_DEFAULT_URL),
      'subdir'              => '',
      'directory_name'      => '',
      'make_directory'      => $make_dir,
      'options'             => array(),
    );
    // If download components are specified, but not the download
    // type, default to git.
    if (isset($project['download']) && !isset($project['download']['type'])) {
      $project['download']['type'] = 'git';
    }
    if (!isset($project['l10n_url']) && ($project['location'] == RELEASE_INFO_DEFAULT_URL)) {
      $project['l10n_url'] = MAKE_DEFAULT_L10N_SERVER;
    }

    // For convenience: define $request to be compatible with release_info
    // engine.
    // TODO: refactor to enforce 'make' to internally work with release_info
    // keys.
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
    drush_set_error('MAKE_NO_CORE', dt('No core project specified.'));
    return FALSE;
  }
  elseif ($cores == 1 && $recursion) {
    unset($projects['core']);
  }
  elseif ($cores > 1) {
    drush_set_error('MAKE_MULTIPLE_CORES', dt('More than one core project specified.'));
    return FALSE;
  }

  foreach ($projects as $type => $type_projects) {
    foreach ($type_projects as $project) {
      if (make_project_needs_release_info($project)) {
        // For convenience: define $request to be compatible with release_info
        // engine.
        // TODO: refactor to enforce 'make' to internally work with release_info
        // keys.
        $request = make_prepare_request($project, $type);
        // Set the cache option based on our '--no-cache' option.
        $cache_before = drush_get_option('cache');
        if (!drush_get_option('no-cache', FALSE)) {
          drush_set_option('cache', TRUE);
        }
        $release = release_info_fetch($request);
        if ($release === FALSE) {
          return FALSE;
        }
        // Restore the previous '--cache' option value.
        drush_set_option('cache', $cache_before);
        if (!isset($project['type'])) {
          // Translate release_info key for project_type to drush make.
          $project['type'] = $request['project_type'];
        }
        if (!isset($project['download'])) {
          $project['download'] = array(
            'type' => 'pm',
            'full_version' => $release['version'],
            'download_link' => $release['download_link'],
            'status url' => $request['status url'],
          );
        }
      }
      if (!empty($md5)) {
        $project['download']['md5'] = $md5;
      }
      if ($ignore_checksums) {
        unset($project['download']['md5']);
      }
      $projects[($project['type'] == 'core' ? 'core' : 'contrib')][$project['name']] = $project;
    }
  }

  // Core is built in place, rather than using make-process.
  if (isset($projects['core'])) {
    foreach ($projects['core'] as $project) {
      if ($instance = DrushMakeProject::getInstance($project['type'], $project)) {
        $project = $instance;
      }
      else {
        make_error('PROJECT-TYPE', dt('Non-existent project type %type on project %project', array('%type' => $project['type'], '%project' => $project['name'])));
      }
      $project->make();
    }
  }

  // Process all projects concurrently using make-process.
  if (isset($projects['contrib'])) {
    $concurrency = drush_get_option('concurrency', 1);
    // Generate $concurrency sub-processes to do the actual work.
    $invocations = array();
    $thread = 0;
    foreach ($projects['contrib'] as $project) {
      $thread = ++$thread % $concurrency;
      // Ensure that we've set this sub-process up.
      if (!isset($invocations[$thread])) {
        $invocations[$thread] = array(
          'args' => array(
            make_tmp(),
          ),
          'options' => array(
            'projects' => array(),
          ),
          'site' => array(),
        );
      }
      // Add the project to this sub-process.
      $invocations[$thread]['options']['projects'][] = $project;
      // Add the manifest so recursive downloads do not override projects.
      $invocations[$thread]['options']['manifest'] = $projects['contrib'];
    }
    if (!empty($invocations)) {
      // Backend options.
      $backend_options = array(
        'concurrency' => $concurrency,
        'method' => 'POST',
      );

      $common_options = drush_redispatch_get_options();
      // Merge in stdin options since we process makefiles recursively. See http://drupal.org/node/1510180.
      $common_options = array_merge($common_options, drush_get_context('stdin'));
      // Package handler should use 'wget'.
      $common_options['package-handler'] = 'wget';

      // Avoid any prompts from CLI.
      $common_options['yes'] = TRUE;

      // Use cache unless explicitly turned off.
      if (!drush_get_option('no-cache', FALSE)) {
        $common_options['cache'] = TRUE;
      }
      // Unless --verbose or --debug are passed, quiter backend output.
      if (empty($common_options['verbose']) && empty($common_options['debug'])) {
        $backend_options['#output-label'] = FALSE;
        $backend_options['integrate'] = TRUE;
      }
      drush_backend_invoke_concurrent($invocations, $common_options, $backend_options, 'make-process', '@none');
    }
  }
  return TRUE;
}

/**
 * Process all libraries specified in the make file.
 */
function make_libraries($contrib_destination, $info, $build_path, $make_dir) {
  if (empty($info['libraries'])) {
    return;
  }
  $ignore_checksums = drush_get_option('ignore-checksums');
  foreach ($info['libraries'] as $key => $library) {
    if (!is_string($key) || !is_array($library)) {
      // TODO Print a prettier message.
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
      'make_directory'      => $make_dir,
    );
    if ($ignore_checksums) {
      unset($library['download']['md5']);
    }
    $class = DrushMakeProject::getInstance('library', $library);
    $class->make();
  }
}

/**
 * The path where the final build will be placed.
 */
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
    return drush_set_error('MAKE_PATH_EXISTS', dt('Base path %path already exists', array('%path' => $build_path)));
  }
  $saved_path = $build_path;
  return $build_path;
}

/**
 * Move the completed build into place.
 */
function make_move_build($build_path) {
  $tmp_path = make_tmp();
  $ret = TRUE;
  if ($build_path == '.') {
    $info = drush_scan_directory($tmp_path . DIRECTORY_SEPARATOR . '__build__', '/./', array('.', '..'), 0, FALSE, 'filename', 0, TRUE);
    foreach ($info as $file) {
      $destination = $build_path . DIRECTORY_SEPARATOR . $file->basename;
      if (file_exists($destination)) {
        // To prevent the removal of top-level directories such as 'modules' or
        // 'themes', descend in a level if the file exists.
        // TODO: This only protects one level of directories from being removed.
        $files = drush_scan_directory($file->filename, '/./', array('.', '..'), 0, FALSE);
        foreach ($files as $file) {
          $ret = $ret && drush_copy_dir($file->filename, $destination . DIRECTORY_SEPARATOR . $file->basename, FILE_EXISTS_MERGE);
        }
      }
      else {
        $ret = $ret && drush_copy_dir($file->filename, $destination);
      }
    }
  }
  else {
    drush_mkdir(dirname($build_path));
    $ret = drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . '__build__', $tmp_path . DIRECTORY_SEPARATOR . basename($build_path), TRUE);
    $ret = $ret && drush_copy_dir($tmp_path . DIRECTORY_SEPARATOR . basename($build_path), $build_path);
  }

  // Copying to final destination resets write permissions. Re-apply.
  if (drush_get_option('prepare-install')) {
    $default = $build_path . '/sites/default';
    chmod($default . '/settings.php', 0666);
    chmod($default . '/files', 0777);
  }

  if (!$ret) {
    drush_set_error('MAKE_CANNOT_MOVE_BUILD', dt("Cannot move build into place"));
  }
  return $ret;
}

/**
 * Create a request array for use with release_info_fetch().
 *
 * @param array $project
 *   Project array.
 * @param string $type
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

/**
 * Determine if the release information is required for this
 * project. When it is determined that it is, this potentially results
 * in the use of pm-download to process the project.
 *
 * If the location of the project is not customized (uses d.o), and
 * one of the following is true, then release information is required:
 *
 * - $project['type'] has not been specified
 * - $project['download'] has not been specified
 *
 * @see make_projects()
 */
function make_project_needs_release_info($project) {
  return isset($project['location'])
    // Only fetch release info if the project type is unknown OR if
    // download attributes are unspecified.
    && (!isset($project['type']) || !isset($project['download']));
}
