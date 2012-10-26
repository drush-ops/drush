<?php

/**
 * @file
 *
 * Drush bootstrapping code.
 *
 * Functions here are used to bootstrap drush and then maybe Drupal to
 * a level sufficient so that we can run commands. This is also where
 * third party extensions can bootstrap drush without going through
 * drush.php (this is used in the unit tests for example).
 */

/**
 * @name Drush bootstrap phases
 * @{
 * Sequential Drush bootstrapping phases.
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
 * Only bootstrap Drush, without any Drupal specific code.
 *
 * Any code that operates on the Drush installation, and not specifically
 * any Drupal directory, should bootstrap to this phase.
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
  // Check to see if anyone has changed --root or --uri
  _drush_bootstrap_select_drupal_site();
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
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
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

/**
 * Validate that Drush is running in a suitable environment.
 */
function _drush_bootstrap_drush_validate() {
  // @todo _drush_environment_php_ini_checks() always returns TRUE.
  $return = _drush_environment_check_php_ini();
  if ($return !== TRUE) {
    return $return;
  }

  if (drush_environment_lib() === FALSE) {
    return FALSE;
  }

  if (drush_environment_table_lib() === FALSE) {
    return FALSE;
  }

  return TRUE;
}

/**
 * Initial Drush bootstrap phase.
 *
 * During the initialization of Drush,
 * this is the first step where all we are
 * aware of is Drush itself.
 *
 * In this step we will register the shutdown function,
 * parse the command line arguments and store them in their
 * related contexts.
 *
 * Configuration files (drushrc.php) that are
 *   a) Specified on the command line
 *   b) Stored in the root directory of drush.php
 *   c) Stored in the home directory of the system user.
 *
 * Additionally the DRUSH_QUIET and DRUSH_BACKEND contexts,
 * will be evaluated now, as they need to be set very early in
 * the execution flow to be able to take affect/
 */
function _drush_bootstrap_drush() {
  // Create an alias '@none' to represent no Drupal site
  _drush_sitealias_cache_alias('none', array('root' => '', 'uri' => ''));

  // Set the terminal width, used for wrapping table output.
  // Normally this is exported using tput in the drush script.
  // If this is not present we do an additional check using stty here.
  // On Windows in CMD and PowerShell is this exported using mode con.
  // PHP version prior to 5.3.0 is not supporting this, they always
  // return 80 columns
  if (!($columns = getenv('COLUMNS'))) {
    // Trying to export the columns using stty.
    exec('stty size 2>&1', $columns_output, $columns_status);
    if (!$columns_status) $columns = preg_replace('/\d+\s(\d+)/', '$1', $columns_output[0], -1, $columns_count);

    // If stty fails and Drush us running on Windows are we trying with mode con.
    if (($columns_status || !$columns_count) && drush_is_windows() && version_compare(phpversion(), '5.3.0') > -1) {
      $columns_output = array();
      exec('mode con', $columns_output, $columns_status);
      if (!$columns_status && is_array($columns_output)) {
        $columns = (int)preg_replace('/\D/', '', $columns_output[4], -1, $columns_count);
      }
    }

    // Failling back to default columns value
    if (empty($columns)) {
      $columns = 80;
    }
  }
  // If a caller wants to reserve some room to add additional
  // information to the drush output via post-processing, the
  // --reserve-margin flag can be used to declare how much
  // space to leave out.  This only affects drush functions
  // such as drush_print_table that wrap the output.
  $columns -= drush_get_option('reserve-margin', 0);
  drush_set_context('DRUSH_COLUMNS', $columns);

  // Statically define a way to call drush again.
  define('DRUSH_COMMAND', drush_find_drush());

  // prime the CWD cache
  drush_cwd();

  // Set up base environment for system-wide file locations.
  _drush_bootstrap_base_environment();

  // Load a drushrc.php file in the drush.php's directory.
  drush_load_config('drush');

  // Load a drushrc.php file in the $ETC_PREFIX/etc/drush directory.
  drush_load_config('system');

  // Load a drushrc.php file at ~/.drushrc.php.
  drush_load_config('user');

  // Load a drushrc.php file in the ~/.drush directory.
  drush_load_config('home.drush');

  // Load a custom config specified with the --config option.
  drush_load_config('custom');

  // Process the site alias that specifies which instance
  // of drush (local or remote) this command will operate on.
  // We must do this after we load our config files (so that
  // site aliases are available), but before the rest
  // of the drush and drupal root bootstrap phases are
  // done, since site aliases may set option values that
  // affect these phases.
  drush_sitealias_check_arg();

  // Load the config options from Drupal's sites/all/drush directory, even prior to bootstrapping the root
  drush_load_config('drupal');

  // Similarly, load the Drupal site configuration options upfront.
  drush_load_config('site');

  // If applicable swaps in shell alias value (or executes it).
  drush_shell_alias_replace();

  // If drush_load_config defined a site alias that did not
  // exist before, then sitealias check arg might now match
  // against one of those aliases.
  if (drush_sitealias_check_arg() === TRUE) {
    $remote_host = drush_get_option('remote-host');
    if (!isset($remote_host)) {
      // Load the config files for the "new" site.
      drush_load_config('drupal');
      drush_load_config('site');
    }
  }
  // Check to see if we 'use'd a site alias with site-set
  drush_sitealias_check_site_env();

  _drush_bootstrap_global_options();
}

function _drush_bootstrap_output_prepare() {
  $backend = drush_set_context('DRUSH_BACKEND', drush_get_option('backend'));

  // Pipe implies quiet.
  $quiet = drush_set_context('DRUSH_QUIET', drush_get_option(array('quiet', 'pipe')));

  drush_set_context('DRUSH_PIPE', drush_get_option('pipe'));

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
 * Determine which Drupal site will be selected.
 *
 * The Drupal site itself will be bootstrapped at a later
 * phase; at this time, we set context variables to
 * point to the drupal root, site URI and site configuration
 * path that will be used when needed.
 *
 * These early context variables are used to find
 * drush configuration and alias files stored with the
 * site to be bootstrapped.
 */
function _drush_bootstrap_select_drupal_site() {
  $drupal_root = drush_get_option('root');
  if (!isset($drupal_root)) {
    $drupal_root = drush_locate_root();
  }
  drush_set_context('DRUSH_SELECTED_DRUPAL_ROOT', $drupal_root);
  drush_set_context('DRUSH_SELECTED_DRUPAL_SITES_ALL_DRUSH', $drupal_root . '/sites/all/drush');
  $uri = _drush_bootstrap_selected_uri();
  drush_set_context('DRUSH_SELECTED_URI', $uri);
  drush_set_context('DRUSH_SELECTED_DRUPAL_SITE_CONF_PATH', drush_conf_path($uri));

  if (!empty($drupal_root) && !empty($uri)) {
    // Create an alias '@self'
    _drush_sitealias_cache_alias('self', array('root' => $drupal_root, 'uri' => $uri));
  }
}

/**
 * Sets up basic environment that controls where Drush looks for files on a
 * system-wide basis. Important to call for "early" functions that need to
 * work with unit tests.
 */
function _drush_bootstrap_base_environment() {
  // Copy ETC_PREFIX and SHARE_PREFIX from environment variables if available.
  // This alters where we check for server-wide config and alias files.
  // Used by unit test suite to provide a clean environment.
  if (getenv('ETC_PREFIX')) drush_set_context('ETC_PREFIX', getenv('ETC_PREFIX'));
  if (getenv('SHARE_PREFIX')) drush_set_context('SHARE_PREFIX', getenv('SHARE_PREFIX'));

  drush_set_context('DOC_PREFIX', DRUSH_BASE_PATH);
  if (!file_exists(DRUSH_BASE_PATH . '/README.txt') && file_exists(drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush') . '/README.txt') {
    drush_set_context('DOC_PREFIX', drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush');
  }
  $alias_path =& drush_get_context('ALIAS_PATH');
  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $default_prefix_commandfile = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '/usr';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $site_wide_commandfile_dir = drush_get_context('SHARE_PREFIX', $default_prefix_commandfile) . '/share/drush/commands';
  $alias_path[] = $site_wide_configuration_dir;
  drush_set_context('DRUSH_SITE_WIDE_CONFIGURATION', $site_wide_configuration_dir);
  drush_set_context('DRUSH_SITE_WIDE_COMMANDFILES', $site_wide_commandfile_dir);
  $alias_path[] = dirname(__FILE__) . '/..';
  if(!is_null(drush_server_home())) {
    $alias_path[] = drush_server_home() . '/.drush';
    drush_set_context('DRUSH_PER_USER_CONFIGURATION', drush_server_home() . '/.drush');
  }
}

function _drush_bootstrap_global_options() {
  // Debug implies verbose
  drush_set_context('DRUSH_VERBOSE',     drush_get_option(array('verbose', 'debug'), FALSE));
  drush_set_context('DRUSH_DEBUG', drush_get_option('debug'));

  // Backend implies affirmative unless negative is explicitly specified
  drush_set_context('DRUSH_NEGATIVE',    drush_get_option('no', FALSE));
  drush_set_context('DRUSH_AFFIRMATIVE', drush_get_option(array('yes', 'pipe'), FALSE) || (drush_get_context('DRUSH_BACKEND') && !drush_get_context('DRUSH_NEGATIVE')));
  drush_set_context('DRUSH_SIMULATE',    drush_get_option('simulate', FALSE));

  // Suppress colored logging if --nocolor option is explicitly given or if
  // terminal does not support it.
  $nocolor = (drush_get_option('nocolor', FALSE));
  if (!$nocolor) {
    // Check for colorless terminal.  If there is no terminal, then
    // 'tput colors 2>&1' will return "tput: No value for $TERM and no -T specified",
    // which is not numeric and therefore will put us in no-color mode.
    $colors = exec('tput colors 2>&1');
    $nocolor = !($colors === FALSE || (is_numeric($colors) && $colors >= 3));
  }
  drush_set_context('DRUSH_NOCOLOR', $nocolor);
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
 * We also now load the drushrc.php for this specific platform.
 * We can now include files from the Drupal Tree, and figure
 * out more context about the platform, such as the version of Drupal.
 */
function _drush_bootstrap_drupal_root() {
  $drupal_root = drush_set_context('DRUSH_DRUPAL_ROOT', drush_bootstrap_value('drupal_root'));
  define('DRUPAL_ROOT', $drupal_root);

  chdir($drupal_root);
  require_once DRUPAL_ROOT . '/' . DRUSH_DRUPAL_SIGNATURE;
  // Beware the poison pill: prefer drush_drupal_version() and
  // drush_drupal_major_version() to 'DRUSH_DRUPAL_VERSION' and
  // 'DRUSH_DRUPAL_MAJOR_VERSION', respectively. See http://drupal.org/node/1816192
  $version = drush_set_context('DRUSH_DRUPAL_VERSION', drush_drupal_version());
  $major_version = drush_set_context('DRUSH_DRUPAL_MAJOR_VERSION', drush_drupal_major_version());

  if (($major_version < 6) || ($major_version > 7)) {
    if ($major_version < 6) {
      $recommended_version = 4;
    }
    else {
      $recommended_version = 6;
    }
    drush_set_error('DRUSH_DRUPAL_VERSION_UNSUPPORTED', dt('Drush !drush_version does not support Drupal !major_version. Use Drush !recommended_version instead.', array('!drush_version' => DRUSH_VERSION, '!major_version' => $major_version, '!recommended_version' => $recommended_version)));
  }

  drush_set_context('DRUSH_DRUPAL_CORE', DRUPAL_ROOT);
  define('DRUSH_DRUPAL_CORE', DRUPAL_ROOT);

  _drush_bootstrap_global_options();

  drush_log(dt("Initialized Drupal !version root directory at !drupal_root", array("!version" => $version, '!drupal_root' => $drupal_root)));
}

/**
 * Find the URI that has been selected by the --uri / -l option
 * or the cwd.
 */
function _drush_bootstrap_selected_uri() {
  $uri = drush_get_option('uri');
  if (!isset($uri)) {
    $site_path = drush_site_path();
    $elements = explode('/', $site_path);
    $current = array_pop($elements);
    if (!$current) {
      $current = 'default';
    }
    $uri = 'http://'. $current;
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
  $drush_uri = drush_get_context('DRUSH_SELECTED_URI');

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

  $site = drush_bootstrap_value('site', $_SERVER['HTTP_HOST']);

  $conf_path = drush_bootstrap_value('conf_path', conf_path(TRUE, TRUE));
  $conf_file = "./$conf_path/settings.php";
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

  _drush_bootstrap_global_options();
}

/**
 * Initialize a site on the Drupal root.
 *
 * We now set various contexts that we determined and confirmed to be valid.
 * Additionally we load an optional drushrc.php file in the site directory.
 */
function _drush_bootstrap_drupal_site() {
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

  // Force Drupal6 not to store queries since we are not outputting them.
  // Don't run poormanscron during Drush request (D7+).
  $override = array(
    'dev_query' => FALSE,
    'cron_safe_threshold' => 0,
  );
  $current_override = drush_get_option_list('variables');
  foreach ($current_override as $name => $value) {
    if (is_numeric($name) && (strpos($value, '=') !== FALSE)) {
      list($name, $value) = explode('=', $value, 2);
    }
    $override[$name] = $value;
  }
  $conf = is_array($conf) ? array_merge($conf, $override) : $conf;

  // Populate the DRUSH_DB_CREDENTIALS with the fields loaded from the configuration.
  $creds = array();
  switch (drush_drupal_major_version()) {
     case 6:
       if (!empty($GLOBALS['db_url'])) {
         $url = $GLOBALS['db_url'];
         if (is_array($url)) {
           $url = $url['default'];
         }
         $parts = parse_url($url);
         $parts += array('pass' => '', 'port' => '');
         $creds['driver'] = $parts['scheme'];
         $creds['user'] = urldecode($parts['user']);
         $creds['host'] = $parts['host'];
         $creds['port'] = $parts['port'];
         $creds['pass'] = urldecode($parts['pass']);
         $creds['name'] = trim($parts['path'], '/');
       }
       break;
     case 7:
     default:
       if (!empty($GLOBALS['databases']['default']['default'])) {
         $conn = $GLOBALS['databases']['default']['default'];
         // Fill in defaults to prevent notices.
         $conn += array(
           'username' => NULL,
           'host' => NULL,
           'port' => NULL,
           'password' => NULL,
           'database' => NULL,
           'unix_socket' => NULL,
         );
         $creds['driver'] = $conn['driver'];
         $creds['user'] = $conn['username'];
         $creds['unix_socket'] = $conn['unix_socket'];
         $creds['host'] = $conn['host'];
         $creds['port'] = $conn['port'];
         $creds['name'] = $conn['database'];
         $creds['pass'] = $conn['password'];
       }
       break;
  }

  drush_set_context('DRUSH_DB_CREDENTIALS', $creds);
}

/**
 * Validate the DRUSH_BOOTSTRAP_DRUPAL_DATABASE phase
 *
 * Attempt to making a working database connection using the
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
  drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
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
  $dogs = module_implements('watchdog');
  if (!in_array('system', $dogs)) {
    // Note that this resets module_implements cache.
    module_implements('watchdog', FALSE, TRUE);
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

  if (!is_null($value)) {
    $values[$context] = $value;
  }

  if (array_key_exists($context, $values)) {
    return $values[$context];
  }

  return null;
}

/**
 * Prepare drush for bootstrap
 *
 * All pre-flight checks and includes necessary to begin the bootstrap
 * process.
 *
 * Ran before drush_main().
 *
 * @see drush_main()
 * @see drush.php
 */
function drush_bootstrap_prepare() {
  define('DRUSH_BASE_PATH', dirname(dirname(__FILE__)));

  require_once DRUSH_BASE_PATH . '/includes/environment.inc';
  require_once DRUSH_BASE_PATH . '/includes/command.inc';
  require_once DRUSH_BASE_PATH . '/includes/drush.inc';
  require_once DRUSH_BASE_PATH . '/includes/backend.inc';
  require_once DRUSH_BASE_PATH . '/includes/batch.inc';
  require_once DRUSH_BASE_PATH . '/includes/context.inc';
  require_once DRUSH_BASE_PATH . '/includes/sitealias.inc';
  require_once DRUSH_BASE_PATH . '/includes/exec.inc';
  require_once DRUSH_BASE_PATH . '/includes/drupal.inc';
  require_once DRUSH_BASE_PATH . '/includes/output.inc';
  require_once DRUSH_BASE_PATH . '/includes/cache.inc';
  require_once DRUSH_BASE_PATH . '/includes/filesystem.inc';
  require_once DRUSH_BASE_PATH . '/includes/dbtng.inc';

  // Terminate immediately unless invoked as a command line script
  if (!drush_verify_cli()) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Drush is designed to run via the command line.'));
  }

  // Check supported version of PHP.
  define('DRUSH_MINIMUM_PHP', '5.2.0');
  if (version_compare(phpversion(), DRUSH_MINIMUM_PHP) < 0) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Your command line PHP installation is too old. Drush requires at least PHP !version.', array('!version' => DRUSH_MINIMUM_PHP)));
  }

  $drush_info = drush_read_drush_info();
  define('DRUSH_VERSION', $drush_info['drush_version']);
  $version_parts = explode('.', DRUSH_VERSION);
  define('DRUSH_MAJOR_VERSION', $version_parts[0]);
  define('DRUSH_MINOR_VERSION', $version_parts[1]);

  define('DRUSH_REQUEST_TIME', microtime(TRUE));

  drush_set_context('argc', $GLOBALS['argc']);
  drush_set_context('argv', $GLOBALS['argv']);

  // Set an error handler and a shutdown function
  set_error_handler('drush_error_handler');
  register_shutdown_function('drush_shutdown');

  drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE);

  // We need some global options/arguments processed at this early stage.
  drush_parse_args();
}

/**
 * Cleanup our bootstrap.
 *
 * This is ran after we have bootstrapped and dispatched properly in
 * drush_main().
 *
 * @see drush_main()
 */
function drush_bootstrap_finish() {
  // We set this context to let the shutdown function know we reached the end of drush_main();
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
}

/**
 * Shutdown function for use while Drupal is bootstrapping and to return any
 * registered errors.
 *
 * The shutdown command checks whether certain options are set to reliably
 * detect and log some common Drupal initialization errors.
 *
 * If the command is being executed with the --backend option, the script
 * will return a json string containing the options and log information
 * used by the script.
 *
 * The command will exit with '1' if it was successfully executed, and the
 * result of drush_get_error() if it wasn't.
 */
function drush_shutdown() {
  // Mysteriously make $user available during sess_write(). Avoids a NOTICE.
  global $user;

  if (!drush_get_context('DRUSH_EXECUTION_COMPLETED', FALSE) && !drush_get_context('DRUSH_USER_ABORT', FALSE)) {
    $php_error_message = '';
    if ($error = error_get_last()) {
      $php_error_message = "\n" . dt('Error: !message in !file, line !line', array('!message' => $error['message'], '!file' => $error['file'], '!line' => $error['line']));
    }
    // We did not reach the end of the drush_main function,
    // this generally means somewhere in the code a call to exit(),
    // was made. We catch this, so that we can trigger an error in
    // those cases.
    drush_set_error("DRUSH_NOT_COMPLETED", dt("Drush command terminated abnormally due to an unrecoverable error.!message", array('!message' => $php_error_message)));
    // Attempt to give the user some advice about how to fix the problem
    _drush_postmortem();
  }

  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if (drush_get_context('DRUSH_BOOTSTRAPPING')) {
    switch ($phase) {
      case DRUSH_BOOTSTRAP_DRUPAL_FULL :
        ob_end_clean();
        _drush_log_drupal_messages();
        drush_set_error('DRUSH_DRUPAL_BOOTSTRAP_ERROR');
        break;
    }
  }

  if (drush_get_context('DRUSH_BACKEND', FALSE)) {
    drush_backend_output();
  }
  elseif (drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_end_clean();
    // If we are in pipe mode, emit the compact representation of the command, if available.
    if (drush_get_context('DRUSH_PIPE')) {
      drush_pipe_output();
    }
  }

  /**
   * For now, drush skips end of page processing on D7. Doing so could write
   * cache entries to module_implements and lookup_cache that don't match web requests.
   */
  // if (drush_drupal_major_version() >= 7 && function_exists('drupal_page_footer')) {
    // drupal_page_footer();
  // }

  // this way drush_return_status will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
  // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
  register_shutdown_function('drush_return_status');
}

function drush_return_status() {
  exit((drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS);
}
