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
function drush_error_handler($errno, $message, $filename, $line, $context) {
  // E_DEPRECATED was added in PHP 5.3. Drupal 6 will not fix all the
  // deprecated errors, but suppresses them. So we suppress them as well.
  if (defined('E_DEPRECATED')) {
    $errno = $errno & ~E_DEPRECATED;
  }

  // "error_reporting" is usually set in php.ini, but may be changed by
  // drush_errors_on() and drush_errors_off().
  if ($errno & error_reporting()) {
    // By default we log notices.
    $type = Drush::config()->get('runtime.php.notices', LogLevel::INFO);
    $halt_on_error = Drush::config()->get('runtime.php.halt-on-error', (drush_drupal_major_version() != 6));

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
 * Evalute the environment after an abnormal termination and
 * see if we can determine any configuration settings that the user might
 * want to adjust.
 */
function _drush_postmortem() {
  // Make sure that the memory limit has been bumped up from the minimum default value of 32M.
  $php_memory_limit = drush_memory_limit();
  if (($php_memory_limit > 0) && ($php_memory_limit <= 32*DRUSH_KILOBYTE*DRUSH_KILOBYTE)) {
    drush_set_error('DRUSH_MEMORY_LIMIT', dt('Your memory limit is set to !memory_limit; Drush needs as much memory to run as Drupal.  !php_ini_msg', ['!memory_limit' => $php_memory_limit / (DRUSH_KILOBYTE*DRUSH_KILOBYTE) . 'M', '!php_ini_msg' => _drush_php_ini_loaded_file_message()]));
  }
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
 * Build a drush command suitable for use for Drush to call itself.
 * Used in backend_invoke.
 */
function drush_build_drush_command($drush_path = NULL, $php = NULL, $os = NULL, $remote_command = FALSE, $environment_variables = []) {
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
      $php = Drush::config()->get('php', 'php');
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
    $prefix .= ' env';
    foreach ($environment_variables as $key=>$value) {
      $prefix .= ' ' . drush_escapeshellarg($key, $os) . '=' . drush_escapeshellarg($value, $os);
    }
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
  return _drush_test_os($os, ["CYGWIN","CWRSYNC","MINGW"]);
}

function drush_is_mingw($os = NULL) {
  return _drush_test_os($os, ["MINGW"]);
}

/**
 * Check if the operating system is OS X.
 * This will return TRUE for Mac OS X (Darwin).
 */
function drush_is_osx($os = NULL) {
  return _drush_test_os($os, ["DARWIN"]);
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
  if (_drush_test_os(getenv("MSYSTEM"), ["MINGW"])) {
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
 * The path to the global cache directory.
 *
 * @param subdir
 *   Return the specified subdirectory inside the global
 *   cache directory instead.  The subdirectory is created.
 */
function drush_directory_cache($subdir = '') {
  $cache_locations = [];
  if (getenv('CACHE_PREFIX')) {
    $cache_locations[getenv('CACHE_PREFIX')] = 'cache';
  }
  $home = Drush::config()->home();
  if ($home) {
    $cache_locations[$home] = '.drush/cache';
  }
  $cache_locations[Drush::config()->tmp()] = 'drush-' . Drush::config()->user() . '/cache';
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
    ['@locations' => implode(',', array_keys($cache_locations))]));
}

/**
 * Determine whether current OS is a Windows variant.
 */
function drush_is_windows($os = NULL) {
  return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
}

function drush_escapeshellarg($arg, $os = NULL, $raw = FALSE) {
  // Short-circuit escaping for simple params (keep stuff readable)
  if (preg_match('|^[a-zA-Z0-9.:/_-]*$|', $arg)) {
    return $arg;
  }
  elseif (drush_is_windows($os)) {
    return _drush_escapeshellarg_windows($arg, $raw);
  }
  else {
    return _drush_escapeshellarg_linux($arg, $raw);
  }
}

/**
 * Linux version of escapeshellarg().
 *
 * This is intended to work the same way that escapeshellarg() does on
 * Linux.  If we need to escape a string that will be used remotely on
 * a Linux system, then we need our own implementation of escapeshellarg,
 * because the Windows version behaves differently.
 */
function _drush_escapeshellarg_linux($arg, $raw = FALSE) {
  // For single quotes existing in the string, we will "exit"
  // single-quote mode, add a \' and then "re-enter"
  // single-quote mode.  The result of this is that
  // 'quote' becomes '\''quote'\''
  $arg = preg_replace('/\'/', '\'\\\'\'', $arg);

  // Replace "\t", "\n", "\r", "\0", "\x0B" with a whitespace.
  // Note that this replacement makes Drush's escapeshellarg work differently
  // than the built-in escapeshellarg in PHP on Linux, as these characters
  // usually are NOT replaced. However, this was done deliberately to be more
  // conservative when running _drush_escapeshellarg_linux on Windows
  // (this can happen when generating a command to run on a remote Linux server.)
  $arg = str_replace(["\t", "\n", "\r", "\0", "\x0B"], ' ', $arg);

  // Only wrap with quotes when needed.
  if(!$raw) {
    // Add surrounding quotes.
    $arg = "'" . $arg . "'";
  }

  return $arg;
}

/**
 * Windows version of escapeshellarg().
 */
function _drush_escapeshellarg_windows($arg, $raw = FALSE) {
  // Double up existing backslashes
  $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

  // Double up double quotes
  $arg = preg_replace('/"/', '""', $arg);

  // Double up percents.
  // $arg = preg_replace('/%/', '%%', $arg);

  // Only wrap with quotes when needed.
  if(!$raw) {
    // Add surrounding quotes.
    $arg = '"' . $arg . '"';
  }

  return $arg;
}
