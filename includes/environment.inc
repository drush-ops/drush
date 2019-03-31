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
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 * Also converts a cygwin "drive emulation" path (/cygdrive/c/dir1) into a
 * proper drive path, still with Unix slashes (c:/dir1).
 */
function _drush_convert_path($path) {
  $path = str_replace('\\','/', $path);
  if (drush_is_windows() && !drush_is_cygwin()) {
    $path = preg_replace('/^\/cygdrive\/([A-Za-z])(.*)$/', '\1:\2', $path);
  }

  return $path;
}

/**
 * Build a drush command suitable for use for Drush to call itself.
 * Used in backend_invoke.
 *
 * @deprecated.
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
 * Checks operating system and returns supported bit bucket folder.
 */
function drush_bit_bucket() {
  return drush_has_bash() ? '/dev/null' : 'nul';
}

/**
 * Return the OS we are running under.
 *
 * @return string
 *   Linux
 *   WIN* (e.g. WINNT)
 *   CYGWIN
 *   MINGW* (e.g. MINGW32)
 *
 * @deprecated. Use \Consolidation\SiteProcess\Util\Escape.
 */
function _drush_get_os($os = NULL) {
  // In most cases, $os will be NULL and PHP_OS will be returned. However, if an
  // OS is specified in $os, return that instead.
  return $os ?: PHP_OS;
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
  return $host == 'localhost' || $host == '127.0.0.1';
}

/**
 * Determine whether current OS is a Windows variant.
 *
 * @deprecated. Use \Consolidation\SiteProcess\Util\Escape.
 */
function drush_is_windows($os = NULL) {
  return strtoupper(substr(_drush_get_os($os), 0, 3)) === 'WIN';
}

/**
 * @deprecated. Use \Consolidation\SiteProcess\Util\Escape.
 */
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
 * because the Windows version behaves differently
 *
 * @deprecated. Use \Consolidation\SiteProcess\Util\Escape.
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
 *
 * @deprecated. Use \Consolidation\SiteProcess\Util\Escape.
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
