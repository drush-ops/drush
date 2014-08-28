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

/**
 * No bootstrap.
 *
 * This constant is only used to indicate that the bootstrap process has
 * not started yet.  It is not possible to have no bootstrap.
 */
define('DRUSH_BOOTSTRAP_NONE', -1);

/**
 * Use drush_bootstrap_max instead of drush_bootstrap_to_phase
 *
 * This constant is only usable as the value of the 'bootstrap'
 * item of a command object, or as the parameter to
 * drush_bootstrap_to_phase.  It is not a real bootstrap state.
 */
define('DRUSH_BOOTSTRAP_MAX', -2);

/**
 * @deprecated
 *
 * No longer used, but 0 remains reserved. Drush always runs preflight.
 * Commands may alternatively use DRUSH_BOOTSTRAP_NONE.
 */
define('DRUSH_BOOTSTRAP_DRUSH', 0);

/**
 * Set up and test for a valid drupal root, either through the -r/--root options,
 * or evaluated based on the current working directory.
 *
 * Any code that interacts with an entire Drupal installation, and not a specific
 * site on the Drupal installation should use this bootstrap phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_ROOT',  1);

/**
 * Set up a Drupal site directory and the correct environment variables to
 * allow Drupal to find the configuration file.
 *
 * If no site is specified with the -l / --uri options, Drush will assume the
 * site is 'default', which mimics Drupal's behaviour.
 *
 * If you want to avoid this behaviour, it is recommended that you use the
 * DRUSH_BOOTSTRAP_DRUPAL_ROOT bootstrap phase instead.
 *
 * Any code that needs to modify or interact with a specific Drupal site's
 * settings.php file should bootstrap to this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_SITE', 2);

/**
 * Load the settings from the Drupal sites directory.
 *
 * This phase is analagous to the DRUPAL_BOOTSTRAP_CONFIGURATION bootstrap phase in Drupal
 * itself, and this is also the first step where Drupal specific code is included.
 *
 * This phase is commonly used for code that interacts with the Drupal install API,
 * as both install.php and update.php start at this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION', 3);

/**
 * Connect to the Drupal database using the database credentials loaded
 * during the previous bootstrap phase.
 *
 * This phase is analogous to the DRUPAL_BOOTSTRAP_DATABASE bootstrap phase in
 * Drupal.
 *
 * Any code that needs to interact with the Drupal database API needs to
 * be bootstrapped to at least this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_DATABASE', 4);

/**
 * Fully initialize Drupal.
 *
 * This is analogous to the DRUPAL_BOOTSTRAP_FULL bootstrap phase in
 * Drupal.
 *
 * Any code that interacts with the general Drupal API should be
 * bootstrapped to this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_FULL', 5);

/**
 * Log in to the initialiased Drupal site.
 *
 * This is the default bootstrap phase all commands will try to reach,
 * unless otherwise specified.
 *
 * This bootstrap phase is used after the site has been
 * fully bootstrapped.
 *
 * This phase will log you in to the drupal site with the username
 * or user ID specified by the --user/ -u option.
 *
 * Use this bootstrap phase for your command if you need to have access
 * to information for a specific user, such as listing nodes that might
 * be different based on who is logged in.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_LOGIN', 6);

/**
 * Helper function listing phases.
 *
 * For commands that need to iterate through the phases, such as help
 */
function _drush_bootstrap_phases($function_names = FALSE, $init_phases_only = FALSE) {
  static $functions = array(
    DRUSH_BOOTSTRAP_DRUSH                  => '_drush_bootstrap_drush',
    DRUSH_BOOTSTRAP_DRUPAL_ROOT            => '_drush_bootstrap_drupal_root',
    DRUSH_BOOTSTRAP_DRUPAL_SITE            => '_drush_bootstrap_drupal_site',
    DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION   => '_drush_bootstrap_drupal_configuration',
    DRUSH_BOOTSTRAP_DRUPAL_DATABASE        => '_drush_bootstrap_drupal_database',
    DRUSH_BOOTSTRAP_DRUPAL_FULL            => '_drush_bootstrap_drupal_full',
    DRUSH_BOOTSTRAP_DRUPAL_LOGIN           => '_drush_bootstrap_drupal_login');

  $result = array();
  if ($init_phases_only) {
    foreach (array(DRUSH_BOOTSTRAP_DRUSH, DRUSH_BOOTSTRAP_DRUPAL_FULL) as $phase) {
      $result[$phase] = $functions[$phase];
    }
  }
  else {
    $result = $functions;
  }
  if (!$function_names) {
    $result = array_keys($result);
  }
  return $result;
}

/**
 * @} End of Drush bootstrap phases.
 */

/**
 * Bootstrap Drush to the desired phase.
 *
 * This function will sequentially bootstrap each
 * lower phase up to the phase that has been requested.
 *
 * @param phase
 *   The bootstrap phase to bootstrap to.
 *   Any of the following constants :
 *     DRUSH_BOOTSTRAP_DRUSH                = Only Drush.
 *     DRUSH_BOOTSTRAP_DRUPAL_ROOT          = Find a valid Drupal root.
 *     DRUSH_BOOTSTRAP_DRUPAL_SITE          = Find a valid Drupal site.
 *     DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION = Load the site's settings.
 *     DRUSH_BOOTSTRAP_DRUPAL_DATABASE      = Initialize the database.
 *     DRUSH_BOOTSTRAP_DRUPAL_FULL          = Initialize Drupal fully.
 *     DRUSH_BOOTSTRAP_DRUPAL_LOGIN         = Log into Drupal with a valid user.
 */
function drush_bootstrap($phase, $phase_max = FALSE) {
  static $phases;
  if (!$phases) {
    $phases = _drush_bootstrap_phases(TRUE);
  }
  static $phase_index = 0;

  drush_set_context('DRUSH_BOOTSTRAPPING', TRUE);
  while ($phase >= $phase_index && isset($phases[$phase_index])) {
    if (drush_bootstrap_validate($phase_index)) {
      $current_phase = $phases[$phase_index];
      if (function_exists($current_phase) && !drush_get_error()) {
        drush_log(dt("Drush bootstrap phase : !function()", array('!function' => $current_phase)), 'bootstrap');
        $current_phase();

        // Find any command files that are available during this bootstrap phase.
        _drush_find_commandfiles($phase_index, $phase_max);
      }
      drush_set_context('DRUSH_BOOTSTRAP_PHASE', $phase_index);
    }
    else {
      $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
      foreach ($errors as $code => $message) {
        drush_set_error($code, $message);
      }
      break;
    }
    unset($phases[$phase_index++]);
  }
  drush_set_context('DRUSH_BOOTSTRAPPING', FALSE);
  return !drush_get_error();
}

/**
 * Determine whether a given bootstrap phase has been completed
 *
 * This function name has a typo which makes me laugh so we choose not to
 * fix it. Take a deep breath, and smile. See
 * http://en.wikipedia.org/wiki/HTTP_referer
 *
 *
 * @param phase
 *   The bootstrap phase to test
 *
 * @returns
 *   TRUE if the specified bootstrap phase has completed.
 */
function drush_has_boostrapped($phase) {
  $phase_index = drush_get_context('DRUSH_BOOTSTRAP_PHASE');

  return isset($phase_index) && ($phase_index >= $phase);
}

/**
 * Validate whether a bootstrap phase can be reached.
 *
 * This function will validate the settings that will be used
 * during the actual bootstrap process, and allow commands to
 * progressively bootstrap to the highest level that can be reached.
 *
 * This function will only run the validation function once, and
 * store the result from that execution in a local static. This avoids
 * validating phases multiple times.
 *
 * @param phase
 *   The bootstrap phase to validate to.
 *   Any of the following constants :
 *     DRUSH_BOOTSTRAP_DRUSH                = Only Drush.
 *     DRUSH_BOOTSTRAP_DRUPAL_ROOT          = Find a valid Drupal root.
 *     DRUSH_BOOTSTRAP_DRUPAL_SITE          = Find a valid Drupal site.
 *     DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION = Load the site's settings.
 *     DRUSH_BOOTSTRAP_DRUPAL_DATABASE      = Initialize the database.
 *     DRUSH_BOOTSTRAP_DRUPAL_FULL          = Initialize Drupal fully.
 *     DRUSH_BOOTSTRAP_DRUPAL_LOGIN         = Log into Drupal with a valid user.
 *
 * @return
 *   True if bootstrap is possible, False if the validation failed.
 *
 */
function drush_bootstrap_validate($phase) {
  static $phases;
  static $result_cache = array();
  if (!$phases) {
    $phases = _drush_bootstrap_phases(TRUE);
  }
  static $phase_index = 0;
  if (!array_key_exists($phase, $result_cache)) {
    drush_set_context('DRUSH_BOOTSTRAP_ERRORS', array());
    drush_set_context('DRUSH_BOOTSTRAP_VALUES', array());

    while ($phase >= $phase_index && isset($phases[$phase_index])) {
      $current_phase = $phases[$phase_index] . '_validate';
      if (function_exists($current_phase)) {
        $result_cache[$phase_index] = $current_phase();
      }
      else {
        $result_cache[$phase_index] = TRUE;
      }
      drush_set_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', $phase_index);
      unset($phases[$phase_index++]);
    }
  }
  return $result_cache[$phase];
}

/**
 * Bootstrap to the specified phase.
 *
 * @param $max_phase_index
 *   Only attempt bootstrap to the specified level.
 */
function drush_bootstrap_to_phase($max_phase_index) {
  // If $max_phase_index is DRUSH_BOOTSTRAP_MAX, then
  // we will bootstrap as far as we can.  drush_bootstrap_max
  // is different than drush_bootstrap_to_phase in that
  // it is not an error if DRUSH_BOOTSTRAP_LOGIN is not reached.
  if ($max_phase_index == DRUSH_BOOTSTRAP_MAX) {
    drush_bootstrap_max();
    return TRUE;
  }

  drush_log(dt("Bootstrap to phase !phase.", array('!phase' => $max_phase_index)), 'bootstrap');
  $phases = _drush_bootstrap_phases();
  $result = TRUE;

  // Try to bootstrap to the maximum possible level, without generating errors
  foreach ($phases as $phase_index) {
    if ($phase_index > $max_phase_index) {
      // Stop trying, since we achieved what was specified.
      break;
    }

    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE)) {
        $result = drush_bootstrap($phase_index, $max_phase_index);
      }
    }
    else {
      $result = FALSE;
      break;
    }
  }

  return $result;
}

/**
 * Bootstrap to the highest level possible, without triggering any errors.
 *
 * @param $max_phase_index
 *   Only attempt bootstrap to the specified level.
 *
 *  @return int
 *    The maximum phase to which we bootstrapped.
 */
function drush_bootstrap_max($max_phase_index = FALSE) {
  $phases = _drush_bootstrap_phases();
  $phase_index = DRUSH_BOOTSTRAP_DRUSH;
  if (!$max_phase_index) {
    $max_phase_index = count($phases);
  }

  // Try to bootstrap to the maximum possible level, without generating errors
  foreach ($phases as $phase_index) {
    if ($phase_index > $max_phase_index) {
      // Stop trying, since we achieved what was specified.
      break;
    }

    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
        drush_bootstrap($phase_index, $max_phase_index);
      }
    }
    else {
      break;
    }
  }

  return drush_get_context('DRUSH_BOOTSTRAP_PHASE');
}

/**
 * Bootstrap the specified site alias.  The site alias must
 * be a valid alias to a local site.
 *
 * @param $site_record
 *   The alias record for the given site alias.
 *   @see drush_sitealias_get_record().
 * @param $max_phase_index
 *   Only attempt bootstrap to the specified level.
 * @returns TRUE if attempted to bootstrap, or FALSE
 *   if no bootstrap attempt was made.
 */
function drush_bootstrap_max_to_sitealias($site_record, $max_phase_index = NULL) {
  if ((array_key_exists('root', $site_record) && !array_key_exists('remote-host', $site_record))) {
    drush_sitealias_set_alias_context($site_record);
    drush_bootstrap_max($max_phase_index);
    return TRUE;
  }
  return FALSE;
}

/**
 * Helper function to collect any errors that occur during the bootstrap process.
 * Always returns FALSE, for convenience.
 */
function drush_bootstrap_error($code, $message = null) {
  $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS');
  $errors[$code] = $message;
  drush_set_context('DRUSH_BOOTSTRAP_ERRORS', $errors);
  return FALSE;
}

function _drush_bootstrap_output_prepare() {
  $backend = drush_get_context('DRUSH_BACKEND');
  $quiet = drush_get_context('DRUSH_QUIET');

  if ($backend) {
    // Load options passed as a JSON encoded string through STDIN.
    $stdin_options = _drush_backend_get_stdin();
    if (is_array($stdin_options)) {
      drush_set_context('stdin', $stdin_options);
    }
    // Add an output buffer handler to collect output/pass through backend
    // packets. Using a chunksize of 2 ensures that each line is flushed
    // straight away.
    if ($quiet) {
      // Pass through of backend packets, discard regular output.
      ob_start('drush_backend_output_discard', 2);
    }
    else {
      // Collect output.
      ob_start('drush_backend_output_collect', 2);
    }
  }

  // In non-backend quiet mode we start buffering and discards it on command
  // completion.
  if ($quiet && !$backend) {
    ob_start();
  }
}

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

  drush_bootstrap_value('drupal_root', $drupal_root);
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
    drush_set_error('DRUSH_DRUPAL_VERSION_UNSUPPORTED', dt('Drush !drush_version does not support Drupal !major_version. See http://drupal.org/project/drush for more information.', array('!drush_version' => DRUSH_VERSION, '!major_version' => $major_version)));
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

  $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
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

  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

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
  drush_log(dt("Successfully connected to the Drupal database."), 'bootstrap');
  // D8 omits this bootstrap level as nothing special needs to be done.
  if (drush_drupal_major_version() <= 7) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
  }
}

/**
 * Attempt to load the full Drupal system.
 */
function _drush_bootstrap_drupal_full() {
  if (!drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_start();
  }
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  if (!drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_end_clean();
  }

  // Unset drupal error handler and restore drush's one.
  if (drush_drupal_major_version() == 6) {
    restore_error_handler();
  }

  // If needed, prod module_implements() to recognize our system_watchdog() implementation.
  drush_include_engine('drupal', 'environment');
  $dogs = drush_module_implements('watchdog');
  if (!in_array('system', $dogs)) {
    // Note that this resets module_implements cache.
    drush_module_implements('watchdog', FALSE, TRUE);
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
  $drush_user = drush_set_context('DRUSH_USER', drush_get_option('user', 0));

  drush_drupal_login($drush_user);
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
