<?php
// $Id$

/**
 * @file
 * Functions used by drush to query the environment and
 * setting the current configuration.
 */

/**
 * The indicator for a Drupal installation folder.
 */
define('DRUSH_DRUPAL_BOOTSTRAP', 'includes/bootstrap.inc');

/**
 * @name Drush bootstrap phases
 * @{
 * Sequential Drush bootstrapping phases.
 */

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
 * This is the default bootstrap phase all commands will try to reach,
 * unless otherwise specified.
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
 * Supported version of Console Table. This is displayed in the manual install help.
 */
define('DRUSH_TABLE_VERSION', '1.1.3');

/**
 * URL for automatic file download for supported version of Console Table.
 */
define('DRUSH_TABLE_URL', 'http://cvs.php.net/viewvc.cgi/pear/Console_Table/Table.php?revision=1.28&view=co');

/**
 * Helper function listing phases.
 *
 * For commands that need to iterate through the phases, such as help
 */
function _drush_bootstrap_phases($function_names = FALSE) {
  static $functions = array(
    DRUSH_BOOTSTRAP_DRUSH                  => '_drush_bootstrap_drush',
    DRUSH_BOOTSTRAP_DRUPAL_ROOT            => '_drush_bootstrap_drupal_root',
    DRUSH_BOOTSTRAP_DRUPAL_SITE            => '_drush_bootstrap_drupal_site',
    DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION   => '_drush_bootstrap_drupal_configuration',
    DRUSH_BOOTSTRAP_DRUPAL_DATABASE        => '_drush_bootstrap_drupal_database',
    DRUSH_BOOTSTRAP_DRUPAL_FULL            => '_drush_bootstrap_drupal_full',
    DRUSH_BOOTSTRAP_DRUPAL_LOGIN           => '_drush_bootstrap_drupal_login');
  static $phases;
  if ($function_names) {
    return $functions;
  }
  if (!$phases) {
    $phases = array_keys($functions);
  }
  return $phases;
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
function drush_bootstrap($phase) {
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
 * Validate whether a bootstrap phases can be reached.
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
 * Bootstrap to the highest level possible, without triggering any errors.
 */
function drush_bootstrap_max() {
  $phases = _drush_bootstrap_phases();
  $phase_index = DRUSH_BOOTSTRAP_DRUSH;

  // Try to bootstrap to the maximum possible level, without generating errors
  foreach ($phases as $phase_index) {
    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
        drush_bootstrap($phase_index);
      }
    }
    else {
      break;
    }
  }

  return $phase_index;
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
 * Log PHP errors to the Drush log. This is in effect until Drupal's error
 * handler takes over.
 */
function drush_error_handler($errno, $message, $filename, $line, $context) {
  // If the @ error suppression operator was used, error_reporting will have
  // been temporarily set to 0.
  if (error_reporting() == 0) {
    return;
  }

  if ($errno & (E_ALL)) {
    // By default we log notices.
    $type = 'notice';

    // Bitmask value that constitutes an error needing to be logged.
    $error = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if ($errno & $error) {
      $type = 'error';
    }

    // Bitmask value that constitutes a warning being logged.
    $warning = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING;
    if ($errno & $warning) {
      $type = 'warning';
    }

    drush_log($message, $type);

    return TRUE;
  }
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
 * Validate initial Drush bootstrap phase.
 */
function _drush_bootstrap_drush_validate() {
  // Test safe mode is off.
  if (ini_get('safe_mode')) {
    return drush_bootstrap_error('DRUSH_SAFE_MODE', dt('PHP safe mode is activated. Drush requires that safe mode is disabled.'));
  }

  // Attempt to download Console Table, via various methods.
  $tablefile = DRUSH_BASE_PATH . '/includes/table.inc';
  if (!file_exists($tablefile)) {
    if ($file = @file_get_contents(DRUSH_TABLE_URL)) {
      @file_put_contents($tablefile, $file);
    }
    if (!file_exists($tablefile)) {
      drush_shell_exec("wget -q --timeout=30 -O $tablefile " . DRUSH_TABLE_URL);
      if (!file_exists($tablefile)) {
        drush_shell_exec("curl -s  --connect-timeout 30 -o $tablefile " . DRUSH_TABLE_URL);
        if (!file_exists($tablefile)) {
          return drush_bootstrap_error('DRUSH_TABLES_INC', dt("Drush needs a copy of the PEAR Console_Table library in order to function, and the attempt to download this file automatically failed. To continue you will need to download the !version package from http://pear.php.net/package/Console_Table, extract it, and copy the Table.php file into Drush's directory as !tablefile.", array('!version' => DRUSH_TABLE_VERSION ,'!tablefile' => $tablefile)));
        }
      }
    }
  }
  require_once $tablefile;

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
  // The bootstrap can fail silently, so we catch that in a shutdown function.
  register_shutdown_function('drush_shutdown');

  // Attempt to set the screen width to the current screen width.
  // This will only work if the user has exported COLUMNS in his session.
  if (!($columns = getenv('COLUMNS'))) {
    $columns = 80;
  }
  drush_set_context('DRUSH_COLUMNS', $columns);

  // parse the command line arguments.
  drush_parse_args();

  // statically define a way to call drush again
  define('DRUSH_COMMAND', drush_find_drush());

  // Load a drushrc.php file in the drush.php's directory.
  drush_load_config('drush');

  // Load a drushrc.php file in the $ETC_PREFIX/etc/drush directory.
  drush_load_config('system');

  // Load a drushrc.php file in the ~/.drush directory.
  drush_load_config('user');

  // Load a custom config specified with the --config option.
  drush_load_config('custom');

  // Process the site alias that specifies which instance
  // of drush (local or remote) this command will operate on.
  // We must do this after we load our config files (so that
  // site aliases are available), but before the rest
  // of the drush and drupal root bootstrap phases are
  // done, since site aliases may set option values that
  // affect these phases.
  // TODO: Note that this function will call drush_locate_root
  // (from within _drush_sitealias_find_record_for_local_site),
  // and drush_locate_root will be called again when bootstrapping
  // the drupal root below.  Is there a good way to refactor this
  // so that we do not need to search for the root twice?
  drush_sitealias_check_arg();

  $backend = drush_set_context('DRUSH_BACKEND', drush_get_option(array('b', 'backend')));

  if ($backend) {
    // Load options passed as a JSON encoded string through STDIN.
    $stdin_options = _drush_backend_get_stdin();
    if (is_array($stdin_options)) {
      drush_set_context('stdin', $stdin_options);
    }
  }

  // Pipe implies quiet.
  $quiet = drush_set_context('DRUSH_QUIET', drush_get_option(array('q', 'quiet', 'p', 'pipe')));

  drush_set_context('DRUSH_PIPE', drush_get_option(array('p', 'pipe')));

  // When running in backend mode, all output is buffered, and returned
  // as a property of a JSON encoded associative array.
  if ($backend || $quiet) {
    ob_start();
  }
  
  _drush_bootstrap_global_options();

  // Find any command files that are available during this bootstrap phase.
  _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUSH);
}

function _drush_bootstrap_global_options() {
  // Debug implies verbose
  drush_set_context('DRUSH_VERBOSE',     drush_get_option(array('v', 'verbose', 'd', 'debug'), FALSE));
  drush_set_context('DRUSH_DEBUG', drush_get_option(array('d', 'debug')));


  // Backend implies affirmative
  drush_set_context('DRUSH_AFFIRMATIVE', drush_get_option(array('y', 'yes', 'b', 'backend'), FALSE));
  drush_set_context('DRUSH_NEGATIVE',    drush_get_option(array('n', 'no'), FALSE));
  drush_set_context('DRUSH_SIMULATE',    drush_get_option(array('s', 'simulate'), FALSE));

  // Suppress colored logging if --nocolor option is explicitly given or if
  // terminal does not support it.
  $nocolor = (drush_get_option(array('nocolor'), FALSE) || !getenv('TERM'));
  if (!$nocolor) {
    // Check for colorless terminal.
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
  $drupal_root = drush_get_option(array('r', 'root'), drush_locate_root());

  if (empty($drupal_root)) {
    return drush_bootstrap_error('DRUSH_NO_DRUPAL_ROOT', dt("A Drupal installation directory could not be found"));
  }
  if (!drush_valid_drupal_root($drupal_root)) {
    return drush_bootstrap_error('DRUSH_INVALID_DRUPAL_ROOT', dt("The directory !drupal_root does not contain a valid Drupal installation", array('!drupal_root' => $drupal_root)));
  }

  drush_bootstrap_value('drupal_root', $drupal_root);

  return TRUE;
}

/**
 * Bootstrap Drush with a valid Drupal Directory.
 *
 * In this function, the pwd will be moved to the root
 * of the Drupal installation.
 *
 * The DRUSH_DRUPAL_ROOT context and the DRUPAL_ROOT constant are
 * populated from the value that we determined during the validation phase.
 *
 * We also now load the drushrc.php for this specific platform.
 * We can now include files from the Drupal Tree, and figure
 * out more context about the platform, such as the version of Drupal.
 */
function _drush_bootstrap_drupal_root() {
  $drupal_root = drush_set_context('DRUSH_DRUPAL_ROOT', drush_bootstrap_value('drupal_root'));
  define('DRUPAL_ROOT', $drupal_root);

  // Save original working dir case some command wants it.
  drush_set_context('DRUSH_OLDCWD', getcwd());

  chdir($drupal_root);
  drush_load_config('drupal');
  require_once(DRUPAL_ROOT . '/' . DRUSH_DRUPAL_BOOTSTRAP);
  $version = drush_set_context('DRUSH_DRUPAL_VERSION', drush_drupal_version());
  $major_version = drush_set_context('DRUSH_DRUPAL_MAJOR_VERSION', drush_drupal_major_version());

  drush_log(dt("Initialized Drupal !version root directory at !drupal_root", array("!version" => $version, '!drupal_root' => $drupal_root)));
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
  $site_path = drush_site_path();
  $elements = explode('/', $site_path);
  $current = array_pop($elements);
  if (!$current) {
    $current = 'default';
  }
  $uri = 'http://'. $current;

  $drush_uri = drush_bootstrap_value('drush_uri', drush_get_option(array('l', 'uri'), $uri));

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
    );
    $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];
    $_SERVER['PHP_SELF'] = $drupal_base_url['path'] . '/index.php';
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
  $drush_uri = drush_set_context('DRUSH_URI', drush_bootstrap_value('drush_uri'));
  $site = drush_set_context('DRUSH_DRUPAL_SITE', drush_bootstrap_value('site'));
  $conf_path = drush_set_context('DRUSH_DRUPAL_SITE_ROOT', drush_bootstrap_value('conf_path'));

  // Find any command files that are available during this bootstrap phase.
  _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUPAL_SITE);

  drush_log(dt("Initialized Drupal site !site at !site_root", array('!site' => $site, '!site_root' => $conf_path)));
  drush_load_config('site');
}

/**
 * Initialize a site on the Drupal root.
 *
 * We now set various contexts that we determined and confirmed to be valid.
 * Additionally we load an optional drushrc.php file in the site directory.
 */
function _drush_bootstrap_drupal_site() {
  _drush_bootstrap_do_drupal_site();
  _drush_bootstrap_redo_drupal_site();
}

/**
 * Re-do the drupal site bootstrap (and possibly the
 * drupal root bootstrap) if a site alias was processed
 * after the site bootstrap phase completed.  This will
 * happen when processing "drush sitealias command" for
 * a site alias defined in a drushrc.php file in the
 * default site's drush configuration directory.
 */
function _drush_bootstrap_redo_drupal_site() {
  // If drush_load_config defined a site alias that did not
  // exist before, then sitealias check arg might now match
  // against one of those aliases.
  if (drush_sitealias_check_arg() === TRUE) {
    $remote_host = drush_get_option('remote-host');
    if (!isset($remote_host)) {
      // Check to see if the drupal root changed.
      // If it has, we will set remote-host to cause
      // this command to be executed via the backend invoke
      // process.
      $sitealias_drupal_root = drush_get_option(array('r', 'root'));
      if (($sitealias_drupal_root != null) && (DRUPAL_ROOT != $sitealias_drupal_root)) {
        drush_set_option('remote-host', 'localhost');
      }
      else {
        // If we set an alias, then we need to bootstrap the
        // drupal site once again.  It is possible to re-bootstrap
        // the site at this point because settings.php has not
        // been included yet.
        drush_log(dt("Re-bootstrap drupal site."));
        _drush_bootstrap_drupal_site_validate();
        _drush_bootstrap_do_drupal_site();
      }
    }
  }
}

/**
 * Initialize and load the Drupal configuration files.
 *
 * We process and store a normalized set of database credentials
 * from the loaded configuration file, so we can validate them
 * and access them easily in the future.
 */
function _drush_bootstrap_drupal_configuration() {
  global $conf, $drush_conf_override;

  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

  // Overriding the $conf array from drupal CONFIGURATION bootstrap with the
  // overrides we collected on the loaded config files on DRUSH_BOOTSTRAP_DRUSH
  $conf = is_array($conf) ? array_merge($conf, $drush_conf_override) : $drush_conf_override;

  // Populate the DRUSH_DB_CREDENTIALS with the fields loaded from the configuration.
  $creds = array();
  switch (drush_drupal_major_version()) {
     case 5:
     case 6:
       if (isset($GLOBALS['db_url'])) {
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
       if (isset($GLOBALS['databases']['default']['default'])) {
         $conn = $GLOBALS['databases']['default']['default'];
         $creds['driver'] = $conn['driver'];
         $creds['user'] = $conn['username'];
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
    return drush_bootstrap_error("DRUSH_DRUPAL_DB_ERROR");
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
  ob_start();
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  ob_end_clean();
  _drush_log_drupal_messages();
  // Find any command files that are available during this bootstrap phase.
  _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUPAL_FULL);
}

/**
 * Log into the bootstrapped Drupal site with a specific
 * username or user id.
 */
function _drush_bootstrap_drupal_login() {
  $drush_user = drush_set_context('DRUSH_USER', drush_get_option(array('u', 'user'), 0));

  drush_drupal_login($drush_user);
  _drush_log_drupal_messages();
}


/**
 * Returns the current working directory.
 *
 * TODO: Could cache result, but it isn't really expensive.
 */
function drush_cwd() {
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find.
  // $_SERVER['PWD'] isn't set on windows and generates a Notice.
  $path = isset($_SERVER['PWD'])?$_SERVER['PWD']:'';
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = _drush_convert_path($path);

  return $path;
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 * Also converts a cygwin "drive emulation" path (/cygdrive/c/dir1) into a
 * proper drive path, still with Unix slashes (c:/dir1).
 */
function _drush_convert_path($path) {
  $path = str_replace('\\','/', $path);
  $path = preg_replace('/^\/cygdrive\/([A-Za-z])(.*)$/', '\1:\2', $path);

  return $path;
}

/**
 * Returns parent directory.
 *
 * @param string
 *   Path to start from.
 *
 * @return string
 *   Parent path of given path.
 */
function _drush_shift_path_up($path) {
  if (empty($path)) {
    return FALSE;
  }
  $path = explode('/', $path);
  // Move one directory up.
  array_pop($path);
  return implode('/', $path);
}

/**
 * Like Drupal conf_path, but searching from beneath.
 * Allows proper site uri detection in site sub-directories.
 *
 * Essentially looks for a settings.php file.
 *
 * @param string
 *   Search starting path. Defaults to current working directory.
 *
 * @return
 *   Current site path (folder containing settings.php) or FALSE if not found.
 */
function drush_site_path($path = NULL) {
  static $site_path;

  if (!isset($site_path)) {
    $site_path = FALSE;

    $path = empty($path) ? drush_cwd() : $path;
    // Check the current path.
    if (file_exists($path . '/settings.php')) {
      $site_path = $path;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if (file_exists($path . '/settings.php')) {
          $site_path = $path;
          break;
        }
      }
    }

    $site_root = drush_locate_root();
    if (file_exists($site_root . '/sites/sites.php')) {
      $sites = array();
      // This will overwrite $sites with the desired mappings.
      include($site_root . '/sites/sites.php');
      // We do a reverse lookup here to determine the URL given the site key.
      if ($match = array_search($site_path, $sites)) {
        $site_path = $match;
      }
    }

    // Last resort: try from site root
    if (!$site_path) {
      if ($site_root) {
        if (file_exists($site_root . '/sites/default/settings.php')) {
          $site_path = $site_root . '/sites/default';
        }
      }
    }
  }

  return $site_path;
}

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run drush from a subdirectory of the drupal root.
 *
 * @param
 *   Search start path. Defaults to current working directory.
 */
function drush_locate_root($start_path = NULL) {
  $drupal_root = FALSE;

  $start_path = empty($start_path) ? drush_cwd() : $start_path;
  foreach (array(TRUE, FALSE) as $follow_symlinks) {
    $path = $start_path;
    if ($follow_symlinks && is_link($path)) {
      $path = realpath($path);
    }
    // Check the start path.
    if (drush_valid_drupal_root($path)) {
      $drupal_root = $path;
      break;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if ($follow_symlinks && is_link($path)) {
          $path = realpath($path);
        }
        if (drush_valid_drupal_root($path)) {
          $drupal_root = $path;
          break 2;
        }
      }
    }
  }

  return $drupal_root;
}

/**
 * Checks whether given path qualifies as a Drupal root.
 *
 * @param string
 *   Path to check.
 *
 * @return boolean
 *   True if given path seems to be a Drupal root, otherwise false.
 */
function drush_valid_drupal_root($path) {
  return !empty($path) && is_dir($path) && file_exists($path . '/' . DRUSH_DRUPAL_BOOTSTRAP);
}

/**
 * Tests the currently loaded database credentials to ensure a database connection can be made.
 */
function drush_valid_db_credentials() {
  if (class_exists('PDO')) {
    $creds = drush_get_context('DRUSH_DB_CREDENTIALS');

    $type = ($creds['driver'] == 'mysqli') ? 'mysql' : $creds['driver'];

    if (!in_array($type, PDO::getAvailableDrivers())) {
      drush_log(dt('PDO support available, but the !type driver has not been installed. Assuming success.', array('!type' => $type)), 'bootstrap');
      return TRUE;
    }

    $constr = sprintf("%s:dbname=%s;host=%s", $type, $creds['name'], $creds['host']);
    if (!empty($creds['port'])) {
      $constr .= sprintf(";port=%d", $creds['port']);
    }

    try {
      $db = new PDO($constr, $creds['user'], $creds['pass']);
      $db = null;
      return TRUE;
    }
    catch (PDOException $e) {
      // We do not use drush_set_error here , because it's up to the calling function
      // to determine whether or not this is an error or a warning.
      drush_log($e->getMessage(), 'warning');
      return FALSE;
    }
  }
  else {
    drush_log(dt('PDO support not available. Could not pre-validate database credentials. Assuming success'), 'bootstrap');
    return TRUE;
  }
}

/**
 * Determine a proper way to call drush again
 *
 * This check if we were called directly or as an argument to some
 * wrapper command (php and sudo are checked now).
 *
 * Calling ./drush.php directly yields the following environment:
 *
 * _SERVER["argv"][0] => ./drush.php
 *
 * Calling php ./drush.php also yields the following:
 *
 * _SERVER["argv"][0] => ./drush.php
 *
 * Note that the $_ global is defined only in bash and therefore cannot
 * be relied upon.
 *
 * We will therefore assume PHP is available in the path and is named
 * "php" for execute ourselves. That is, the #!/usr/bin/env php is
 * working and valid, unless a PHP constant is defined, which can be
 * done by the shell wrapper.
 *
 * The DRUSH_COMMAND constant is initialised to the value of this
 * function when environment.inc is loaded.
 *
 * @see DRUSH_COMMAND
 */
function drush_find_drush() {
 if (drush_get_option(array('php'))) {
    $drush = drush_get_option(array('php')) . " " . realpath($_SERVER['argv'][0]);
  } else {
    $drush = realpath($_SERVER['argv']['0']);
  }
  return $drush;
}

/**
 * Get module information for all installed modules. Wrapper for _drush_get_modules().
 *
 * @return
 *   An array containing module info for all installed modules or FALSE on error.
 */
function drush_get_modules() {
  if(drush_include_engine('drupal', 'environment')){
    return _drush_get_modules();
  }
  else {
    return drush_set_error('DRUSH_CORE', dt('Drush was unable to load module information.'));
  }
}

/**
 * Get theme information for all installed themes. Wrapper for _drush_get_themes().
 *
 * @return
 *   An array containing theme info for all installed themes or FALSE on error.
 */
function drush_get_themes() {
  if(drush_include_engine('drupal', 'environment')){
    return _drush_get_themes();
  }
  else {
    return drush_set_error('DRUSH_CORE', dt('Drush was unable to load theme information.'));
  }
}

/**
 * Make a determination whether or not the given
 * host is local or not.
 *
 * @param host
 *   A hostname, 'localhost' or '127.0.0.1'.
 * @return
 *   True if the host is local.
 */
function drush_is_local_host($host) {
  // @TODO: Check DNS against local interface settings?
  // (Maybe not portable, maybe too slow?)
  // @TODO: Allow user to specify host names in a configuration
  // option (e.g. $options['local-hosts'] = array('myhostname1.com', 'myhostname2.com'); )
  return ($host == 'localhost') || ($host == '127.0.0.1') || (file_exists(drush_get_context('DRUSH_DRUPAL_ROOT') . "/sites/$host"));
}
