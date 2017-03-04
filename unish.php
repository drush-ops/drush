#!/usr/bin/env php
<?php

// Set some environment variables that are used here and again unish_bootstrap().
putenv('UNISH_TMP='. realpath(getenv('UNISH_TMP') ? getenv('UNISH_TMP') : (isset($GLOBALS['UNISH_TMP']) ? $GLOBALS['UNISH_TMP'] : sys_get_temp_dir())));
$unish_sandbox = getenv('UNISH_TMP') . DIRECTORY_SEPARATOR . 'drush-sandbox';
putenv("UNISH_SANDBOX=$unish_sandbox");
$unish_drush = $unish_sandbox. '/vendor/drush/drush';
putenv("UNISH_DRUSH=$unish_drush");

unish_validate();
unish_setup_sut($unish_sandbox);
unish_start_phpunit($unish_sandbox, $unish_drush);

function unish_validate() {
  if (basename(__DIR__) != 'drush') {
    fwrite(STDERR, 'The drush directory must end in /drush in order to run the tests. This is due to the "path" repository in tests/resources/composer.json');
    exit(1);
  }
}

/**
 * Use Composer to build a Drupal codebase, with this Drush symlinked into /vendor.
 *
 * @param $unish_sandbox Path to the sandbox.
 */
function unish_setup_sut($unish_sandbox) {
  unish_file_delete_recursive($unish_sandbox, TRUE);
  $codebase = 'tests/resources/codebase';
  unish_recursive_copy($codebase, $unish_sandbox);
  foreach (['composer.json', 'composer.lock'] as $filename) {
    $path = $unish_sandbox. "/$filename";
    if (file_exists($path)) {
      // We replace a token in these 2 files with the /path/to/drush for this install.
      // @todo Use https://getcomposer.org/doc/03-cli.md#modifying-repositories if it can edit composer.lock too.
      $contents = file_get_contents($path);
      $new_contents = str_replace('%PATH-TO-DRUSH%', __DIR__, $contents);
      file_put_contents($path, $new_contents);
    }
  }
  chdir($unish_sandbox);
  passthru('composer install --no-interaction --no-progress --no-suggest');
}

/**
 * @param $unish_sandbox Path to the sandbox.
 * @param $unish_drush Path to Drush in the vendor dir.
 */
function unish_start_phpunit($unish_sandbox, $unish_drush) {
  // Get the arguments for the command.
  $arguments = $GLOBALS['argv'];
  // Shift off argv[0] which contains the name of this script.
  array_shift($arguments);
  // Add the directory containing the phpunit bootstrap file.
  array_unshift($arguments, $unish_drush. '/tests');
  $cmd = $unish_sandbox. '/vendor/bin/phpunit --configuration '. implode(' ', $arguments);
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
