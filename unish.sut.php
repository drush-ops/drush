#!/usr/bin/env php
<?php

/**
 * This script performs setup and then calls `composer install`. It may not autoload code.
 */

require __DIR__ . '/includes/filesystem.inc';
require __DIR__ . '/tests/unish.inc';

list($unish_tmp, $unish_sandbox, $unish_drush_dir) = unishGetPaths();
unish_validate();
$return = unish_setup_sut($unish_sandbox);
exit($return);

function unish_validate() {
  if (basename(__DIR__) != 'drush') {
    fwrite(STDERR, 'The drush directory must end in /drush in order to run the tests. This is due to the "path" repository in tests/resources/composer.json');
    exit(1);
  }

  /**
   * Assure that the composer.lock and the composer.json in the /tests directory
   * are in sync.
   *
   * Based on http://stackoverflow.com/a/28730898/265501.
   *
   * @todo. Not sure this is feasible since multiple authors will update the lockfile from different path/to/drush.
   */
//  $codebase = __DIR__ . '/tests/resources/codebase';
//  // If composer.lock is missing then no need for this check.
//  $lockfile = $codebase . '/composer.lock';
//  if (file_exists($lockfile)) {
//    $lock = json_decode(file_get_contents($lockfile))->{'content-hash'};
//    $json = md5(replace_token(file_get_contents($codebase . '/composer.json')));
//
//    if ($lock !== $json) {
//      fwrite(STDERR, "$lockfile file out of sync with its composer.json.\n");
//      exit(1);
//    }
//
//    fwrite(STDERR, "$lockfile file up to date with its composer.json.\n");
//    exit(0);
//  }
}

/**
 * Use Composer to build a Drupal codebase, with this Drush symlinked into /vendor.
 * @param $unish_sandbox Path to sandbox.
 * @return integer
 *   Exit code.
 */
function unish_setup_sut($unish_sandbox) {
  $working_dir = dirname($unish_sandbox) . DIRECTORY_SEPARATOR . 'drush-sut';
  drush_delete_dir($working_dir, TRUE);
  $codebase = 'tests/resources/codebase';
  drush_copy_dir($codebase, $working_dir);
  foreach (['composer.json', 'composer.lock'] as $filename) {
    $path = $working_dir . "/$filename";
    if (file_exists($path)) {
      $contents = file_get_contents($path);
      $new_contents = replace_token($contents);
      file_put_contents($path, $new_contents);
    }
  }
  // @todo Call update instead of install if specified on the CLI. Useful need we need to update composer.lock.
  // We also need to put back the %PATH-TO-DRUSH% token by hand or automatically.
  // For option parsing, see built-in getopt() function.
  $cmd = 'composer install --no-interaction --no-progress --no-suggest --working-dir ' . escapeshellarg($working_dir);
  fwrite(STDERR, 'Executing: ' . $cmd . "\n");
  exec($cmd, $output, $return);

  // @todo Not 100% sure this is needed, but I've seen Composer download a second Drush, instead of symlink.
  $drush_sut = $working_dir . '/vendor/drush/drush';
  if (!is_link($drush_sut)) {
    fwrite(STDERR, "Drush not symlinked in the System-Under-Test.\n");
    $return = 1;
  }
  return $return;
}

/**
 * Replace a token with the /path/to/drush for this install.
 *
 * @param $contents
 */
function replace_token($contents) {
  // @todo Use https://getcomposer.org/doc/03-cli.md#modifying-repositories if it can edit composer.lock too.
  return str_replace('%PATH-TO-DRUSH%', __DIR__, $contents);
}