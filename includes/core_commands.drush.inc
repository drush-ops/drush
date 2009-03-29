<?php
// $Id$

/**
 * @file
 *   Core drush commands. See 'Drush Extras' project on drupal.org for more.
 */

/**
 * Implementation of hook_drush_command().
 *
 * In this hook, you specify which commands your
 * drush module makes available, what it does and 
 * description.
 *
 * Notice how this structure closely resembles how 
 * you define menu hooks.
 *
 * @return
 *   An associative array describing your command(s).
 */
function core_commands_drush_command() {
  $items = array();

  $items['help'] = array(
    'callback' => 'drush_usage',
    'description' => 'Print this help message.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
  );
  $items['cron'] = array(
    'callback' => 'core_commands_cron_run',
    'description' => 'Run all cron hooks.',
  );
  $items['status'] = array(
    'callback' => 'core_commands_status',
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
  );
  $items['script'] = array(
    'callback' => 'core_commands_script',
    'description' => "Run php script(s).",
    'examples' => array(
      'drush script update_variables.php',
    ),
    'arguments' => array(
      'path/to/script' => 'One or more file paths. Paths may be absolute or relative to the current working dir.',
    ),
  );
  $items['cache clear'] = array(
    'callback' => 'core_commands_cache_clear',
    'description' => 'Clear all caches.'
  );
  $items['dl'] = array(
    'callback' => 'core_commands_dl',
    'description' => 'Download core Drupal and modules like Drush Extras.',
    'examples' => array(
      'drush dl drupal' => 'Download latest version of Drupal core.', 
      'drush dl drupal-5.x' => 'Download latest version of Drupal core for 5.x release', 
      'drush dl diff-6.x-2.0' => 'Download a specfic version of diff module.',
      'drush dl views' => 'Download latest versions of Views for my version of Drupal.',
    ),
    'arguments' => array(
      'project' => '\'drupal\', or module name, with optional version.',
    ),
    'options' => array(
      '--destination' => 'Path to which the project will be copied.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );
  return $items;
}

/**
 * Implementation of hook_drush_help().
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-your-command>'
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function core_commands_drush_help($section) {
  switch ($section) {
    case 'drush:cron':
      return dt("Runs all cron hooks in all active modules for specified site.");
    case 'drush:status':
      return dt("View the Drupal version and DB credentials for the current site.");
    case 'drush:script':
      return dt("Runs the given php script(s) against the current Drupal site.");
    case 'drush:dl':
      return dt("Quickly download projects from drupal.org. Automatically figures out which module version you want based on its latest release, or you may specify a particular version. Downloads drupal core as well.  If no destination is provided, defaults to a site specific modules directory if available, then to sites/all/modules if available, then to the current working directory.");
    case 'error:DRUSH_DRUPAL_DB_ERROR' : 
      $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
      $message .= dt("Hint: This error often occurs when Drush is trying to bootstrap a site that has not been installed or does not have a configured database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_commands_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
    case 'error:DRUSH_DRUPAL_BOOTSTRAP_ERROR' :
      $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
      $message .= dt("Hint: This error can only occur once the database connection has already been succesfully initiated, therefor this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_commands_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
      break;
  }
}

function _core_commands_site_credentials() {
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal Root', $drupal_root);
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal version', drush_drupal_major_version());
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $credentials .= sprintf("  %-18s: %s\n", 'Site Path', $site_root);
      $credentials .= sprintf("  %-18s: %s\n", 'Site URI', drush_get_context('DRUSH_URI'));
      $db_driver = 'not defined';
      $db_host = 'not defined';
      $db_user = 'not defined';
      $db_name = 'not defined';
      switch (drush_drupal_major_version()) {
         case 5:
         case 6:
           if (isset($GLOBALS['db_url'])) {
             $parts = parse_url($GLOBALS['db_url']);
             $db_driver = $parts['scheme'];
             $db_user = $parts['user'];
             $db_host = $parts['host'];
             $db_name = trim($parts['path'], '/');
           }
           break;
         case 7:
           if (isset($GLOBALS['databases']['default'])) {
             $conn = $GLOBALS['databases']['default'];
             $db_driver = $conn['driver'];
             $db_user = $conn['username'];
             $db_host = $conn['host'];
             $db_name = $conn['database'];
           }
           break;
      }
      $credentials .= sprintf("  %-18s: %s\n", 'Database Driver', $db_driver);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Hostname', $db_host);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Username', $db_user);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Name', $db_name);
      return $credentials;
    }
  }
  return null;
}




/**
 * Command callback. Runs cron hooks.
 *
 * This is where the action takes place.
 *
 * In this function, all of Drupals API is (usually) available, including
 * any functions you have added in your own modules/themes.
 *
 * To print something to the terminal window, use drush_print().
 *
 */
function core_commands_cron_run() {
  drupal_cron_run();
  drush_print(dt('Cron run successfully.'));
}

/**
 * Command callback. Provides a birds-eye view of the current Drupal
 * installation.
 */
function core_commands_status() {
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    drush_print(dt("Drupal !version is installed at !DRUSH_DRUPAL_ROOT.", array('!version' => drush_drupal_version(), '!DRUSH_DRUPAL_ROOT' => $drupal_root)));
    if ($drush_uri = drush_get_context('DRUSH_URI')) {
      drush_print(dt("Current site: !DRUSH_URI", array('!DRUSH_URI' => $drush_uri)));
    }
    else {
      drush_print(dt("No site uri was determined. Using default site path."));
    }
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
      switch (drush_drupal_major_version()) {
        case 5:
        case 6:
          drush_print(dt("Current database: !db_url", array('!db_url' => $GLOBALS['db_url'])));
          break;
        case 7:
          $db_conn = Database::getConnectionInfo();
          drush_print(dt("Current database:"));
          foreach($db_conn['default'] as $key => $value) {
            drush_print(dt("  !key => !value", array('!key' => $key, '!value' => $value)));
          }
          break;
        default:
          drush_print(dt("No database information available."));
          break;
      }
    }
    else {
      drush_print(dt("Site not found or not working."));
    }
  }
  else {
    drush_print(dt("No Drupal installation was found."));
  }
}

/**
 * Command callback. Runs "naked" php scripts.
 */
function core_commands_script() {
  $scripts = func_get_args();
  foreach ($scripts as $script) {
    $script_filename = drush_cwd() . '/' . $script;
    if (file_exists($script_filename)) {
      include($script_filename);
    }
  }
}

function core_commands_cache_clear() {
  switch (drush_drupal_major_version()) {
    case 5:
      // clear preprocessor cache
      drupal_clear_css_cache();

      // clear core tables
      $core = array('cache', 'cache_filter', 'cache_menu', 'cache_page');
      $alltables = array_merge($core, module_invoke_all('devel_caches'));
      foreach ($alltables as $table) {
        cache_clear_all('*', $table, TRUE);
      }
      drush_print(dt('Cache cleared.'));
      break;
    case 6:
    case 7:
    default:
      drupal_flush_all_caches();
      drush_print(dt('Cache cleared.'));
      break;
  }
}

function core_commands_dl_destination($type) {
  $destination = drush_get_option('destination');
  if (!empty($destination)) {
    $destination = rtrim($destination, '/') . '/';
    if (is_dir($destination)) {
      return $destination;
    }
    else {
      drush_set_error('DRUSH_PM_NO_DESTINATION', dt('The destination directory !destination does not appear to exist.', array('!destination' => $destination)));
      exit();
    }
  }
  
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  
  // If a URI is provided and we bootstrapped successfully then we install to
  // that specific site, otherwise we install to sites/all/modules
  if (drush_get_context('DRUSH_URI') && function_exists('conf_path')) {
    $sitepath = $drupal_root .'/'. conf_path() .'/';
  }
  else if ($drupal_root) {
    $sitepath = $drupal_root . '/sites/all/';
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
// TODO: rename
function drush_pm_parse_project_version($requests) {
  $requestdata = array();
  foreach($requests as $request) {
    // project-HEAD or project-5.x-1.0-beta
    // '5.x-' is optional, as is '-beta'
    preg_match('/-(HEAD|(\d+)\.([\dx]+)(-.+)?)$/', $request, $matches);
    if (!empty($matches[0])) {
      // Specific version requested
      $version = $matches[0];
      $project = substr($request, 0, strlen($request) - strlen($version));
    }
    else {
      // Recommended stable version requested
      $project = $request;
      $version = NULL;
    }
    if (empty($project)) {
      drush_set_error('DRUSH_PM_MISSING_PROJECT_NAME', 'Project name not found. Run drush help pm install for more information.');
    }
    $requestdata[$project] = array(
      'name' => $project,
      'version' => trim($version, ' -'),
    );
  }
  return $requestdata;
}

function core_commands_drush_dl_project_types() {
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
 * Command callback. Download drupal core.
 */
function core_commands_dl($full_project = NULL) {
  if (empty($full_project)) {
    return drush_set_error('DRUSH_DL_EMPTY_PROJECT', "Please tell me what to download. (eg. `drush dl views` or `drush dl diff-6.x-2.0`)");
  }

  // Try to bootstrap to config phase so we can determine destination.
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_ROOT);

  $package_handler = 'drush_pm_wget_install_project';
  if (function_exists('drush_pm_get_package_handler')) {
    $package_handler = drush_pm_get_package_handler() .'_install_project';
    if (!function_exists($package_handler)) {
      return drush_set_error('DRUSH_PM_HANDLER_NON_INSTALL', dt('The !package_handler package handler does not handle installs.', array('!package_handler' => $package_handler)));
    }
  }

  $requestdata = drush_pm_parse_project_version(explode(' ', $full_project));

  $project_types = core_commands_drush_dl_project_types();
  $project_types_xpath = '(value="' . implode('" or value="', $project_types) . '")';
  foreach ($requestdata as $package) {
    $version_default = drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION') ? drush_get_context('DRUSH_DRUPAL_MAJOR_VERSION') : 6;
    $version_default .= '.x';

    $version = empty($package['version']) ? $version_default : $package['version'];
    $project = $package['name'];
    $major_version = ($project == 'drupal') ? substr($version, 0, 1) .'.x' : substr($version, 0, 3);
    // Don't rely on UPDATE_DEFAULT_URL since we are not fully bootstrapped.
    $url = 'http://updates.drupal.org/release-history' . "/$project/". $major_version;
    if ($xml = @simplexml_load_file($url)) {
      if ($error = $xml->xpath('/error')) {
        drush_set_error('DRUSH_PM_COULD_NOT_LOAD_UPDATE_FILE', $error[0]);
      }
      else {
        // Try to get the specified release.
        if ($package['version']) {
          if ($releases = $xml->xpath("/project/releases/release[version='$version']")) {
            $release = (array)$releases[0];
          }
        }
        // If that did not work, get the first listed release.
        if (empty($release)) {
          $release = (array)$xml->releases->release;
        }
        // Determine what type of project we have, so we know where to put it.
        $release['type'] = 'module';
        
        if ($types = $xml->xpath('/project/terms/term[name="Projects" and ' . $project_types_xpath . ']')) {
          $release['type'] = array_search($types[0]->value, $project_types);
        }
        $destination = core_commands_dl_destination($release['type']);
        
        if ($package_handler($project, $release, $destination)) {
          drush_print(dt("Project !project (!version) successfully downloaded to !dest.",
                         array('!project' => $project, '!version' => $release['version'], '!dest' => $destination)));
          drush_command_invoke_all('drush_pm_post_install', $project, $release, $destination);
        }
      }
    }
    else {
      // We are not getting here since drupal.org always serves an XML response.
      drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', dt('Could not download project status information from !url', array('!url' => $url)));
    }
  }
}
