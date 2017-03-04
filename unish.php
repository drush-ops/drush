#!/usr/bin/env php
<?php

/**
 * @file
 *   Initialize a sandboxed environment. Starts with call unish_init() at bottom.
 */

unish_init();
unish_setup_sut();
unish_start_phpunit();

/**
 * Initialize our environment at the start of each run (i.e. suite).
 */
function unish_init() {
  // Default drupal major version to run tests over.
  define('UNISH_DRUPAL_MAJOR_VERSION', '8');

  // We read from env then globals then default to mysql.
  $unish_db_url = 'mysql://root:@127.0.0.1';
  if (getenv('UNISH_DB_URL')) {
    $unish_db_url = getenv('UNISH_DB_URL');
  }
  elseif (isset($GLOBALS['UNISH_DB_URL'])) {
    $unish_db_url = $GLOBALS['UNISH_DB_URL'];
  }
  define('UNISH_DB_URL', $unish_db_url);

  // UNISH_DRUSH value can come from phpunit.xml.
  if (!defined('UNISH_DRUSH')) {
    // Let the UNISH_DRUSH environment variable override if set.
    $unish_drush = isset($_SERVER['UNISH_DRUSH']) ? $_SERVER['UNISH_DRUSH'] : NULL;
    $unish_drush = isset($GLOBALS['UNISH_DRUSH']) ? $GLOBALS['UNISH_DRUSH'] : $unish_drush;
    if (empty($unish_drush)) {
      // $script = \Unish\UnitUnishTestCase::is_windows() ? 'dr.bat' : 'drush';
      $script = 'drush';
      $unish_drush =  __DIR__ . DIRECTORY_SEPARATOR . $script;
    }
    define('UNISH_DRUSH', $unish_drush);
  }

  define('UNISH_TMP', realpath(getenv('UNISH_TMP') ? getenv('UNISH_TMP') : (isset($GLOBALS['UNISH_TMP']) ? $GLOBALS['UNISH_TMP'] : sys_get_temp_dir())));
  define('UNISH_SANDBOX', UNISH_TMP . DIRECTORY_SEPARATOR . 'drush-sandbox');
  define('UNISH_DRUSH_PHP', UNISH_SANDBOX. '/vendor/drush/drush/drush.php');

  // Cache dir lives outside the sandbox so that we get persistence across classes.
  define('UNISH_CACHE', UNISH_TMP . DIRECTORY_SEPARATOR . 'drush-cache');
  putenv("CACHE_PREFIX=" . UNISH_CACHE);
  // Wipe at beginning of run.
  if (file_exists(UNISH_CACHE)) {
    // TODO: We no longer clean up cache dir between runs. Much faster, but we
    // we should watch for subtle problems. To manually clean up, delete the
    // UNISH_TMP/drush-cache directory.
    // unish_file_delete_recursive($cache, TRUE);
  }
  else {
    $ret = mkdir(UNISH_CACHE, 0777, TRUE);
  }

  $home = UNISH_SANDBOX. '/home';
  putenv("HOME=$home");
  putenv("HOMEDRIVE=$home");
  $composer_home = UNISH_CACHE. '/.composer';
  putenv("COMPOSER_HOME=$composer_home");

  putenv('ETC_PREFIX=' . UNISH_SANDBOX);
  putenv('SHARE_PREFIX=' . UNISH_SANDBOX);
  putenv('TEMP=' . UNISH_TMP);
  putenv('DRUSH_SKIP_OWN_AUTOLOAD=1');

  define('UNISH_USERGROUP', isset($GLOBALS['UNISH_USERGROUP']) ? $GLOBALS['UNISH_USERGROUP'] : NULL);

  define('UNISH_BACKEND_OUTPUT_DELIMITER', 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END');
}

/**
 * Use Composer to build a Drupal codebase, with this Drush symlinked into /vendor.
 */
function unish_setup_sut() {
  unish_file_delete_recursive(UNISH_SANDBOX);
  $codebase = 'tests/resources/codebase';
  unish_recursive_copy($codebase, UNISH_SANDBOX);
  foreach (['composer.json', 'composer.lock'] as $file) {
    // We replace a token in these 2 files with the /path/to/drush for this install.
    // @todo Use https://getcomposer.org/doc/03-cli.md#modifying-repositories if it can edit composer.lock too.
    $contents = file_get_contents(UNISH_SANDBOX. "/$file");
    $new_contents = str_replace('%PATH-TO-DRUSH%', dirname(UNISH_DRUSH), $contents);
    file_put_contents(UNISH_SANDBOX. "/$file", $new_contents);
  }
  chdir(UNISH_SANDBOX);
  passthru('composer install --no-interaction --no-progress --no-suggest');

  // @todo This path is a bit legacy in D8.
  // mkdir(UNISH_SANDBOX . '/web/sites/all/drush', 0777, TRUE);
}

function unish_start_phpunit() {
  // Get the arguments for the command.
  $arguments = $GLOBALS['argv'];
  // Shift off argv[0] which contains the name of this script.
  array_shift($arguments);
  // Add the directory containing the phpunit bootstrap file.
  array_unshift($arguments, dirname(UNISH_DRUSH). '/tests');
  $cmd = UNISH_SANDBOX. '/vendor/bin/phpunit --configuration '. implode(' ', $arguments);
  echo "\n". $cmd. "\n";
  passthru($cmd);
}

/**
 * Deletes the specified file or directory and everything inside it.
 *
 * Usually respects read-only files and folders. To do a forced delete use
 * drush_delete_tmp_dir() or set the parameter $forced.
 *
 * To avoid permission denied error on Windows, make sure your CWD is not
 * inside the directory being deleted.
 *
 * This is essentially a copy of drush_delete_dir().
 *
 * @todo This sort of duplication isn't very DRY. This is bound to get out of
 *   sync with drush_delete_dir(), as in fact it already has before.
 *
 * @param string $dir
 *   The file or directory to delete.
 * @param bool $force
 *   Whether or not to try everything possible to delete the directory, even if
 *   it's read-only. Defaults to FALSE.
 * @param bool $follow_symlinks
 *   Whether or not to delete symlinked files. Defaults to FALSE--simply
 *   unlinking symbolic links.
 *
 * @return bool
 *   FALSE on failure, TRUE if everything was deleted.
 *
 * @see drush_delete_dir()
 */
function unish_file_delete_recursive($dir, $force = TRUE, $follow_symlinks = FALSE) {
  // Do not delete symlinked files, only unlink symbolic links
  if (is_link($dir) && !$follow_symlinks) {
    return unlink($dir);
  }
  // Allow to delete symlinks even if the target doesn't exist.
  if (!is_link($dir) && !file_exists($dir)) {
    return TRUE;
  }
  if (!is_dir($dir)) {
    if ($force) {
      // Force deletion of items with readonly flag.
      @chmod($dir, 0777);
    }
    return unlink($dir);
  }
  if (unish_delete_dir_contents($dir, $force) === FALSE) {
    return FALSE;
  }
  if ($force) {
    // Force deletion of items with readonly flag.
    @chmod($dir, 0777);
  }
  return rmdir($dir);
}

/**
 * Deletes the contents of a directory.
 *
 * This is essentially a copy of drush_delete_dir_contents().
 *
 * @param string $dir
 *   The directory to delete.
 * @param bool $force
 *   Whether or not to try everything possible to delete the contents, even if
 *   they're read-only. Defaults to FALSE.
 *
 * @return bool
 *   FALSE on failure, TRUE if everything was deleted.
 *
 * @see drush_delete_dir_contents()
 */
function unish_delete_dir_contents($dir, $force = FALSE) {
  $scandir = @scandir($dir);
  if (!is_array($scandir)) {
    return FALSE;
  }

  foreach ($scandir as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }
    if ($force) {
      @chmod($dir, 0777);
    }
    if (!unish_file_delete_recursive($dir . '/' . $item, $force)) {
      return FALSE;
    }
  }
  return TRUE;
}

function unish_recursive_copy($src, $dst) {
  $dir = opendir($src);
  mkdir($dst);
  while(false !== ( $file = readdir($dir)) ) {
    if (( $file != '.' ) && ( $file != '..' )) {
      if ( is_dir($src . '/' . $file) ) {
        unish_recursive_copy($src . '/' . $file,$dst . '/' . $file);
      }
      else {
        copy($src . '/' . $file,$dst . '/' . $file);
      }
    }
  }
  closedir($dir);
}
