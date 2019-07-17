<?php

use Drush\Drush;
use Drush\Log\LogLevel;
use Webmozart\PathUtil\Path;

/**
 * Check if the operating system is OS X.
 * This will return TRUE for Mac OS X (Darwin).
 */
function drush_is_osx($os = NULL) {
  return _drush_test_os($os, ["DARWIN"]);
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