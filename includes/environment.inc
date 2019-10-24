<?php

/**
 * @file
 * Functions used by drush to query the environment and
 * setting the current configuration.
 *
 * Bootstrapping now occurs in bootstrap.inc.
 *
 * @see includes/bootstrap.inc
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use Webmozart\PathUtil\Path;

/**
 * Log PHP errors to the Drush log. This is in effect until Drupal's error
 * handler takes over.
 */
function drush_error_handler($errno, $message, $filename, $line) {
  // E_DEPRECATED was added in PHP 5.3. Drupal 6 will not fix all the
  // deprecated errors, but suppresses them. So we suppress them as well.
  if (defined('E_DEPRECATED')) {
    $errno = $errno & ~E_DEPRECATED;
  }

  // "error_reporting" is usually set in php.ini, but may be changed by
  // drush_errors_on() and drush_errors_off().
  if ($errno & error_reporting()) {
    // By default we log notices.
    $type = drush_get_option('php-notices', 'notice');
    $halt_on_error = drush_get_option('halt-on-error', (drush_drupal_major_version() != 6));

    // Bitmask value that constitutes an error needing to be logged.
    $error = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
    if ($errno & $error) {
      $type = 'error';
    }

    // Bitmask value that constitutes a warning being logged.
    $warning = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING;
    if ($errno & $warning) {
      $type = LogLevel::WARNING;
    }

    drush_log($message . ' ' . basename($filename) . ':' . $line, $type);

    if ($errno == E_RECOVERABLE_ERROR && $halt_on_error) {
      drush_log(dt('E_RECOVERABLE_ERROR encountered; aborting. To ignore recoverable errors, run again with --no-halt-on-error'), 'error');
      exit(DRUSH_APPLICATION_ERROR);
    }

    return TRUE;
  }
}

/**
 * Returns a localizable message about php.ini that
 * varies depending on whether the php_ini_loaded_file()
 * is available or not.
 */
function _drush_php_ini_loaded_file_message() {
  if (function_exists('php_ini_loaded_file')) {
    return dt('Please check your configuration settings in !phpini or in your drush.ini file; see examples/example.drush.ini for details.', array('!phpini' => php_ini_loaded_file()));
  }
  else {
    return dt('Please check your configuration settings in your php.ini file or in your drush.ini file; see examples/example.drush.ini for details.');
  }
}

/**
 * Evalute the environment after an abnormal termination and
 * see if we can determine any configuration settings that the user might
 * want to adjust.
 */
function _drush_postmortem() {
  // Make sure that the memory limit has been bumped up from the minimum default value of 32M.
  $php_memory_limit = drush_memory_limit();
  if (($php_memory_limit > 0) && ($php_memory_limit <= 32*DRUSH_KILOBYTE*DRUSH_KILOBYTE)) {
    drush_set_error('DRUSH_MEMORY_LIMIT', dt('Your memory limit is set to !memory_limit; Drush needs as much memory to run as Drupal.  !php_ini_msg', array('!memory_limit' => $php_memory_limit / (DRUSH_KILOBYTE*DRUSH_KILOBYTE) . 'M', '!php_ini_msg' => _drush_php_ini_loaded_file_message())));
  }
}

/**
 * Evaluate the environment before command bootstrapping
 * begins.  If the php environment is too restrictive, then
 * notify the user that a setting change is needed and abort.
 */
function _drush_environment_check_php_ini() {
  $ini_checks = array('safe_mode' => '', 'open_basedir' => '', 'disable_functions' => array('exec', 'system'), 'disable_classes' => '');

  // Test to insure that certain php ini restrictions have not been enabled
  $prohibited_list = array();
  foreach ($ini_checks as $prohibited_mode => $disallowed_value) {
    $ini_value = ini_get($prohibited_mode);
    $invalid_value = FALSE;
    if (empty($disallowed_value)) {
      $invalid_value = !empty($ini_value) && (strcasecmp($ini_value, 'off') != 0);
    }
    else {
      foreach ($disallowed_value as $test_value) {
        if (preg_match('/(^|,)' . $test_value . '(,|$)/', $ini_value)) {
          $invalid_value = TRUE;
        }
      }
    }
    if ($invalid_value) {
      $prohibited_list[] = $prohibited_mode;
    }
  }
  if (!empty($prohibited_list)) {
    drush_log(dt('The following restricted PHP modes have non-empty values: !prohibited_list. This configuration is incompatible with drush.  !php_ini_msg', array('!prohibited_list' => implode(' and ', $prohibited_list), '!php_ini_msg' => _drush_php_ini_loaded_file_message())), LogLevel::ERROR);
  }

  return TRUE;
}

/**
 * Returns the current working directory.
 *
 * This is the directory as it was when drush was started, not the
 * directory we are currently in. For that, use getcwd() directly.
 */
function drush_cwd() {
  if ($path = drush_get_context('DRUSH_OLDCWD')) {
    return $path;
  }
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find.
  // $_SERVER['PWD'] isn't set on windows and generates a Notice.
  $path = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : '';
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = Path::canonicalize($path);

  // Save original working dir case some command wants it.
  drush_set_context('DRUSH_OLDCWD', $path);

  // Make a read-only copy of the original cwd at env.cwd
  // for forwards-compatibility
  drush_set_option('env.cwd', $path);

  return $path;
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 * Also converts a cygwin "drive emulation" path (/cygdrive/c/dir1) into a
 * proper drive path, still with Unix slashes (c:/dir1).
 */
function _drush_convert_path($path) {
  $path = str_replace('\\','/', $path);
  if (drush_is_windows(_drush_get_os()) && !drush_is_cygwin(_drush_get_os())) {
    $path = preg_replace('/^\/cygdrive\/([A-Za-z])(.*)$/', '\1:\2', $path);
  }

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
  $path = explode(DIRECTORY_SEPARATOR, $path);
  // Move one directory up.
  array_pop($path);
  return implode(DIRECTORY_SEPARATOR, $path);
  // return dirname($path);
}

/**
 * Like Drupal conf_path, but searching from beneath.
 * Allows proper site uri detection in site sub-directories.
 *
 * Essentially looks for a settings.php file.  Drush uses this
 * function to find a usable site based on the user's current
 * working directory.
 *
 * @param string
 *   Search starting path. Defaults to current working directory.
 *
 * @return
 *   Current site path (folder containing settings.php) or FALSE if not found.
 */
function drush_site_path($path = NULL) {
  $site_path = FALSE;

  $path = empty($path) ? drush_cwd() : $path;
  // Check the current path.
  if (file_exists($path . '/settings.php')) {
    $site_path = $path;
  }
  else {
    // Move up dir by dir and check each.
    // Stop if we get to a Drupal root.   We don't care
    // if it is DRUSH_SELECTED_DRUPAL_ROOT or some other root.
    while (($path = _drush_shift_path_up($path)) && !drush_valid_root($path)) {
      if (file_exists($path . '/settings.php')) {
        $site_path = $path;
        break;
      }
    }
  }

  $site_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
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

  return $site_path;
}

/**
 * Lookup a site's directory via the sites.php file given a hostname.
 *
 * @param $hostname
 *   The hostname of a site.  May be converted from URI.
 *
 * @return $dir
 *   The directory associated with that hostname or FALSE if not found.
 */
function drush_site_dir_lookup_from_hostname($hostname, $site_root = NULL) {
  if (!isset($site_root)) {
    $site_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  }
  if (!empty($site_root) && file_exists($site_root . '/sites/sites.php')) {
    $sites = array();
    // This will overwrite $sites with the desired mappings.
    include($site_root . '/sites/sites.php');
    return isset($sites[$hostname]) ? $sites[$hostname] : FALSE;
  }
  else {
    return FALSE;
  }
}

/**
 * This is a copy of Drupal's conf_path function, taken from D7 and
 * adjusted slightly to search from the selected Drupal Root.
 *
 * Drush uses this routine to find a usable site based on a URI
 * passed in via a site alias record or the --uri commandline option.
 *
 * Drush uses Drupal itself (specifically, the Drupal conf_path function)
 * to bootstrap the site itself.  If the implementation of conf_path
 * changes, the site should still bootstrap correctly; the only consequence
 * of this routine not working is that drush configuration files
 * (drushrc.php) stored with the site's drush folders might not be found.
 */
function drush_conf_path($server_uri, $require_settings = TRUE) {
  $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  if(empty($drupal_root) || empty($server_uri)) {
    return NULL;
  }
  $parsed_uri = parse_url($server_uri);
  if (is_array($parsed_uri) && !array_key_exists('scheme', $parsed_uri)) {
    $parsed_uri = parse_url('http://' . $server_uri);
  }
  if (!is_array($parsed_uri)) {
    return NULL;
  }
  $server_host = $parsed_uri['host'];
  if (array_key_exists('path', $parsed_uri)) {
    $server_uri = $parsed_uri['path'] . '/index.php';
  }
  else {
    $server_uri = "/index.php";
  }
  $confdir = 'sites';

  $sites = array();
  if (file_exists($drupal_root . '/' . $confdir . '/sites.php')) {
    // This will overwrite $sites with the desired mappings.
    include($drupal_root . '/' . $confdir . '/sites.php');
  }

  $uri = explode('/', $server_uri);
  $server = explode('.', implode('.', array_reverse(explode(':', rtrim($server_host, '.')))));
  for ($i = count($uri) - 1; $i > 0; $i--) {
    for ($j = count($server); $j > 0; $j--) {
      $dir = implode('.', array_slice($server, -$j)) . implode('.', array_slice($uri, 0, $i));
      if (isset($sites[$dir]) && file_exists($drupal_root . '/' . $confdir . '/' . $sites[$dir])) {
        $dir = $sites[$dir];
      }
      if (file_exists($drupal_root . '/' . $confdir . '/' . $dir . '/settings.php') || (!$require_settings && file_exists(DRUPAL_ROOT . '/' . $confdir . '/' . $dir))) {
        $conf = "$confdir/$dir";
        return $conf;
      }
    }
  }
  $conf = "$confdir/default";
  return $conf;
}

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run Drush from a subdirectory of the drupal root.
 *
 * @param
 *   Search start path. Defaults to current working directory.
 * @return
 *   A path to drupal root, or FALSE if not found.
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
    if (drush_valid_root($path)) {
      $drupal_root = $path;
      break;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if ($follow_symlinks && is_link($path)) {
          $path = realpath($path);
        }
        if (drush_valid_root($path)) {
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
 * @return string
 *   The relative path to common.inc (varies by Drupal version), or FALSE if not
 *   a Drupal root.
 */
function drush_valid_root($path) {
  $bootstrap_class = drush_bootstrap_class_for_root($path);
  return $bootstrap_class != NULL;
}

/**
 * Tests the currently loaded database credentials to ensure a database connection can be made.
 *
 * @param bool $log_errors
 *   (optional) If TRUE, log error conditions; otherwise be quiet.
 *
 * @return bool
 *   TRUE if database credentials are valid.
 */
function drush_valid_db_credentials() {
  try {
    $sql = drush_sql_get_class();
    if (!$sqlVersion = drush_sql_get_version()) {
      drush_log(dt('While checking DB credentials, could not instantiate SQLVersion class.'), 'debug');
      return FALSE;
    }
    if (!$sqlVersion->valid_credentials($sql->db_spec())) {
      drush_log(dt('DB credentials are invalid.'), 'debug');
      return FALSE;
    }
    return $sql->query('SELECT 1;');
  }
  catch (Exception $e) {
    drush_log(dt('Checking DB credentials yielded error: @e', array('@e' => $e->getMessage())), 'debug');
    return FALSE;
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
 * The DRUSH_COMMAND constant is initialised to the value of this
 * function when environment.inc is loaded.
 *
 * @see DRUSH_COMMAND
 */
function drush_find_drush() {
  if ($drush = realpath($_SERVER['argv']['0'])) {
    return Path::canonicalize($drush);
  }
  return FALSE;
}

/**
 * Verify that we are running PHP through the command line interface.
 *
 * This function is useful for making sure that code cannot be run via the web server,
 * such as a function that needs to write files to which the web server should not have
 * access to.
 *
 * @return
 *   A boolean value that is true when PHP is being run through the command line,
 *   and false if being run through cgi or mod_php.
 */
function drush_verify_cli() {
  return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
}

/**
 * Build a drush command suitable for use for Drush to call itself
 * e.g. in backend_invoke.
 */
function drush_build_drush_command($drush_path = NULL, $php = NULL, $os = NULL, $remote_command = FALSE, $environment_variables = array()) {
  $os = _drush_get_os($os);
  $additional_options = '';
  $prefix = '';
  if (!$drush_path) {
    if (!$remote_command) {
      $drush_path = DRUSH_COMMAND;
    }
    else {
      $drush_path = 'drush'; // drush_is_windows($os) ? 'drush.bat' : 'drush';
    }
  }
  // If the path to drush points to drush.php, then we will need to
  // run it via php rather than direct execution.  By default, we
  // will use 'php' unless something more specific was passed in
  // via the --php flag.
  if (substr($drush_path, -4) == ".php") {
    if (!isset($php)) {
      $php = drush_get_option('php');
      if (!isset($php)) {
        $php = 'php';
      }
    }
    if (isset($php) && ($php != "php")) {
      $additional_options .= ' --php=' . drush_escapeshellarg($php, $os);
    }
    // We will also add in the php options from --php-options
    $prefix .= drush_escapeshellarg($php, $os);
    $php_options = implode(' ', drush_get_context_options('php-options'));
    if (!empty($php_options)) {
      $prefix .= ' ' . $php_options;
      $additional_options .= ' --php-options=' . drush_escapeshellarg($php_options, $os);
    }
  }
  else {
    // Set environment variables to propogate config to redispatched calls.
    if (drush_has_bash($os)) {
      if ($php) {
        $environment_variables['DRUSH_PHP'] = $php;
      }
      if ($php_options_alias = drush_get_option('php-options', NULL, 'alias')) {
        $environment_variables['PHP_OPTIONS'] = $php_options_alias;
      }
      $columns = drush_get_context('DRUSH_COLUMNS');
      if (($columns) && ($columns != 80)) {
        $environment_variables['COLUMNS'] = $columns;
      }
    }
  }

  // Add environmental variables, if present
  if (!empty($environment_variables)) {
    $env = 'env';
    foreach ($environment_variables as $key=>$value) {
      $env .= ' ' . drush_escapeshellarg($key, $os) . '=' . drush_escapeshellarg($value, $os);
    }
    $prefix = $env . ' ' . $prefix;
  }

  return trim($prefix . ' ' . drush_escapeshellarg($drush_path, $os) . $additional_options);
}

/**
 * Check if the operating system is Winodws
 * running some variant of cygwin -- either
 * Cygwin or the MSYSGIT shell.  If you care
 * which is which, test mingw first.
 */
function drush_is_cygwin($os = NULL) {
  return _drush_test_os($os, array("CYGWIN","CWRSYNC","MINGW"));
}

function drush_is_mingw($os = NULL) {
  return _drush_test_os($os, array("MINGW"));
}

/**
 * Return tar executable name specific for the current OS
 */
function drush_get_tar_executable() {
  return drush_is_windows() ? (drush_is_mingw() ? "tar.exe" : "bsdtar.exe") : "tar";
}

/**
 * Check if the operating system is OS X.
 * This will return TRUE for Mac OS X (Darwin).
 */
function drush_is_osx($os = NULL) {
  return _drush_test_os($os, array("DARWIN"));
}

/**
 * Checks if the operating system has bash.
 *
 * MinGW has bash, but PHP isn't part of MinGW and hence doesn't run in bash.
 */
function drush_has_bash($os = NULL) {
  return (drush_is_cygwin($os) && !drush_is_mingw($os)) || !drush_is_windows($os);
}

/**
 * Checks operating system and returns
 * supported bit bucket folder.
 */
function drush_bit_bucket() {
  if (drush_has_bash()) {
    return '/dev/null';
  }
  else {
    return 'nul';
  }
}

/**
 * Return the OS we are running under.
 *
 * @return string
 *   Linux
 *   WIN* (e.g. WINNT)
 *   CYGWIN
 *   MINGW* (e.g. MINGW32)
 */
function _drush_get_os($os = NULL) {
  // The special os "CWRSYNC" can be used to indicate that we are testing
  // a path that will be passed as an argument to cwRsync, which requires
  // that the path be converted to /cygdrive/c/path, even on DOS or Powershell.
  // The special os "RSYNC" can be used to indicate that we want to assume
  // "CWRSYNC" when cwrsync is installed, or default to the local OS otherwise.
  if (strtoupper($os) == "RSYNC") {
    $os = _drush_get_os("LOCAL");
    // For now we assume that cwrsync is always installed on Windows, and never installed son any other platform.
    return drush_is_windows($os) ? "CWRSYNC" : $os;
  }
  // We allow "LOCAL" to document, in instances where some parameters are being escaped
  // for use on a remote machine, that one particular parameter will always be used on
  // the local machine (c.f. drush_backend_invoke).
  if (isset($os) && ($os != "LOCAL")) {
    return $os;
  }
  if (_drush_test_os(getenv("MSYSTEM"), array("MINGW"))) {
    return getenv("MSYSTEM");
  }
  // QUESTION: Can we differentiate between DOS and POWERSHELL? They appear to have the same environment.
  // At the moment, it does not seem to matter; they behave the same from PHP.
  // At this point we will just return PHP_OS.
  return PHP_OS;
}

function _drush_test_os($os, $os_list_to_check) {
  $os = _drush_get_os($os);
  foreach ($os_list_to_check as $test) {
    if (strtoupper(substr($os, 0, strlen($test))) == strtoupper($test)) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Read the drush info file.
 */
function drush_read_drush_info() {
  return Drush::ReadDrushInfo();
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
  // Check to see if the provided host is "local".
  // @see hook_drush_sitealias_alter() in drush.api.php.
  if (
    ($host == 'localhost') ||
    ($host == '127.0.0.1')
  ) {
    return TRUE;
  }

  return FALSE;
}

/**
 * Return the user's home directory.
 */
function drush_server_home() {
  try {
    return Path::getHomeDirectory();
  } catch (Exception $e) {
    return NULL;
  }
}

/**
 * Return the name of the user running drush.
 */
function drush_get_username() {
  $name = NULL;
  if (!$name = getenv("username")) { // Windows
    if (!$name = getenv("USER")) {
      // If USER not defined, use posix
      if (function_exists('posix_getpwuid')) {
        $processUser = posix_getpwuid(posix_geteuid());
        $name = $processUser['name'];
      }
    }
  }
  return $name;
}

/**
 * The path to the global cache directory.
 *
 * @param subdir
 *   Return the specified subdirectory inside the global
 *   cache directory instead.  The subdirectory is created.
 */
function drush_directory_cache($subdir = '') {
  $cache_locations = array();
  if (getenv('CACHE_PREFIX')) {
    $cache_locations[getenv('CACHE_PREFIX')] = 'cache';
  }
  $home = drush_server_home();
  if ($home) {
    $cache_locations[$home] = '.drush/cache';
  }
  $cache_locations[drush_find_tmp()] = 'drush-' . drush_get_username() . '/cache';
  foreach ($cache_locations as $base => $dir) {
    if (!empty($base) && is_writable($base)) {
      $cache_dir = $base . '/' . $dir;
      if (!empty($subdir)) {
        $cache_dir .= '/' . $subdir;
      }
      if (drush_mkdir($cache_dir)) {
        return $cache_dir;
      }
      else {
        // If the base directory is writable, but the cache directory
        // is not, then we will get an error. The error will be displayed,
        // but we will still call drush_clear_error so that we can go
        // on and try the next location to see if we can find a cache
        // directory somewhere.
        drush_clear_error();
      }
    }
  }
  return drush_set_error('DRUSH_NO_WRITABLE_CACHE', dt('Drush must have a writable cache directory; please insure that one of the following locations is writable: @locations',
    array('@locations' => implode(',', array_keys($cache_locations)))));
}

/**
 * Get complete information for all available extensions (modules and themes).
 *
 * @return
 *   An array containing info for all available extensions. In D8, these are Extension objects.
 */
function drush_get_extensions($include_hidden = TRUE) {
  drush_include_engine('drupal', 'environment');
  $extensions = array_merge(drush_get_modules($include_hidden), drush_get_themes($include_hidden));
  foreach ($extensions as $name => $extension) {
    if (isset($extension->info['name'])) {
      $extensions[$name]->label = $extension->info['name'].' ('.$name.')';
    }
    else {
      drush_log(dt("Extension !name provides no human-readable name in .info file.", array('!name' => $name), LogLevel::DEBUG));
      $extensions[$name]->label = $name.' ('.$name.')';
    }
    if (empty($extension->info['package'])) {
      $extensions[$name]->info['package'] = dt('Other');
    }
  }
  return $extensions;
}

/**
 * Gets the extension name.
 *
 * @param $info
 *   The extension info.
 * @return string
 *   The extension name.
 */
function drush_extension_get_name($info) {
  drush_include_engine('drupal', 'environment');
  return _drush_extension_get_name($info);
}

/**
 * Gets the extension type.
 *
 * @param $info
 *   The extension info.
 * @return string
 *   The extension type.
 */
function drush_extension_get_type($info) {
  drush_include_engine('drupal', 'environment');
  return _drush_extension_get_type($info);
}

/**
 * Gets the extension path.
 *
 * @param $info
 *   The extension info.
 * @return string
 *   The extension path.
 */
function drush_extension_get_path($info) {
  drush_include_engine('drupal', 'environment');
  return _drush_extension_get_path($info);
}

/**
 * Test compatibility of a extension with version of drupal core and php.
 *
 * @param $file Extension object as returned by system_rebuild_module_data().
 * @return FALSE if the extension is compatible.
 */
function drush_extension_check_incompatibility($file) {
  // TODO: what is the right way to detect core modules?
  if (isset($file->info['package']) && ($file->info['package'] == 'Core')) {
    return FALSE;
  }
  // TODO: validate core_version_requirement
  if (isset($file->info['core_version_requirement'])) {
    return FALSE;
  }
  if (!isset($file->info['core']) || $file->info['core'] != drush_get_drupal_core_compatibility()) {
    return 'Drupal';
  }
  if (version_compare(phpversion(), $file->info['php']) < 0) {
    return 'PHP';
  }
  return FALSE;
}

/**
 *
 */
function drush_drupal_required_modules($modules) {
  drush_include_engine('drupal', 'environment');
  return _drush_drupal_required_modules($modules);
}

/**
 * Return the default theme.
 *
 * @return
 *  Machine name of the default theme.
 */
function drush_theme_get_default() {
  drush_include_engine('drupal', 'environment');
  return _drush_theme_default();
}

/**
 * Return the administration theme.
 *
 * @return
 *  Machine name of the administration theme.
 */
function drush_theme_get_admin() {
  drush_include_engine('drupal', 'environment');
  return _drush_theme_admin();
}

/**
 * Return the path to public files directory.
 */
function drush_file_get_public() {
  drush_include_engine('drupal', 'environment');
  return _drush_file_public_path();
}

/**
 * Return the path to private files directory.
 */
function drush_file_get_private() {
  drush_include_engine('drupal', 'environment');
  return _drush_file_private_path();
}

/**
 * Returns the sitewide Drupal directory for extensions.
 */
function drush_drupal_sitewide_directory($major_version = NULL) {
  if (!$major_version) {
    $major_version = drush_drupal_major_version();
  }
  return ($major_version < 8) ? 'sites/all' : '';
}

/**
 * Helper function to get core compatibility constant.
 *
 * @return string
 *   The Drupal core compatibility constant.
 */
function drush_get_drupal_core_compatibility() {
  if (defined('DRUPAL_CORE_COMPATIBILITY')) {
    return DRUPAL_CORE_COMPATIBILITY;
  }
  elseif (defined('\Drupal::CORE_COMPATIBILITY')) {
    return \Drupal::CORE_COMPATIBILITY;
  }
}

/**
 * Set Env. Variables for given site-alias.
 */
function drush_set_environment_vars(array $site_record) {
  if (!empty($site_record)) {
    $os = drush_os($site_record);
    if (isset($site_record['#env-vars'])) {
      foreach ($site_record['#env-vars'] as $var => $value) {
        $env_var = drush_escapeshellarg($var, $os, TRUE) . '=' . drush_escapeshellarg($value, $os, TRUE);
        putenv($env_var);
      }
    }
  }
}
