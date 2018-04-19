<?php

/**
 * @file
 * Preflight, postflight and shutdown code.
 */

use Drush\Log\LogLevel;

/**
 * The main Drush function.
 *
 * - Runs "early" option code, if set (see global options).
 * - Parses the command line arguments, configuration files and environment.
 * - Prepares and executes a Drupal bootstrap, if possible,
 * - Dispatches the given command.
 *
 * function_exists('drush_main') may be used by modules to detect whether
 * they are being called from Drush.  See http://drupal.org/node/1181308
 * and http://drupal.org/node/827478
 *
 * @return mixed
 *   Whatever the given command returns.
 */
function drush_main() {
  // Load Drush core include files, and parse command line arguments.
  if (drush_preflight_prepare() === FALSE) {
    return(1);
  }
  // Start code coverage collection.
  if ($coverage_file = drush_get_option('drush-coverage', FALSE)) {
    drush_set_context('DRUSH_CODE_COVERAGE', $coverage_file);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    register_shutdown_function('drush_coverage_shutdown');
  }

  // Load the global Drush configuration files, and global Drush commands.
  // Find the selected site based on --root, --uri or cwd
  // Preflight the selected site, and load any configuration and commandfiles associated with it.
  // Select and return the bootstrap class.
  $bootstrap = drush_preflight();

  // Reset our bootstrap phase to the beginning
  drush_set_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE);

  $return = '';
  if (!drush_get_error()) {
    if ($file = drush_get_option('early', FALSE)) {
      require_once drush_is_absolute_path($file) ? $file : DRUSH_BASE_PATH . DIRECTORY_SEPARATOR . $file;
      $function = 'drush_early_' . basename($file, '.inc');
      if (function_exists($function)) {
        if ($return = $function()) {
          // If the function returns FALSE, we continue and attempt to bootstrap
          // as normal. Otherwise, we exit early with the returned output.
          if ($return === TRUE) {
            $return = '';
          }
        }
      }
    }
    else {
      // Do any necessary preprocessing operations on the command,
      // perhaps handling immediately.
      $command_handled = drush_preflight_command_dispatch();
      if (!$command_handled) {
        $return = $bootstrap->bootstrap_and_dispatch();
      }
    }
  }
  // TODO: Get rid of global variable access here, and just trust
  // the bootstrap object returned from drush_preflight().  This will
  // require some adjustments to Drush bootstrapping.
  // See: https://github.com/drush-ops/drush/pull/1303
  if ($bootstrap = drush_get_bootstrap_object()) {
    $bootstrap->terminate();
  }
  drush_postflight();
  if (is_object($return)) {
    $return = 0;
  }

  // How strict are we?  If we are very strict, turn 'ok' into 'error'
  // if there are any warnings in the log.
  if (($return == 0) && (drush_get_option('strict') > 1) && drush_log_has_errors()) {
    $return = 1;
  }

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
}

/**
 * Prepare Drush for preflight.
 *
 * Runs before drush_main().
 *
 * @see drush_main()
 * @see drush.php
 */
function drush_preflight_prepare() {
  define('DRUSH_BASE_PATH', dirname(dirname(__FILE__)));
  // Local means that autoload.php is inside of Drush. That is, Drush is its own Composer project.
  // Global means autoload.php is outside of Drush. That is, Drush is a dependency of a bigger project.
  $local_vendor_path = DRUSH_BASE_PATH . '/vendor/autoload.php';
  $global_vendor_path = DRUSH_BASE_PATH . '/../../../vendor/autoload.php';

  // Check for a local composer install or a global composer install. Vendor dirs are in different spots).
  if (file_exists($local_vendor_path)) {
    $vendor_path = $local_vendor_path;
  }
  elseif (file_exists($global_vendor_path)) {
    $vendor_path = $global_vendor_path;
  }
  else {
    $msg = "Unable to load autoload.php. Run composer install to fetch dependencies and write this file (http://docs.drush.org/en/master/install-alternative/). Or if you prefer, use the drush.phar which already has dependencies included (http://docs.drush.org/en/master/install).\n";
    fwrite(STDERR, $msg);
    return FALSE;
  }

  $classloader = require $vendor_path;

  require_once DRUSH_BASE_PATH . '/includes/startup.inc';
  require_once DRUSH_BASE_PATH . '/includes/bootstrap.inc';
  require_once DRUSH_BASE_PATH . '/includes/environment.inc';
  require_once DRUSH_BASE_PATH . '/includes/annotationcommand_adapter.inc';
  require_once DRUSH_BASE_PATH . '/includes/command.inc';
  require_once DRUSH_BASE_PATH . '/includes/drush.inc';
  require_once DRUSH_BASE_PATH . '/includes/engines.inc';
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
  require_once DRUSH_BASE_PATH . '/includes/array_column.inc';

  // Stash our vendor path and classloader.
  drush_set_context('DRUSH_VENDOR_PATH', dirname($vendor_path));
  drush_set_context('DRUSH_CLASSLOADER', $classloader);

  // Can't log until we have a logger, so we'll create this ASAP.
  _drush_create_default_logger();

  // Terminate immediately unless invoked as a command line script
  if (!drush_verify_cli()) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Drush is designed to run via the command line.'));
  }

  // Check supported version of PHP.
  // Note: If this is adjusted, check other code that compares
  // PHP_VERSION, such as drush_json_encode(), runserver/runserver.drush.inc, and also
  // adjust _drush_environment_check_php_ini() and the php_prohibited_options
  // list in the drush script.  See http://drupal.org/node/1748228
  define('DRUSH_MINIMUM_PHP', '5.4.5');
  if (version_compare(phpversion(), DRUSH_MINIMUM_PHP) < 0 && !getenv('DRUSH_NO_MIN_PHP')) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Your command line PHP installation is too old. Drush requires at least PHP !version. To suppress this check, set the environment variable DRUSH_NO_MIN_PHP=1', array('!version' => DRUSH_MINIMUM_PHP)));
  }

  if (!$return = _drush_environment_check_php_ini()) {
    return; // An error was logged.
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
  // We need some global options/arguments processed at this early stage.
  drush_parse_args();

  // Process initial global options such as --debug.
  _drush_preflight_global_options();

  drush_log(dt("Drush preflight prepare loaded autoloader at !autoloader", array('!autoloader' => realpath($vendor_path))), LogLevel::PREFLIGHT);
}

/**
 * During the initialization of Drush, this is the first
 * step where we load our configuration and commandfiles,
 * and select the site we are going to operate on; however,
 * we take no irreversible actions (e.g. site bootstrapping).
 * This allows commands that are declared with no bootstrap
 * to select a new site root and bootstrap it.
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
 * the execution flow to be able to take affect.
 *
 * @return \Drush\Boot\Boot;
 */
function drush_preflight() {
  // Create an alias '@none' to represent no Drupal site
  _drush_sitealias_cache_alias('@none', array('root' => '', 'uri' => ''));

  // Discover terminal width for pretty output.
  _drush_preflight_columns();

  // Display is tidy now that column width has been handled.
  drush_log(dt('Starting Drush preflight.'), LogLevel::PREFLIGHT);

  // Statically define a way to call drush again.
  define('DRUSH_COMMAND', drush_find_drush());

  // prime the CWD cache
  drush_cwd();

  // Set up base environment for system-wide file locations.
  _drush_preflight_base_environment();

  // Setup global alias_paths[] in context system.
  if (!drush_get_option('local')) {
    _drush_preflight_alias_path();
  }
  if (!drush_get_option('local')) {
    // Load a drushrc.php file in the drush.php's directory.
    drush_load_config('drush');

    // Load a drushrc.php file in the $ETC_PREFIX/etc/drush directory.
    drush_load_config('system');

    // Load a drushrc.php file at ~/.drushrc.php.
    drush_load_config('user');

    // Load a drushrc.php file in the ~/.drush directory.
    drush_load_config('home.drush');
  }

  // Load a custom config specified with the --config option.
  drush_load_config('custom');

  _drush_preflight_global_options();
  // Load all the commandfiles findable from any of the
  // scopes listed above.
  _drush_find_commandfiles_drush();

  // Look up the alias identifier that the user wants to use,
  // either via an argument or via 'site-set'.
  $target_alias = drush_sitealias_check_arg_and_site_set();

  // Process the site alias that specifies which instance
  // of Drush (local or remote) this command will operate on.
  // We must do this after we load our config files (so that
  // site aliases are available), but before the rest of
  // Drush preflight and Drupal root bootstrap phase are
  // done, since site aliases may set option values that
  // affect these phases.
  $alias_record = _drush_sitealias_set_context_by_name($target_alias);

  // Find the selected site based on --root, --uri or cwd
  drush_preflight_root();

  // Preflight the selected site, and load any configuration and commandfiles associated with it.
  drush_preflight_site();

  // Check to see if anything changed during the 'site' preflight
  // that might allow us to find our alias record now
  if (empty($alias_record)) {
    $alias_record = _drush_sitealias_set_context_by_name($target_alias);

    // If the site alias settings changed late in the preflight,
    // then run the preflight for the root and site contexts again.
    if (!empty($alias_record)) {
      $remote_host = drush_get_option('remote-host');
      if (!isset($remote_host)) {
        drush_preflight_root();
        drush_preflight_site();
      }
    }
  }

  // Fail if we could not find the selected site alias.
  if ($target_alias && empty($alias_record)) {
    // We will automatically un-set the site-set alias if it could not be found.
    // Otherwise, we'd be stuck -- the user would only be able to execute Drush
    // commands again after `drush @none site-set @none`, and most folks would
    // have a hard time figuring that out.
    $site_env = drush_sitealias_site_get();
    if ($site_env == $target_alias) {
      drush_sitealias_site_clear();
    }
    return drush_set_error('DRUSH_BOOTSTRAP_NO_ALIAS', dt("Could not find the alias !alias", array('!alias' => $target_alias)));
  }

  // If applicable swaps in shell alias values.
  drush_shell_alias_replace($target_alias);

  // Copy global options to their respective contexts
  _drush_preflight_global_options();

  // Set environment variables based on #env-vars.
  drush_set_environment_vars($alias_record);

  // Select the bootstrap object and return it.
  return drush_select_bootstrap_class();
}

/**
 * If --root is provided, set context.
 */
function drush_preflight_root() {
  $root = drush_get_option('root');
  if (!isset($root)) {
    $root = drush_locate_root();
  }
  if ($root) {
    $root = realpath($root);
  }
  // @todo This context name should not mention Drupal.
  // @todo Drupal code should use DRUSH_DRUPAL_ROOT instead of this constant.
  drush_set_context('DRUSH_SELECTED_DRUPAL_ROOT', $root);

  // Load the config options from Drupal's /drush, ../drush, and sites/all/drush directories,
  // even prior to bootstrapping the root.
  drush_load_config('drupal');

  // Search for commandfiles in the root locations
  $discovery = annotationcommand_adapter_get_discovery();
  $searchpath = [dirname($root) . '/drush', "$root/drush", "$root/sites/all/drush"];

  $drush_root_extensions = $discovery->discover($searchpath, '\Drush');
  drush_set_context(
    'DRUSH_ANNOTATED_COMMANDFILES',
    array_merge(
      drush_get_context('DRUSH_ANNOTATED_COMMANDFILES'),
      $drush_root_extensions
    )
  );
}

function drush_preflight_site() {
  // Load the Drupal site configuration options upfront.
  drush_load_config('site');

  // Determine URI and set constants/contexts accordingly. Keep this after loading of drupal,site configs.
  _drush_preflight_uri();

  // If someone set 'uri' in the 'site' context, then copy it
  // to the 'process' context (to give it a higher priority
  // than the 'cli' and 'alias' contexts) and reset our selected
  // site and @self alias.
  $uri = drush_get_option('uri');
  if ($uri != drush_get_option('uri', $uri, 'site')) {
    drush_set_option('uri', drush_get_option('uri', $uri, 'site'));
    _drush_preflight_uri();
  }

  // Create a @self site alias record.
  drush_sitealias_create_self_alias();
}

function _drush_preflight_global_options() {
  // Debug implies verbose
  $verbose = drush_get_option('verbose', FALSE);
  $debug = drush_get_option('debug', FALSE);
  drush_set_context('DRUSH_VERBOSE',      $verbose || $debug);
  drush_set_context('DRUSH_DEBUG',        $debug);
  drush_set_context('DRUSH_DEBUG_NOTIFY', $verbose && $debug);
  drush_set_context('DRUSH_SIMULATE',     drush_get_option('simulate', FALSE));

  // Backend implies affirmative unless negative is explicitly specified
  drush_set_context('DRUSH_NEGATIVE',    drush_get_option('no', FALSE));
  drush_set_context('DRUSH_AFFIRMATIVE', drush_get_option(array('yes', 'pipe'), FALSE) || (drush_get_context('DRUSH_BACKEND') && !drush_get_context('DRUSH_NEGATIVE')));

  // Pipe implies quiet.
  drush_set_context('DRUSH_QUIET', drush_get_option(array('quiet', 'pipe')));
  drush_set_context('DRUSH_PIPE', drush_get_option('pipe'));

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
 * Sets up basic environment that controls where Drush looks for files on a
 * system-wide basis. Important to call for "early" functions that need to
 * work with unit tests.
 */
function _drush_preflight_base_environment() {
  // Copy ETC_PREFIX and SHARE_PREFIX from environment variables if available.
  // This alters where we check for server-wide config and alias files.
  // Used by unit test suite to provide a clean environment.
  if (getenv('ETC_PREFIX')) drush_set_context('ETC_PREFIX', getenv('ETC_PREFIX'));
  if (getenv('SHARE_PREFIX')) drush_set_context('SHARE_PREFIX', getenv('SHARE_PREFIX'));

  drush_set_context('DOC_PREFIX', DRUSH_BASE_PATH);
  if (!file_exists(DRUSH_BASE_PATH . '/README.md') && file_exists(drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush' . '/README.md')) {
    drush_set_context('DOC_PREFIX', drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush');
  }

  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $default_prefix_commandfile = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '/usr';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $site_wide_commandfile_dir = drush_get_context('SHARE_PREFIX', $default_prefix_commandfile) . '/share/drush/commands';
  drush_set_context('DRUSH_SITE_WIDE_CONFIGURATION', $site_wide_configuration_dir);
  drush_set_context('DRUSH_SITE_WIDE_COMMANDFILES', $site_wide_commandfile_dir);

  $server_home = drush_server_home();
  if (isset($server_home)) {
    drush_set_context('DRUSH_PER_USER_CONFIGURATION', $server_home . '/.drush');
  }
}

/*
 * Set the terminal width, used for wrapping table output.
 * Normally this is exported using tput in the drush script.
 * If this is not present we do an additional check using stty here.
 * On Windows in CMD and PowerShell is this exported using mode con.
 */
function _drush_preflight_columns() {
  if (!($columns = getenv('COLUMNS') ?: 0)) {
    // Trying to export the columns using stty.
    exec('stty size 2>&1', $columns_output, $columns_status);
    if (!$columns_status) $columns = preg_replace('/\d+\s(\d+)/', '$1', $columns_output[0], -1, $columns_count);

    // If stty fails and Drush us running on Windows are we trying with mode con.
    if (($columns_status || !$columns_count) && drush_is_windows()) {
      $columns_output = array();
      exec('mode con', $columns_output, $columns_status);
      if (!$columns_status && is_array($columns_output)) {
        $columns = (int)preg_replace('/\D/', '', $columns_output[4], -1, $columns_count);
      }
      else {
        drush_log(dt('Drush could not detect the console window width. Set a Windows Environment Variable of COLUMNS to the desired width.'), LogLevel::WARNING);
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
  // such as drush_print_table() that wrap the output.
  $columns -= drush_get_option('reserve-margin', 0);
  drush_set_context('DRUSH_COLUMNS', $columns);
}

function _drush_preflight_alias_path() {
  $alias_path =& drush_get_context('ALIAS_PATH');
  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $alias_path[] = drush_sitealias_alias_base_directory($site_wide_configuration_dir);

  $alias_path[] = drush_sitealias_alias_base_directory(dirname(__FILE__) . '/..');

  $server_home = drush_server_home();
  if (isset($server_home)) {
    $alias_path[] = drush_sitealias_alias_base_directory($server_home . '/.drush');
  }
}

/*
 * Set root and uri.
 */
function _drush_preflight_root_uri() {
  drush_preflight_root();
  _drush_preflight_uri();
}

/**
 * If --uri is provided, set context.
 */
function _drush_preflight_uri() {
  $uri = drush_get_option('uri', '');
  drush_set_context('DRUSH_SELECTED_URI', $uri);
}

function _drush_find_commandfiles_drush() {
  // Core commands shipping with Drush
  $searchpath[] = dirname(__FILE__) . '/../commands/';

  // User commands, specified by 'include' option
  $include = drush_get_context('DRUSH_INCLUDE', array());
  foreach ($include as $path) {
    if (is_dir($path)) {
      drush_log('Include ' . $path, LogLevel::NOTICE);
      $searchpath[] = $path;
    }
  }

  if (!drush_get_option('local')) {
    // System commands, residing in $SHARE_PREFIX/share/drush/commands
    $share_path = drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES');
    if (is_dir($share_path)) {
      $searchpath[] = $share_path;
    }

    // User commands, residing in ~/.drush
    $per_user_config_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
    if (!empty($per_user_config_dir)) {
      $searchpath[] = $per_user_config_dir;
    }
  }

  // @todo the zero parameter is a bit weird here. It's $phase.
  _drush_add_commandfiles($searchpath, 0);

  // Also discover Drush's own annotation commands.
  $discovery = annotationcommand_adapter_get_discovery();
  $annotation_commandfiles = $discovery->discover(DRUSH_BASE_PATH . '/lib/Drush', '\Drush');

  // And, finally, search for commandfiles in the $searchpath
  $searchpath = array_map(
    function ($item) {
      if (strtolower(basename($item)) == 'commands') {
        return dirname($item);
      }
      return $item;
    },
    $searchpath
  );
  $global_drush_extensions = $discovery->discover($searchpath, '\Drush');
  $annotation_commandfiles += $global_drush_extensions;

  drush_set_context('DRUSH_ANNOTATED_COMMANDFILES', $annotation_commandfiles);
}

/**
 * Handle any command preprocessing that may need to be done, including
 * potentially redispatching the command immediately (e.g. for remote
 * commands).
 *
 * @return
 *   TRUE if the command was handled remotely.
 */
function drush_preflight_command_dispatch() {
  $interactive = drush_get_option('interactive', FALSE);

  // The command will be executed remotely if the --remote-host flag
  // is set; note that if a site alias is provided on the command line,
  // and the site alias references a remote server, then the --remote-host
  // option will be set when the site alias is processed.
  // @see drush_sitealias_check_arg_and_site_set and _drush_sitealias_set_context_by_name
  $remote_host = drush_get_option('remote-host');
  $site_list = drush_get_option('site-list');
  // Get the command early so that we can allow commands to directly handle remote aliases if they wish
  $command = drush_parse_command();
  drush_command_default_options($command);

  // If the command sets the 'strict-option-handling' flag, then we will remove
  // any cli options that appear after the command name from the 'cli' context.
  // The cli options that appear before the command name are stored in the
  // 'DRUSH_GLOBAL_CLI_OPTIONS' context, so we will just overwrite the cli context
  // with this, after doing the neccessary fixup from short-form to long-form options.
  // After we do that, we put back any local drush options identified by $command['options'].
  if (is_array($command) && !empty($command['strict-option-handling'])) {
    $cli_options = drush_get_context('DRUSH_GLOBAL_CLI_OPTIONS', array());
    // Now we are going to sort out any options that exist in $command['options'];
    // we will remove these from DRUSH_COMMAND_ARGS and put them back into the
    // cli options.
    $cli_context = drush_get_context('cli');
    $remove_from_command_args = array();
    foreach ($command['options'] as $option => $info) {
      if (array_key_exists($option, $cli_context)) {
        $cli_options[$option] = $cli_context[$option];
        $remove_from_command_args[$option] = $option;
      }
    }
    if (!empty($remove_from_command_args)) {
      $drush_command_args = array();
      foreach (drush_get_context('DRUSH_COMMAND_ARGS') as $arg) {
        if (!_drush_should_remove_command_arg($arg, $remove_from_command_args)) {
          $drush_command_args[] = $arg;
        }
      }
      drush_set_context('DRUSH_COMMAND_ARGS', $drush_command_args);
    }
    drush_expand_short_form_options($cli_options);
    drush_set_context('cli', $cli_options);
    _drush_preflight_global_options();
  }
  $args = drush_get_arguments();
  $command_name = array_shift($args);
  $root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  $local_drush = drush_get_option('drush-script');
  if (empty($local_drush) && !empty($root)) {
    $local_drush = find_wrapper_or_launcher($root);
  }
  $is_local = drush_get_option('local');
  $must_use_site_local = !empty($root) && !empty($local_drush) && empty($is_local);
  // If the command sets the 'handle-remote-commands' flag, then we will short-circuit
  // remote command dispatching and site-list command dispatching, and always let
  // the command handler run on the local machine.
  if (is_array($command) && !empty($command['handle-remote-commands']) && !$must_use_site_local) {
    return FALSE;
  }
  $values = NULL;
  if (!isset($remote_host) && !isset($site_list) && $must_use_site_local) {
    if (!drush_is_absolute_path($local_drush)) {
      $local_drush = $root . DIRECTORY_SEPARATOR . $local_drush;
    }
    $local_drush = realpath($local_drush);
    $this_drush = drush_find_drush();
    // If there is a local Drush selected, and it is not the
    // same Drush that is currently running, redispatch to it.
    // We assume that if the current Drush is nested inside
    // the current Drupal root (or, more specifically, the
    // current Drupal root's parent), then it is a site-local Drush.
    // We avoid redispatching in that instance to prevent an
    // infinite loop.
    if (file_exists($local_drush) && !drush_is_nested_directory(dirname($root), $this_drush)) {
      $uri = drush_get_context('DRUSH_SELECTED_URI');
      $aditional_options = array(
        'root' => $root,
      );
      if (!empty($uri)) {
        $aditional_options['uri'] = $uri;
      }
      // We need to chdir to the Drupal root here, for the
      // benefit of the Drush wrapper.
      chdir($root);
      $values = drush_do_command_redispatch(is_array($command) ? $command : $command_name, $args, NULL, NULL, $local_drush, TRUE, $aditional_options);
    }
  }
  if (isset($remote_host)) {
    $remote_user = drush_get_option('remote-user');

    // Force interactive mode if there is a single remote target.  #interactive is added by drush_do_command_redispatch
    $user_interactive = drush_get_option('interactive');
    drush_set_option('interactive', TRUE);
    $values = drush_do_command_redispatch(is_array($command) ? $command : $command_name, $args, $remote_host, $remote_user, $user_interactive);
  }
  // If the --site-list flag is set, then we will execute the specified
  // command once for every site listed in the site list.
  if (isset($site_list)) {
    if (!is_array($site_list)) {
      $site_list = explode(',', $site_list);
    }
    $site_record = array('site-list' => $site_list);
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE') && !$interactive  && !drush_get_context('DRUSH_AFFIRMATIVE') && !drush_get_context('DRUSH_QUIET')) {
      drush_print(dt("You are about to execute '!command' non-interactively (--yes forced) on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
        drush_user_abort();
        return TRUE;
      }
    }
    $command_name = array_shift($args);
    $multi_options = drush_redispatch_get_options();
    $backend_options = array();
    if (drush_get_option('pipe') || drush_get_option('interactive')) {
      $backend_options['interactive'] = TRUE;
    }
    if (drush_get_option('no-label', FALSE)) {
      $backend_options['no-label'] = TRUE;
    }
    // If the user specified a format, try to look up the
    // default list separator for the specified format.
    // If the user did not specify a different label separator,
    // then pass in the default as an option, so that the
    // separator between the items in the list and the site
    // name will be consistent.
    $format = drush_get_option('format', FALSE);
    if ($format && !array_key_exists('label-separator', $multi_options)) {
      $formatter = drush_load_engine('outputformat', $format);
      if ($formatter) {
        $list_separator = $formatter->get_info('list-separator');
        if ($list_separator) {
          $multi_options['label-separator'] = $list_separator;
        }
      }
    }
    $values = drush_invoke_process($site_record, $command_name, $args, $multi_options, $backend_options);
  }
  if (isset($values)) {
    if (is_array($values) && ($values['error_status'] > 0)) {
      // Force an error result code.  Note that drush_shutdown() will still run.
      drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
      exit($values['error_status']);
    }
    return TRUE;
  }
  return FALSE;
}

/**
 * Look for instances of arguments or parameters that
 * start with "~/".  Replace these with "$HOME/".
 *
 * Note that this function is called _after_ Drush does
 * its redispatch checks; tildes are passed through
 * unmodified on a redispatch, and are only expanded when
 * a command is handled locally.
 */
function drush_preflight_tilde_expansion(&$command) {
  // Skip tilde expansion for commands that use
  // stict option handling, or those that explicitly
  // turn it off via $command['tilde-expansion'] = FALSE.
  if ($command['tilde-expansion'] && !$command['strict-option-handling']) {
    $cli =& drush_get_context('cli');
    $match = '#^~/#';
    $replacement = drush_server_home() . '/';
    foreach ($cli as $key => $value) {
      if (is_string($value) && preg_match($match, $value)) {
        $cli[$key] = preg_replace($match, $replacement, $value);
      }
    }
    $command['arguments'] = array_map(function($value) use($match, $replacement) { return is_string($value) ? preg_replace($match, $replacement, $value) : $value; } , $command['arguments']);
  }
}

/**
 * We set this context to let the shutdown function know we reached the end of drush_main().
 *
 * @see drush_main()
 */
function drush_postflight() {
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
}

/**
 * Shutdown function for use while Drush and Drupal are bootstrapping and to return any
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

  // @todo Ask the bootstrap object (or maybe dispatch) how far we got.
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

  // This way drush_return_status() will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
  // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
  register_shutdown_function('drush_return_status');
}

/**
 * Shutdown function to save code coverage data.
 */
function drush_coverage_shutdown() {
  if ($file_name = drush_get_context('DRUSH_CODE_COVERAGE', FALSE)) {
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();

    // If coverage dump file contains anything, merge in the old data before
    // saving. This happens if the current drush command invoked another drush
    // command.
    if (file_exists($file_name) && $content = file_get_contents($file_name)) {
      $merge_data = unserialize($content);
      if (is_array($merge_data)) {
        foreach ($merge_data as $file => $lines) {
          if (!isset($data[$file])) {
            $data[$file] = $lines;
          }
          else {
            foreach ($lines as $num => $executed) {
              if (!isset($data[$file][$num])) {
                $data[$file][$num] = $executed;
              }
              else {
                $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
              }
            }
          }
        }
      }
    }

    file_put_contents($file_name, serialize($data));
  }
}

function drush_return_status() {
  // If a specific exit code was set, then use it.
  $exit_code = drush_get_context('DRUSH_EXIT_CODE');
  if (empty($exit_code)) {
    $exit_code = (drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS;
  }

  exit($exit_code);
}
