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
