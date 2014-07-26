<?php

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
  $local_vendor_path = DRUSH_BASE_PATH . '/vendor/autoload.php';
  $global_vendor_path = DRUSH_BASE_PATH . '/../../../vendor/autoload.php';

  // Check for a local composer install or a global composer install. Vendor dirs are in different spots).
  if ((!@include $local_vendor_path) && (!@include $global_vendor_path)) {
    $msg = "Unable to load autoload.php. Drush now requires Composer in order to install its dependencies and autoload classes. Please see README.md\n";
    fwrite(STDERR, $msg);
    return FALSE;
  }

  require_once DRUSH_BASE_PATH . '/includes/environment.inc';
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

  // Terminate immediately unless invoked as a command line script
  if (!drush_verify_cli()) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Drush is designed to run via the command line.'));
  }

  // Check supported version of PHP.
  // Note: If this is adjusted, check other code that compares
  // PHP_VERSION, such as drush_json_encode(), runserver/runserver.drush.inc, and also
  // adjust _drush_environment_check_php_ini() and the php_prohibited_options
  // list in the drush script.  See http://drupal.org/node/1748228
  define('DRUSH_MINIMUM_PHP', '5.3.0');
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
}

/**
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
 * the execution flow to be able to take affect.
 */
function drush_preflight() {
  // Create an alias '@none' to represent no Drupal site
  _drush_sitealias_cache_alias('@none', array('root' => '', 'uri' => ''));

  // Set the terminal width, used for wrapping table output.
  // Normally this is exported using tput in the drush script.
  // If this is not present we do an additional check using stty here.
  // On Windows in CMD and PowerShell is this exported using mode con.
  if (!($columns = getenv('COLUMNS'))) {
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
        drush_log(dt('Drush could not detect the console window width. Set a Windows Environment Variable of COLUMNS to the desired width.'), 'warning');
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

  // Display is tidy now that column width has been handled.
  drush_log(dt('Starting Drush preflight.'), 'preflight');

  // Statically define a way to call drush again.
  define('DRUSH_COMMAND', drush_find_drush());

  // prime the CWD cache
  drush_cwd();

  // Set up base environment for system-wide file locations.
  _drush_preflight_base_environment();

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

  // Determine if --root and --uri have been defined.
  _drush_bootstrap_select_drupal_site();

  // Process the site alias that specifies which instance
  // of Drush (local or remote) this command will operate on.
  // We must do this after we load our config files (so that
  // site aliases are available), but before the rest of
  // Drush preflight and Drupal root bootstrap phase are
  // done, since site aliases may set option values that
  // affect these phases.
  drush_sitealias_check_arg();

  // If an alias was not created by drush_sitealias_check_arg()
  // or drush_sitealias_check_site_env(), make one now.
  drush_sitealias_create_self_alias();

  // Load the config options from Drupal's /drush and sites/all/drush directories,
  // even prior to bootstrapping the root.
  drush_load_config('drupal');

  // Similarly, load the Drupal site configuration options upfront.
  drush_load_config('site');

  // Check to see if we 'use'd a site alias with site-set
  drush_sitealias_check_site_env();

  // If applicable swaps in shell alias value (or executes it).
  drush_shell_alias_replace();

  // @todo Move this code?
  // If drush_load_config defined a site alias that did not
  // exist before, then sitealias check arg might now match
  // against one of those aliases.
  if ((drush_sitealias_check_arg() === TRUE) || (drush_sitealias_check_site_env() === TRUE)) {
    $remote_host = drush_get_option('remote-host');
    if (!isset($remote_host)) {
      // Load the config files for the "new" site.
      drush_load_config('drupal');
      drush_load_config('site');
    }
  }

  _drush_preflight_global_options();

  _drush_find_commandfiles_drush();
}

function _drush_preflight_global_options() {
  // Debug implies verbose
  drush_set_context('DRUSH_VERBOSE',     drush_get_option(array('verbose', 'debug'), FALSE));
  drush_set_context('DRUSH_DEBUG', drush_get_option('debug'));
  drush_set_context('DRUSH_SIMULATE',    drush_get_option('simulate', FALSE));
  drush_set_context('DRUSH_BACKEND', drush_get_option('backend'));

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
  $alias_path =& drush_get_context('ALIAS_PATH');
  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $default_prefix_commandfile = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '/usr';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $site_wide_commandfile_dir = drush_get_context('SHARE_PREFIX', $default_prefix_commandfile) . '/share/drush/commands';
  $alias_path[] = $site_wide_configuration_dir;
  drush_set_context('DRUSH_SITE_WIDE_CONFIGURATION', $site_wide_configuration_dir);
  drush_set_context('DRUSH_SITE_WIDE_COMMANDFILES', $site_wide_commandfile_dir);
  $alias_path[] = dirname(__FILE__) . '/..';
  $server_home = drush_server_home();
  if (isset($server_home)) {
    $alias_path[] = $server_home . '/.drush';
    drush_set_context('DRUSH_PER_USER_CONFIGURATION', $server_home . '/.drush');
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
  $uri = drush_get_option('uri', '');
  drush_set_context('DRUSH_SELECTED_URI', $uri);
  drush_sitealias_create_self_alias();
}

function _drush_find_commandfiles_drush() {
  // Core commands shipping with Drush
  $searchpath[] = realpath(dirname(__FILE__) . '/../commands/');

  // User commands, specified by 'include' option
  if ($include = drush_get_context('DRUSH_INCLUDE', FALSE)) {
    foreach ($include as $path) {
      if (is_dir($path)) {
        drush_log('Include ' . $path, 'notice');
        $searchpath[] = $path;
      }
    }
  }

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

  // @todo the zero parameter is a bit weird here. It's $phase.
  _drush_add_commandfiles($searchpath, 0);
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
  exit((drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS);
}
