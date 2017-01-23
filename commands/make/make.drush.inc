<?php
/**
 * @file
 * Drush Make commands.
 */

use Drush\Log\LogLevel;
use Drush\UpdateService\ReleaseInfo;

/**
 * Default localization server for downloading translations.
 */
define('MAKE_DEFAULT_L10N_SERVER', 'http://ftp.drupal.org/files/translations/l10n_server.xml');

/**
 * Make refuses to build makefiles whose api version is mismatched
 * with make command.
 */
define('MAKE_API', 2);

include_once 'make.utilities.inc';
include_once 'make.download.inc';
include_once 'make.project.inc';
include_once 'generate.contents.make.inc';

/**
 * Implements hook_drush_help().
 */
function make_drush_help($section) {
  switch ($section) {
    case 'meta:make:title':
      return dt('Make commands');
    case 'meta:make:summary':
      return dt('Manage Drupal codebases using manifests of projects and libraries.');
    case 'drush:make':
      return dt('Turns a makefile into a Drupal codebase. For a full description of options and makefile syntax, see docs/make.txt and examples/example.make.');
    case 'drush:make-generate':
      return dt('Generate a makefile from the current Drupal site, specifying project version numbers unless not known or otherwise specified. Unversioned projects will be interpreted later by drush make as "most recent stable release"');
  }
}

/**
 * Implements hook_drush_command().
 */
function make_drush_command() {
  $projects = array(
    'description' => 'Restrict the make to this comma-separated list of projects. To specify all projects, pass *.',
    'example-value' => 'views,ctools',
  );
  $libraries = array(
    'description' => 'Restrict the make to this comma-separated list of libraries. To specify all libraries, pass *.',
    'example-value' => 'tinymce',
  );

  $items['make'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Turns a makefile into a working Drupal codebase.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to use for this build.',
      'build path' => 'The path at which to build the makefile.',
    ),
    'examples' => array(
      'drush make example.make example' => 'Build the example.make makefile in the example directory.',
      'drush make --no-core --contrib-destination=. installprofile.make' => 'Build an installation profile within an existing Drupal site',
      'drush make http://example.com/example.make example' => 'Build the remote example.make makefile in the example directory.',
      'drush make example.make --no-build --lock=example.lock' => 'Write a new makefile to example.lock. All project versions will be resolved.',
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
      'no-build' => 'Do not build a codebase. Makes the `build path` argument optional.',
      'no-cache' => 'Do not use the pm-download caching (defaults to cache enabled).',
      'no-clean' => 'Leave temporary build directories in place instead of cleaning up after completion.',
      'no-core' => 'Do not require a Drupal core project to be specified.',
      'no-recursion' => 'Do not recurse into the makefiles of any downloaded projects; you can also set [do_recursion] = 0 on a per-project basis in the makefile.',
      'no-patch-txt' => 'Do not write a PATCHES.txt file in the directory of each patched project.',
      'no-gitinfofile' => 'Do not modify .info files when cloning from Git.',
      'force-gitinfofile' => 'Force a modification of .info files when cloning from Git even if repository isn\'t hosted on Drupal.org.',
      'no-gitprojectinfo' => 'Do not inject project info into .info files when cloning from Git.',
      'overwrite' => 'Overwrite existing directories. Default is to merge.',
      'prepare-install' => 'Prepare the built site for installation. Generate a properly permissioned settings.php and files directory.',
      'tar' => 'Generate a tar archive of the build. The output filename will be [build path].tar.gz.',
      'test' => 'Run a temporary test build and clean up.',
      'translations' => 'Retrieve translations for the specified comma-separated list of language(s) if available for all projects.',
      'working-copy' => 'Preserves VCS directories, like .git, for projects downloaded using such methods.',
      'download-mechanism' => 'How to download files. Should be autodetected, but this is an override if it doesn\'t work. Options are "curl" and "make" (a native download method).',
      'projects' => $projects,
      'libraries' => $libraries,
      'allow-override' => array(
        'description' => 'Restrict the make options to a comma-separated list. Defaults to unrestricted.',
      ),
      'lock' => array(
        'description' => 'Generate a makefile, based on the one passed in, with all versions *resolved*. Defaults to printing to the terminal, but an output file may be provided.',
        'example-value' => 'example.make.lock',
      ),
      'shallow-clone' => array(
        'description' => 'For makefile entries which use git for downloading, this option will utilize shallow clones where possible (ie. by using the git-clone\'s depth=1 option). If the "working-copy" option is not desired, this option will significantly speed up makes which involve modules stored in very large git repos. In fact, if "working-copy" option is enabled, this option cannot be used.',
      ),
      'bundle-lockfile' => array(
        'description' => 'Generate a lockfile for this build and copy it into the codebase (at sites/all/drush/platform.lock). An alternate path (relative to the Drupal root) can also be specified',
        'example-value' => 'sites/all/drush/example.make.lock',
      ),
      'format' => array(
        'description' => 'The format for generated lockfiles. Options are "yaml" or "ini". Defaults to "yaml".',
        'example-value' => 'ini',
      ),
      'core-quick-drupal' => array(
        'description' => 'Return project info for use by core-quick-drupal.',
        'hidden' => TRUE,
      ),
      'includes' => 'A list of makefiles to include at build-time.',
      'overrides' => 'A list of makefiles to that can override values in other makefiles.',
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
      'format' => array(
        'description' => 'The format for generated makefile. Options are "yaml" or "ini". Defaults to "yaml".',
        'example-value' => 'ini',
      ),
    ),
    'engines' => array('release_info'),
    'aliases' => array('generate-makefile'),
  );

  $items['make-convert'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Convert a legacy makefile into another format. Defaults to converting .make => .make.yml.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to convert.',
    ),
    'options' => array(
      'projects' => $projects,
      'libraries' => $libraries,
      'includes' => 'A list of makefiles to include at build-time.',
      'format' => 'The format to which the make file should be converted. Accepted values include make, composer, and yml.',
    ),
    'required-arguments' => TRUE,
    'examples' => array(
      'drush make-convert example.make --format=composer  > composer.json' => 'Convert example.make to composer.json',
      'drush make-convert example.make --format=yml > example.make.yml' => 'Convert example.make to example.make.yml',
      'drush make-convert composer.lock --format=make > example.make' => 'Convert composer.lock example.make',
    ),
  );

  // Hidden command to build a group of projects.
  $items['make-process'] = array(
    'hidden' => TRUE,
    'arguments' => array(
      'directory' => 'The temporary working directory to use',
    ),
    'options' => array(
      'projects-location' => 'Name of a temporary file containing json-encoded output of make_projects().',
      'manifest' => 'An array of projects already being processed.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'engines' => array('release_info'),
  );

  $items['make-update'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Process a makefile and outputs an equivalent makefile with projects version resolved to latest available.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to use for this build.',
    ),
    'options' => array(
      'result-file' => array(
        'description' => 'Save to a file. If not provided, the updated makefile will be dumped to stdout.',
        'example-value' => 'updated.make',
      ),
      'format' => array(
        'description' => 'The format for generated lockfiles. Options are "yaml" or "ini". Defaults to "yaml".',
        'example-value' => 'ini',
      ),
      'includes' => 'A list of makefiles to include at build-time.',
    ),
    'engines' => array('release_info', 'update_status'),
  );

  $items['make-lock'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'description' => 'Process a makefile and outputs an equivalent makefile with projects version *resolved*. Respects pinned versions.',
    'arguments' => array(
      'makefile' => 'Filename of the makefile to use for this build.',
    ),
    'options' => array(
      'result-file' => array(
        'description' => 'Save to a file. If not provided, the lockfile will be dumped to stdout.',
        'example-value' => 'platform.lock',
      ),
      'format' => array(
        'description' => 'The format for generated lockfiles. Options are "yaml" or "ini". Defaults to "yaml".',
        'example-value' => 'ini',
      ),
      'includes' => 'A list of makefiles to include at build-time.',
    ),
    'allow-additional-options' => TRUE,
    'engines' => array('release_info', 'update_status'),
  );

  // Add docs topic.
  $docs_dir = drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH);
  $items['docs-make'] = array(
    'description' => 'Drush Make overview with examples',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/make.md'),
  );
  $items['docs-make-example'] = array(
    'description' => 'Drush Make example makefile',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.make.yml'),
  );
  return $items;
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
 * Validation callback for make command.
 */
function drush_make_validate($makefile = NULL, $build_path = NULL) {
  // Don't validate if --version option is supplied.
  if (drush_get_option('version', FALSE)) {
    return;
  }

  if (drush_get_option('shallow-clone', FALSE) && drush_get_option('working-copy', FALSE)) {
    return drush_set_error('MAKE_SHALLOW_CLONE_WORKING_COPY_CONFLICT', dt('You cannot use "--shallow-clone" and "--working-copy" options together.'));
  }

  // Error out if the build path is not valid and --no-build was not supplied.
  if (!drush_get_option('no-build', FALSE) && !make_build_path($build_path)) {
    return FALSE;
  }
}

/**
 * Implements drush_hook_pre_COMMAND().
 *
 * If --version option is supplied, print it and prevent execution of the command.
 */
function drush_make_pre_make($makefile = NULL, $build_path = NULL) {
  if (drush_get_option('version', FALSE)) {
    drush_print(dt('Drush make API version !version', array('!version' => MAKE_API)));
    drush_print_pipe(MAKE_API);
    // Prevent command execution.
    return FALSE;
  }
}

/**
 * Drush callback; make based on the makefile.
 */
function drush_make($makefile = NULL, $build_path = NULL) {
  // Set the cache option based on our '--no-cache' option.
  _make_enable_cache();

  // Build.
  if (!drush_get_option('no-build', FALSE)) {
    $info = make_parse_info_file($makefile);
    drush_log(dt('Beginning to build !makefile.', array('!makefile' => $makefile)), LogLevel::OK);

    // Default contrib destination depends on Drupal core version.
    $core_version = str_replace('.x', '', $info['core'][0]);
    $sitewide = drush_drupal_sitewide_directory($core_version);
    $contrib_destination = drush_get_option('contrib-destination', $sitewide);

    $build_path = make_build_path($build_path);
    $make_dir = realpath(dirname($makefile));

    $success = make_projects(FALSE, $contrib_destination, $info, $build_path, $make_dir);
    if ($success) {
      make_libraries(FALSE, $contrib_destination, $info, $build_path, $make_dir);

      if (drush_get_option('prepare-install')) {
        make_prepare_install($build_path);
      }
      if ($option = drush_get_option('md5')) {
        $md5 = make_md5();
        if ($option === 'print') {
          drush_print($md5);
        }
        else {
          drush_log(dt('Build hash: %md5', array('%md5' => $md5)), LogLevel::OK);
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
    else {
      return make_error('MAKE_PROJECTS_FAILED', dt('Drush Make failed to download all projects. See the log above for the specific errors.'));
    }
  }

  // Process --lock and --bundle-lockfile
  $lockfiles = array();
  if ($result_file = drush_get_option('bundle-lockfile', FALSE)) {
    if ($result_file === TRUE) {
      $result_file = 'sites/all/drush/platform.make';
    }
    $lockfiles[] = $build_path . '/' . $result_file;
  }
  if ($result_file = drush_get_option('lock', FALSE)) {
    $lockfiles[] = $result_file;
  }
  if (count($lockfiles)) {
    foreach ($lockfiles as $lockfile) {
      if ($lockfile !== TRUE) {
        $result_file = drush_normalize_path($lockfile);
        drush_mkdir(dirname($result_file), $required = TRUE);
        drush_set_option('result-file', $result_file);
      }
      drush_invoke('make-lock', $makefile);
      drush_unset_option('result-file');
    }
  }

  // Used by core-quick-drupal command.
  // @see drush_core_quick_drupal().
  if (drush_get_option('core-quick-drupal', FALSE)) {
    return $info;
  }
}

/**
 * Command callback; convert ini makefile to YAML.
 */
function drush_make_convert($source) {
  $dest_format = drush_get_option('format', 'yml');

  // Load source data.
  $source_format = pathinfo($source, PATHINFO_EXTENSION);

  if ($source_format == $dest_format || $source_format == 'lock' && $dest_format == 'composer') {
    drush_print('The source format cannot be the same as the destination format.');
  }

  // Obtain drush make $info array, converting if necessary.
  switch ($source_format) {
    case 'make':
    case 'yml':
    case 'yaml':
      $info = make_parse_info_file($source);
      break;
    case 'lock':
      $composer_json_file = str_replace('lock', 'json', $source);
      if (!file_exists($composer_json_file)) {
        drush_print('Please ensure that a composer.json file is in the same directory as the specified composer.lock file.');
        return FALSE;
      }
      $composer_json = json_decode(make_get_data($composer_json_file), TRUE);
      $composer_lock = json_decode(make_get_data($source), TRUE);
      $info = drush_make_convert_composer_to_make($composer_lock, $composer_json);
      break;
    case 'json':
      drush_print('Please use composer.lock instead of composer.json as source for conversion.');
      return FALSE;
      break;
  }

  // Output into destination formation.
  switch ($dest_format) {
    case 'yml':
    case 'yaml':
      $output = drush_make_convert_make_to_yml($info);
      break;

    case 'make':
      foreach ($info['projects'] as $key => $project) {
        $info['projects'][$key]['_type'] = $info['projects'][$key]['type'];
      }
      foreach ($info['libraries'] as $key => $library) {
        $info['libraries'][$key]['_type'] = 'librarie';
      }
      $output = _drush_make_generate_makefile_contents($info['projects'], $info['libraries'], $info['core'], $info['defaults']);

      break;

    case 'composer':
      $output = drush_make_convert_make_to_composer($info);
      break;
  }

  drush_print($output);
}

/**
 * Converts a composer.lock array into a traditional drush make array.
 *
 * @param array $composer_lock
 *   An array of composer.lock data.
 *
 * @param array $composer_json
 *   An array of composer.json data.
 *
 * @return array A traditional drush make info array.
 * A traditional drush make info array.
 */
function drush_make_convert_composer_to_make($composer_lock, $composer_json) {
  $info = array(
    'core' => array(),
    'api' => 2,
    'defaults' => array(
      'projects' => array(
        'subdir' => 'contrib',
      ),
    ),
    'projects' => array(),
    'libraries' => array(),
  );

  // The make generation function requires that projects be grouped by type,
  // or else duplicative project groups will be created.
  $core = array();
  $modules = array();
  $themes = array();
  $profiles = array();
  $libraries = array();
  foreach ($composer_lock['packages'] as $key => $package) {
    if (strpos($package['name'], 'drupal/') === 0 && in_array($package['type'], array('drupal-core', 'drupal-theme', 'drupal-module', 'drupal-profile'))) {
      $project_name = str_replace('drupal/', '', $package['name']);

      switch ($package['type']) {
        case 'drupal-core':
          $project_name = 'drupal';
          $group =& $core;
          $group[$project_name]['type'] = 'core';
          $info['core'] = substr($package['version'], 0, 1) . '.x';
          break;
        case 'drupal-theme':
          $group =& $themes;
          $group[$project_name]['type'] = 'theme';
          break;
        case 'drupal-module':
          $group =& $modules;
          $group[$project_name]['type'] = 'module';
          break;
        case 'drupal-profile':
          $group =& $profiles;
          $group[$project_name]['type'] = 'profile';
          break;
      }

      $group[$project_name]['download']['type'] = 'git';
      $group[$project_name]['download']['url'] = $package['source']['url'];
      // Dev versions should use git branch + revision, otherwise a tag is used.
      if (strstr($package['version'], 'dev')) {
        // 'dev-' prefix indicates a branch-alias. Stripping the dev prefix from
        // the branch name is sufficient.
        // @see https://getcomposer.org/doc/articles/aliases.md
        if (strpos($package['version'], 'dev-') === 0) {
          $group[$project_name]['download']['branch'] = substr($package['version'], 4);
        }
        // Otherwise, leave as is. Version may already use '-dev' suffix.
        else {
          $group[$project_name]['download']['branch'] = $package['version'];
        }
        $group[$project_name]['download']['revision'] = $package['source']['reference'];
      }
      elseif ($package['type'] == 'drupal-core') {
        // For 7.x tags, replace 7.xx.0 with 7.xx.
        if ($info['core'] == '7.x') {
          $group[$project_name]['download']['tag']= substr($package['version'], 0, 4);
        }
        else {
          $group[$project_name]['download']['tag'] = $package['version'];
        }
      }
      else {
        // Make tag versioning drupal-friendly. 8.1.0-alpha1 => 8.x-1.0-alpha1.
        $major_version = substr($package['version'], 0 ,1);
        $the_rest = substr($package['version'], 2, strlen($package['version']));
        $group[$project_name]['download']['tag'] = "$major_version.x-$the_rest";
      }

      if (!empty($package['extra']['patches_applied'])) {
        foreach ($package['extra']['patches_applied'] as $desc => $url) {
          $group[$project_name]['patch'][] = $url;
        }
      }
    }
    // Include any non-drupal libraries that exist in both .lock and .json.
    elseif (!in_array($package['type'], array('composer-plugin', 'metapackage'))
      && array_key_exists($package['name'], $composer_json['require'])) {
      $project_name = $package['name'];
      $libraries[$project_name]['type'] = 'library';
      $libraries[$project_name]['download']['type'] = 'git';
      $libraries[$project_name]['download']['url'] = $package['source']['url'];
      $libraries[$project_name]['download']['branch'] = $package['version'];
      $libraries[$project_name]['download']['revision'] = $package['source']['reference'];
    }
  }

  $info['projects'] = $core + $modules + $themes;
  $info['libraries'] = $libraries;

  return $info;
}

/**
 * Converts a drush info array to a composer.json array.
 *
 * @param array $info
 *   A drush make info array.
 *
 * @return string
 *   A json encoded composer.json schema object.
 */
function drush_make_convert_make_to_composer($info) {
  $core_major_version = substr($info['core'], 0, 1);
  $core_project_name = $core_major_version == 7 ? 'drupal/drupal' : 'drupal/core';

  // Add default projects.
  $projects = array(
    'composer/installers' => '^1.0.20',
    'cweagans/composer-patches' => '~1.0',
    $core_project_name => str_replace('x', '*', $info['core']),
  );

  $patches = array();

  // Iterate over projects, populating composer-friendly array.
  foreach ($info['projects'] as $project_name => $project) {
    switch ($project['type']) {
      case 'core':
        $project['name'] = 'drupal/core';
        $projects[$project['name']] = str_replace('x', '*', $project['version']);
        break;

      default:
        $project['name'] = "drupal/$project_name";
        $projects[$project['name']] = drush_make_convert_project_to_composer($project, $core_major_version);
        break;
    }

    // Add project patches.
    if (!empty($project['patch'])) {
      foreach($project['patch'] as $key => $patch) {
        $patch_description = "Enter {$project['name']} patch #$key description here";
        $patches[$project['name']][$patch_description] = $patch;
      }
    }
  }

  // Iterate over libraries, populating composer-friendly array.
  if (!empty($info['libraries'])) {
    foreach ($info['libraries'] as $library_name => $library) {
      $library_name = 'Verify project name: ' . $library_name;
      $projects[$library_name] = drush_make_convert_project_to_composer($library, $core_major_version);
    }
  }

  $output = array(
    'name' => 'Enter project name here',
    'description' => 'Enter project description here',
    'type' => 'project',
    'repositories' => array(
      array('type' => 'composer', 'url' => 'https://packagist.drupal-composer.org'),
    ),
    'require' => $projects,
    'minimum-stability' => 'dev',
    'prefer-stable' => TRUE,
    'extra' => array(
      'installer-paths' => array(
        'core' => array('type:drupal-core'),
        'docroot/modules/contrib/{$name}' => array('type:drupal-module'),
        'docroot/profiles/contrib/{$name}' => array('type:drupal-profile'),
        'docroot/themes/contrib/{$name}' => array('type:drupal-theme'),
        'drush/contrib/{$name}' => array('type:drupal-drush'),
      ),
      'patches' => $patches,
    ),
  );

  $output = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

  return $output;
}

/**
 * Converts a make file project array into a composer project version string.
 *
 * @param array $original_project
 *   A project dependency, as defined in a make file.
 *
 * @param string $core_major_version
 *   The major core version. E.g., 6, 7, 8, etc.
 *
 * @return string
 *   The project version, in composer syntax.
 *
 */
function drush_make_convert_project_to_composer($original_project, $core_major_version) {
  // Typical specified version with major version "x" removed.
  if (!empty($original_project['version'])) {
    $version = str_replace('x', '0', $original_project['version']);
  }
  // Git branch or revision.
  elseif (!empty($original_project['download'])) {
    switch ($original_project['download']['type']) {
      case 'git':
        if (!empty($original_project['download']['branch'])) {
          // @todo Determine if '0' will always be correct.
          $version = str_replace('x', '0', $original_project['download']['branch']);
        }
        if (!empty($original_project['download']['tag'])) {
          // @todo Determine if '0' will always be correct.
          $version = str_replace('x', '0', $original_project['download']['tag']);
        }
        if (!empty($project['download']['revision'])) {
          $version .= '#' . $original_project['download']['revision'];
        }
        break;

      default:
        $version = 'Enter correct project name and version number';
        break;
    }
  }

  $version = "$core_major_version." . $version;

  return $version;
}

/**
 * Converts a drush info array to a YAML array.
 *
 * @param array $info
 *   A drush make info array.
 *
 * @return string
 *   A yaml encoded info array.
 */
function drush_make_convert_make_to_yml($info) {
  // Remove incorrect value.
  unset($info['format']);

  // Replace "*" with "~" for project versions.
  foreach ($info['projects'] as $key => $project) {
    if ($project['version'] == '*') {
      $info['projects'][$key]['version'] = '~';
    }
  }

  $dumper = drush_load_engine('outputformat', 'yaml');
  $output = $dumper->format($info, array());

  return $output;
}

/**
 * Drush callback: hidden file to process an individual project.
 *
 * @param string $directory
 *   Directory where the project is being built.
 */
function drush_make_process($directory) {
  drush_get_engine('release_info');

  // Set the temporary directory.
  make_tmp(TRUE, $directory);
  if (!$projects_location = drush_get_option('projects-location')) {
    return drush_set_error('MAKE-PROCESS', dt('No projects passed to drush_make_process'));
  }
  $projects = json_decode(file_get_contents($projects_location), TRUE);
  $manifest = drush_get_option('manifest', array());

  foreach ($projects as $project) {
    if ($instance = DrushMakeProject::getInstance($project['type'], $project)) {
      $instance->setManifest($manifest);
      $instance->make();
    }
    else {
      make_error('PROJECT-TYPE', dt('Non-existent project type %type on project %project.', array('%type' => $project['type'], '%project' => $project['name'])));
    }
  }
}

/**
 * Gather additional data on all projects specified in the make file.
 */
function make_prepare_projects($recursion, $info, $contrib_destination = '', $build_path = '', $make_dir = '') {
  $release_info = drush_get_engine('release_info');

  // Nothing to make if the project list is empty. Maybe complain about it.
  if (empty($info['projects'])) {
    if (drush_get_option('no-core') || $recursion) {
      return TRUE;
    }
    else {
      return drush_set_error('MAKE_NO_CORE', dt('No core project specified.'));
    }
  }

  // Obtain translations to download along with the projects.
  $translations = array();
  if (isset($info['translations'])) {
    $translations = $info['translations'];
  }
  if ($arg_translations = drush_get_option('translations', FALSE)) {
    $translations = array_merge(explode(',', $arg_translations), $translations);
  }

  // Normalize projects.
  $projects = array();
  $ignore_checksums = drush_get_option('ignore-checksums');
  foreach ($info['projects'] as $key => $project) {
    // Merge the known data onto the project info.
    $project += array(
      'name'                => $key,
      'type'                => 'module',
      'core'                => $info['core'],
      'translations'        => $translations,
      'build_path'          => $build_path,
      'contrib_destination' => $contrib_destination,
      'version'             => '',
      'location'            => drush_get_option('make-update-default-url', ReleaseInfo::DEFAULT_URL),
      'subdir'              => '',
      'directory_name'      => '',
      'make_directory'      => $make_dir,
      'options'             => array(),
    );
    // MD5 Checksum.
    if ($ignore_checksums) {
      unset($project['download']['md5']);
    }
    elseif (!empty($project['md5'])) {
      $project['download']['md5'] = $project['md5'];
    }

    // If download components are specified, but not the download
    // type, default to git.
    if (isset($project['download']) && !isset($project['download']['type'])) {
      $project['download']['type'] = 'git';
    }
    // Localization server.
    if (!isset($project['l10n_url']) && ($project['location'] == ReleaseInfo::DEFAULT_URL)) {
      $project['l10n_url'] = MAKE_DEFAULT_L10N_SERVER;
    }
    // Classify projects in core or contrib.
    if ($project['type'] == 'core') {
      $project['download_type'] = 'core';
    }
    elseif ($project['location'] != ReleaseInfo::DEFAULT_URL || !isset($project['download'])) {
      $request = make_prepare_request($project);
      $is_core = $release_info->checkProject($request, 'core');
      $project['download_type'] = ($is_core ? 'core' : 'contrib');
      $project['type'] = $is_core ? 'core' : $project['type'];
    }
    else {
      $project['download_type'] = ($project['name'] == 'drupal' ? 'core' : 'contrib');
    }
    $projects[$project['download_type']][$project['name']] = $project;
  }

  // Verify there're enough cores, but not too many.
  $cores = !empty($projects['core']) ? count($projects['core']) : 0;
  if (drush_get_option('no-core')) {
    unset($projects['core']);
  }
  elseif ($cores == 0 && !$recursion) {
    return drush_set_error('MAKE_NO_CORE', dt('No core project specified.'));
  }
  elseif ($cores == 1 && $recursion) {
    unset($projects['core']);
  }
  elseif ($cores > 1) {
    return drush_set_error('MAKE_MULTIPLE_CORES', dt('More than one core project specified.'));
  }

  // Set download type = pm for suitable projects.
  foreach (array_keys($projects) as $project_type) {
    foreach ($projects[$project_type] as $project) {
      if (make_project_needs_release_info($project)) {
        $request = make_prepare_request($project, $project_type);
        $release = $release_info->selectReleaseBasedOnStrategy($request, '', 'ignore');
        if ($release === FALSE) {
          return FALSE;
        }
        // Override default project type with data from update service.
        if (!isset($info['projects'][$project['name']]['type'])) {
          $project['type'] = $release_info->get($request)->getType();
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
      $projects[$project_type][$project['name']] = $project;
    }
  }
  if (!$recursion) {
    $projects += array(
      'core' => array(),
      'contrib' => array(),
    );
    drush_set_option('DRUSH_MAKE_PROJECTS', array_merge($projects['core'], $projects['contrib']));
  }
  return $projects;
}

/**
 * Process all projects specified in the make file.
 */
function make_projects($recursion, $contrib_destination, $info, $build_path, $make_dir) {
  $projects = make_prepare_projects($recursion, $info, $contrib_destination, $build_path, $make_dir);
  // Abort if there was an error processing projects.
  if ($projects === FALSE) {
    return FALSE;
  }

  // Core is built in place, rather than using make-process.
  if (!empty($projects['core']) && count($projects['core'])) {
    $project = current($projects['core']);
    $project = DrushMakeProject::getInstance('core', $project);
    $project->make();
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
      $invocations[$thread]['options']['manifest'] = array_keys($projects['contrib']);
    }
    if (!empty($invocations)) {
      // Backend options.
      $backend_options = array(
        'concurrency' => $concurrency,
        'method' => 'POST',
      );

      // Store projects in temporary files since passing this much data on the
      // pipe buffer can break on certain systems.
      _make_write_project_json($invocations);

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
      $results = drush_backend_invoke_concurrent($invocations, $common_options, $backend_options, 'make-process', '@none');
      if (count($results['error_log'])) {
        return FALSE;
      }
    }
  }
  return TRUE;
}

/**
 * Writes out project data to temporary files.
 *
 * @param array &$invocations
 *   An array containing projects sorted by thread.
 */
function _make_write_project_json(array &$invocations) {
  foreach ($invocations as $thread => $info) {
    $projects = $info['options']['projects'];
    unset($invocations[$thread]['options']['projects']);
    $temp_file = drush_tempnam('make_projects');
    file_put_contents($temp_file, json_encode($projects));
    $invocations[$thread]['options']['projects-location'] = $temp_file;
  }
}

/**
 * Gather additional data on all libraries specified in the make file.
 */
function make_prepare_libraries($recursion, $info, $contrib_destination = '', $build_path = '', $make_dir = '') {
  // Nothing to make if the libraries list is empty.
  if (empty($info['libraries'])) {
    return;
  }

  $libraries = array();
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
    $libraries[$key] = $library;
  }
  if (!$recursion) {
    drush_set_option('DRUSH_MAKE_LIBRARIES', $info['libraries']);
  }
  return $libraries;
}

/**
 * Process all libraries specified in the make file.
 */
function make_libraries($recursion, $contrib_destination, $info, $build_path, $make_dir) {
  $libraries = make_prepare_libraries($recursion, $info, $contrib_destination, $build_path, $make_dir);
  if (empty($libraries)) {
    return;
  }
  foreach ($libraries as $key => $library) {
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
  if ($build_path != '.' && file_exists($build_path) && !drush_get_option('no-core', FALSE)) {
    return drush_set_error('MAKE_PATH_EXISTS', dt('Base path %path already exists.', array('%path' => $build_path)));
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
  if ($build_path == '.' || (drush_get_option('no-core', FALSE) && file_exists($build_path))) {
    $info = drush_scan_directory($tmp_path . DIRECTORY_SEPARATOR . '__build__', '/./', array('.', '..'), 0, FALSE, 'filename', 0, TRUE);
    foreach ($info as $file) {
      $destination = $build_path . DIRECTORY_SEPARATOR . $file->basename;
      if (file_exists($destination)) {
        // To prevent the removal of top-level directories such as 'modules' or
        // 'themes', descend in a level if the file exists.
        // TODO: This only protects one level of directories from being removed.
        $overwrite = drush_get_option('overwrite', FALSE) ? FILE_EXISTS_OVERWRITE : FILE_EXISTS_MERGE;
        if (is_dir($destination)) {
          $files = drush_scan_directory($file->filename, '/./', array('.', '..'), 0, FALSE);
          foreach ($files as $file) {
            $ret = $ret && drush_copy_dir($file->filename, $destination . DIRECTORY_SEPARATOR . $file->basename, $overwrite);
          }
        }
        else {
          $ret = $ret && drush_copy_dir($file->filename, $destination, $overwrite);
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
    return drush_set_error('MAKE_CANNOT_MOVE_BUILD', dt("Cannot move build into place."));
  }
  return $ret;
}

/**
 * Create a request array suitable for release_info engine.
 *
 * This is a convenience function to easily integrate drush_make
 * with drush release_info engine.
 *
 * @todo: refactor 'make' to internally work with release_info keys.
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

/**
 * Enables caching if not explicitly disabled.
 *
 * @return bool
 *   The previous value of the 'cache' option.
 */
function _make_enable_cache() {
  $cache_before = drush_get_option('cache');
  if (!drush_get_option('no-cache', FALSE)) {
    drush_set_option('cache', TRUE);
  }
  return $cache_before;
}
