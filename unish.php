#!/usr/bin/env php
<?php

// Set some environment variables that are used here and again unish_bootstrap().
putenv('UNISH_TMP='. realpath(getenv('UNISH_TMP') ? getenv('UNISH_TMP') : (isset($GLOBALS['UNISH_TMP']) ? $GLOBALS['UNISH_TMP'] : sys_get_temp_dir())));
$unish_sandbox = getenv('UNISH_TMP') . DIRECTORY_SEPARATOR . 'drush-sandbox';
putenv("UNISH_SANDBOX=$unish_sandbox");
$unish_drush = $unish_sandbox. '/vendor/drush/drush';
putenv("UNISH_DRUSH=$unish_drush");

require __DIR__. '/includes/filesystem.inc';

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
  drush_delete_dir($unish_sandbox, TRUE);
  $codebase = 'tests/resources/codebase';
  drush_copy_dir($codebase, $unish_sandbox);
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