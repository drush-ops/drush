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

  $items['cron'] = array(
    'callback' => 'core_commands_cron_run',
    'description' => 'Run all cron hooks.',
  );
  $items['status'] = array(
    'callback' => 'core_commands_status',
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'core' => NULL,
    'bootstrap' => 'system',
  );
  $items['script'] = array(
    'callback' => 'core_commands_script',
    'description' => "Run php script(s).",
  );
  $items['cache clear'] = array(
    'callback' => 'core_commands_cache_clear',
    'description' => 'Clear all caches.'
  );
  $items['extras'] = array(
    'callback' => 'core_commands_extras',
    'description' => 'Download the Drush extras commands.'
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
      return dt("Runs the given php scripts against the current Drupal site. Pass one or more file paths as arguments. Paths may be absolute or relative to the current working directory.");
      case 'drush:extras':
        return dt("Download the Drush Extras project to the ~/.drush directory.");
  }
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
  if (defined('DRUSH_DRUPAL_ROOT')) {
    drush_print(dt("Drupal !version is installed.", array('!version' => drush_drupal_version())));
    if (DRUSH_URI != '') {
      drush_print(dt("Current site: !DRUSH_URI", array('!DRUSH_URI' => DRUSH_URI)));
    }
    else {
      drush_print(dt("No site uri was determined. Using default site path."));
    }
    if (defined('DRUSH_DRUPAL_BOOTSTRAPPED')) {
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

/**
 * Command callback. Download drush extras project.
 */
function core_commands_extras() {
  $destination = core_commands_extras_destination();
  $project = 'drush_extras';
  $url = UPDATE_DEFAULT_URL . "/$project/". DRUPAL_CORE_COMPATIBILITY;
  $xml = simplexml_load_file($url);
  $info = array(
    'download_link' => (string)$xml->releases->release->download_link,
    'mdhash' => (string)$xml->releases->release->mdhash,
  );
  drush_pm_wget_install_project($project, $info, $destination);
}

function core_commands_extras_destination() {
  // If a URI is provided then we install to that specific site, otherwise we install to sites/all/modules
  if (DRUSH_URI) {
    $path = conf_path();
    $modulepath = DRUSH_DRUPAL_ROOT .'/'. $path .'/modules/';
  }

  if (!isset($modulepath) || !file_exists($modulepath)) {
    $modulepath = DRUSH_DRUPAL_ROOT .'/sites/all/modules/';
  }
  
  return $modulepath;
}


