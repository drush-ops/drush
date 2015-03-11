<?php

/**
 * @file
 *
 * Drupal bootstrapping code.
 *
 * Functions here are used to bootstrap Drupal to
 * a level sufficient so that we can run commands.
 */

/**
 * @name Drupal bootstrap phases
 * @{
 * Sequential Drupal bootstrapping phases.
 */

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DrupalKernel;

/**
 * Validate the DRUSH_BOOTSTRAP_DRUPAL_ROOT phase.
 *
 * In this function, we will check if a valid Drupal directory is available.
 * We also determine the value that will be stored in the DRUSH_DRUPAL_ROOT
 * context and DRUPAL_ROOT constant if it is considered a valid option.
 */
function _drush_bootstrap_drupal_root_validate() {
  $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');

  if (empty($drupal_root)) {
    return drush_bootstrap_error('DRUSH_NO_DRUPAL_ROOT', dt("A Drupal installation directory could not be found"));
  }
  if (!$signature = drush_valid_drupal_root($drupal_root)) {
    return drush_bootstrap_error('DRUSH_INVALID_DRUPAL_ROOT', dt("The directory !drupal_root does not contain a valid Drupal installation", array('!drupal_root' => $drupal_root)));
  }

  drush_bootstrap_value('drupal_root', realpath($drupal_root));
  define('DRUSH_DRUPAL_SIGNATURE', $signature);

  return TRUE;
}

/**
 * Bootstrap Drush with a valid Drupal Directory.
 *
 * In this function, the pwd will be moved to the root
 * of the Drupal installation.
 *
 * The DRUSH_DRUPAL_ROOT context, DRUSH_DRUPAL_CORE context, DRUPAL_ROOT, and the
 * DRUSH_DRUPAL_CORE constants are populated from the value that we determined during
 * the validation phase.
 *
 * We also now load the drushrc.php for this specific Drupal site.
 * We can now include files from the Drupal Tree, and figure
 * out more context about the platform, such as the version of Drupal.
 */
function _drush_bootstrap_drupal_root() {
  // Load the config options from Drupal's /drush and sites/all/drush directories.
  drush_load_config('drupal');

  $drupal_root = drush_set_context('DRUSH_DRUPAL_ROOT', drush_bootstrap_value('drupal_root'));
  chdir($drupal_root);
  $version = drush_drupal_version();
  $major_version = drush_drupal_major_version();

  if ($major_version <= 5) {
    drush_set_error('DRUSH_DRUPAL_VERSION_UNSUPPORTED', dt('Drush !drush_version does not support Drupal !major_version.', array('!drush_version' => DRUSH_VERSION, '!major_version' => $major_version)));
  }
  else {
    if ($major_version <= 7) {
      define('DRUPAL_ROOT', $drupal_root);
      $core = DRUPAL_ROOT;
    }
    else {
      $core = DRUPAL_ROOT . '/core';
    }

    // DRUSH_DRUPAL_CORE should point to the /core folder in Drupal 8+ or to DRUPAL_ROOT
    // in prior versions.
    drush_set_context('DRUSH_DRUPAL_CORE', $core);
    define('DRUSH_DRUPAL_CORE', $core);

    _drush_preflight_global_options();

    drush_log(dt("Initialized Drupal !version root directory at !drupal_root", array("!version" => $version, '!drupal_root' => $drupal_root)));
  }
}

/**
 * Find the URI that has been selected by the cwd
 * if it was not previously set via the --uri / -l option
 */
function _drush_bootstrap_selected_uri() {
  $uri = drush_get_context('DRUSH_SELECTED_URI');
  if (empty($uri)) {
    $site_path = drush_site_path();
    $elements = explode('/', $site_path);
    $current = array_pop($elements);
    if (!$current) {
      $current = 'default';
    }
    $uri = 'http://'. $current;
    $uri = drush_set_context('DRUSH_SELECTED_URI', $uri);
    drush_sitealias_create_self_alias();
  }

  return $uri;
}

/**
 * VALIDATE the DRUSH_BOOTSTRAP_DRUPAL_SITE phase.
 *
 * In this function we determine the URL used for the command,
 * and check for a valid settings.php file.
 *
 * To do this, we need to set up the $_SERVER environment variable,
 * to allow us to use conf_path to determine what Drupal will load
 * as a configuration file.
 */
function _drush_bootstrap_drupal_site_validate() {
  // Define the selected conf path as soon as we have identified that
  // we have selected a Drupal site.  Drush used to set this context
  // during the drush_bootstrap_drush phase.
  $drush_uri = _drush_bootstrap_selected_uri();
  drush_set_context('DRUSH_SELECTED_DRUPAL_SITE_CONF_PATH', drush_conf_path($drush_uri));

  // Fake the necessary HTTP headers that Drupal needs:
  if ($drush_uri) {
    $drupal_base_url = parse_url($drush_uri);
    // If there's no url scheme set, add http:// and re-parse the url
    // so the host and path values are set accurately.
    if (!array_key_exists('scheme', $drupal_base_url)) {
      $drush_uri = 'http://' . $drush_uri;
      $drupal_base_url = parse_url($drush_uri);
    }
    // Fill in defaults.
    $drupal_base_url += array(
      'path' => NULL,
      'host' => NULL,
      'port' => NULL,
    );
    $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];

    if ($drupal_base_url['scheme'] == 'https') {
      $_SERVER['HTTPS'] = 'on';
    }

    if ($drupal_base_url['port']) {
      $_SERVER['HTTP_HOST'] .= ':' . $drupal_base_url['port'];
    }
    $_SERVER['SERVER_PORT'] = $drupal_base_url['port'];

    if (array_key_exists('path', $drupal_base_url)) {
      $_SERVER['PHP_SELF'] = $drupal_base_url['path'] . '/index.php';
    }
    else {
      $_SERVER['PHP_SELF'] = '/index.php';
    }
  }
  else {
    $_SERVER['HTTP_HOST'] = 'default';
    $_SERVER['PHP_SELF'] = '/index.php';
  }

  $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
  $_SERVER['REQUEST_URI'] = '/';
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  $_SERVER['REQUEST_METHOD']  = NULL;

  $_SERVER['SERVER_SOFTWARE'] = NULL;
  $_SERVER['HTTP_USER_AGENT'] = NULL;
  $_SERVER['SCRIPT_FILENAME'] = DRUPAL_ROOT . '/index.php';

  $site = drush_bootstrap_value('site', $_SERVER['HTTP_HOST']);

  $conf_path = drush_bootstrap_value('conf_path', conf_path(TRUE, TRUE));
  $conf_file = "$conf_path/settings.php";
  if (!file_exists($conf_file)) {
    return drush_bootstrap_error('DRUPAL_SITE_SETTINGS_NOT_FOUND', dt("Could not find a Drupal settings.php file at !file.",
       array('!file' => $conf_file)));
  }

  return TRUE;
}

/**
 * Called by _drush_bootstrap_drupal_site to do the main work
 * of the drush drupal site bootstrap.
 */
function _drush_bootstrap_do_drupal_site() {
  $drush_uri = drush_get_context('DRUSH_SELECTED_URI');
  drush_set_context('DRUSH_URI', $drush_uri);
  $site = drush_set_context('DRUSH_DRUPAL_SITE', drush_bootstrap_value('site'));
  $conf_path = drush_set_context('DRUSH_DRUPAL_SITE_ROOT', drush_bootstrap_value('conf_path'));

  drush_log(dt("Initialized Drupal site !site at !site_root", array('!site' => $site, '!site_root' => $conf_path)));

  _drush_preflight_global_options();
}

/**
 * Initialize a site on the Drupal root.
 *
 * We now set various contexts that we determined and confirmed to be valid.
 * Additionally we load an optional drushrc.php file in the site directory.
 */
function _drush_bootstrap_drupal_site() {
  drush_load_config('site');
  _drush_bootstrap_do_drupal_site();
}

/**
 * Initialize and load the Drupal configuration files.
 *
 * We process and store a normalized set of database credentials
 * from the loaded configuration file, so we can validate them
 * and access them easily in the future.
 *
 * Also override Drupal variables as per --variables option.
 */
function _drush_bootstrap_drupal_configuration() {
  global $conf;

  if (drush_drupal_major_version() <= 7) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);
  }
  else {
    drupal8_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }

  // Unset drupal error handler and restore drush's one.
  if (drush_drupal_major_version() >= 7) {
    restore_error_handler();
  }

  $override = array(
    'dev_query' => FALSE, // Force Drupal6 not to store queries since we are not outputting them.
    'cron_safe_threshold' => 0, // Don't run poormanscron during Drush request (D7+).
  );

  $current_override = drush_get_option_list('variables');
  foreach ($current_override as $name => $value) {
    if (is_numeric($name) && (strpos($value, '=') !== FALSE)) {
      list($name, $value) = explode('=', $value, 2);
    }
    $override[$name] = $value;
  }
  $conf = is_array($conf) ? array_merge($conf, $override) : $conf;
}

/**
 * Validate the DRUSH_BOOTSTRAP_DRUPAL_DATABASE phase
 *
 * Attempt to make a working database connection using the
 * database credentials that were loaded during the previous
 * phase.
 */
function _drush_bootstrap_drupal_database_validate() {
  if (!drush_valid_db_credentials()) {
    return drush_bootstrap_error('DRUSH_DRUPAL_DB_ERROR');
  }
  return TRUE;
}

/**
 * Boostrap the Drupal database.
 */
function _drush_bootstrap_drupal_database() {
  // D8 omits this bootstrap level as nothing special needs to be done.
  if (drush_drupal_major_version() <= 7) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
  }
  drush_log(dt("Successfully connected to the Drupal database."), 'bootstrap');
}

/**
 * Attempt to load the full Drupal system.
 */
function _drush_bootstrap_drupal_full() {
  if (!drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_start();
  }
  if (drush_drupal_major_version() <= 7) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  }
  else {
    drupal8_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  }
  if (!drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_end_clean();
  }

  // Unset drupal error handler and restore drush's one.
  if (drush_drupal_major_version() == 6) {
    restore_error_handler();
  }

  drush_include_engine('drupal', 'environment');

  // If we're running on Drupal 8 or later, we provide a logger which will send
  // output to drush_log(). This should catch every message logged through every
  // channel.
  if (drush_drupal_major_version() >= 8) {
    \Drupal::getContainer()->get('logger.factory')->addLogger(new \Drush\Log\DrushLog);
  }
  else {
    // If needed, prod module_implements() to recognize our system_watchdog() implementation.
    $dogs = drush_module_implements('watchdog');
    if (!in_array('system', $dogs)) {
      // Note that this resets module_implements cache.
      drush_module_implements('watchdog', FALSE, TRUE);
    }
  }

  // Write correct install_profile to cache as needed. Used by _drush_find_commandfiles().
  $cid = drush_cid_install_profile();
  if ($cached_install_profile = drush_cache_get($cid)) {
    // We have a cached profile. Check it for correctness and save new value if needed.
    $install_profile = drush_drupal_major_version() >= 7 ? drupal_get_profile() : variable_get('install_profile', 'standard');
    if ($cached_install_profile->data != $install_profile) {
      drush_cache_set($cid, $install_profile);
    }
  }
  else {
    // No cached entry so write to cache.
    $install_profile = drush_drupal_major_version() >= 7 ? drupal_get_profile() : variable_get('install_profile', 'standard');
    drush_cache_set($cid, $install_profile);
  }

  _drush_log_drupal_messages();
}

/**
 * Log into the bootstrapped Drupal site with a specific
 * username or user id.
 */
function _drush_bootstrap_drupal_login() {
  $uid_or_name = drush_set_context('DRUSH_USER', drush_get_option('user', 0));
  $userversion = drush_user_get_class();
  if (!$account = $userversion->load_by_uid($uid_or_name)) {
    if (!$account = $userversion->load_by_name($uid_or_name)) {
      if (is_numeric($uid_or_name)) {
        $message = dt('Could not login with user ID !user.', array('!user' => $uid_or_name));
        if ($uid_or_name === 0) {
          $message .= ' ' . dt('This is typically caused by importing a MySQL database dump from a faulty tool which re-numbered the anonymous user ID in the users table. See !link for help recovering from this situation.', array('!link' => 'http://drupal.org/node/1029506'));
        }
      }
      else {
        $message = dt('Could not login with user account `!user\'.', array('!user' => $uid_or_name));
      }
      return drush_set_error('DRUPAL_USER_LOGIN_FAILED', $message);
    }
  }
  $userversion->setCurrentUser($account);
  _drush_log_drupal_messages();
}

/**
 * Helper function to store any context settings that are being validated.
 */
function drush_bootstrap_value($context, $value = null) {
  $values =& drush_get_context('DRUSH_BOOTSTRAP_VALUES', array());

  if (isset($value)) {
    $values[$context] = $value;
  }

  if (array_key_exists($context, $values)) {
    return $values[$context];
  }

  return null;
}


/**
 * Ensures Drupal 8 is bootstrapped to the specified phase.
 *
 * In order to bootstrap Drupal from another PHP script, you can use this code:
 * @code
 *   require_once '/path/to/drupal/core/vendor/autoload.php';
 *   require_once '/path/to/drupal/core/includes/bootstrap.inc';
 *   drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
 * @endcode
 *
 * @param $phase
 *   A constant telling which phase to bootstrap to. Possible values:
 *   - DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION: Initializes configuration and
 *     kernel.
 *   - DRUSH_BOOTSTRAP_DRUPAL_FULL: Boots the kernel.
 */
function drupal8_bootstrap($phase = NULL) {
  // Temporary variables used for booting later legacy phases.
  /** @var \Drupal\Core\DrupalKernel $kernel */
  static $kernel;
  static $boot_level = 0;

  if (isset($phase)) {
    $request = Request::createFromGlobals();
    for ($current_phase = $boot_level; $current_phase <= $phase; $current_phase++) {

      switch ($current_phase) {
        case DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION:
          $classloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
          $kernel = DrupalKernel::createFromRequest($request, $classloader, 'prod');
          break;

        case DRUSH_BOOTSTRAP_DRUPAL_FULL:
          $kernel->boot();
          $kernel->prepareLegacyRequest($request);
          break;
      }
    }
    $boot_level = $phase;
  }
}
