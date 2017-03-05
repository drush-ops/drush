#!/usr/bin/env php
<?php

/**
 * This script performs setup and then calls `composer install`. It may not autoload code.
 */

require __DIR__. '/includes/filesystem.inc';
require __DIR__. '/tests/unish.inc';

unish_env();
unish_validate();
$return = unish_setup_sut();
exit($return);

function unish_validate() {
  if (basename(__DIR__) != 'drush') {
    fwrite(STDERR, 'The drush directory must end in /drush in order to run the tests. This is due to the "path" repository in tests/resources/composer.json');
    exit(1);
  }
}

/**
 * Use Composer to build a Drupal codebase, with this Drush symlinked into /vendor.
 */
function unish_setup_sut() {
  echo "Deleting ". UNISH_SANDBOX. "\n";
  drush_delete_dir(UNISH_SANDBOX, TRUE);
  $codebase = 'tests/resources/codebase';
  drush_copy_dir($codebase, UNISH_SANDBOX);
  foreach (['composer.json', 'composer.lock'] as $filename) {
    $path = UNISH_SANDBOX . "/$filename";
    if (file_exists($path)) {
      // We replace a token with the /path/to/drush for this install.
      // @todo Use https://getcomposer.org/doc/03-cli.md#modifying-repositories if it can edit composer.lock too.
      $contents = file_get_contents($path);
      $new_contents = str_replace('%PATH-TO-DRUSH%', __DIR__, $contents);
      file_put_contents($path, $new_contents);
    }
  }
  // @todo Call update instead of install if specified on the CLI. Useful need we need to update composer.lock.
  // We also need to put back the %PATH-TO-DRUSH% token by hand or automatically.
  // For option parsing, see built-in getopt() function.
  passthru('composer install --no-interaction --no-progress --no-suggest --working-dir '. UNISH_SANDBOX, $return);
  return $return;
}