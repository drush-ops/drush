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
}

/**
 * Use Composer to build a Drupal codebase, with this Drush symlinked into /vendor.
 * @param string $unish_sandbox Path to sandbox.
 * @return integer
 *   Exit code.
 */
function unish_setup_sut($unish_sandbox) {
  $working_dir = dirname($unish_sandbox) . DIRECTORY_SEPARATOR . 'build-drush-sut';
  $target_dir = dirname($working_dir) . DIRECTORY_SEPARATOR . 'drush-sut';
  drush_delete_dir($working_dir, TRUE);
  $codebase = 'tests/resources/codebase';
  drush_copy_dir($codebase, $working_dir);
  $composer_json = getenv('COMPOSER') ?: 'composer.json';
  foreach ([$composer_json] as $filename) {
    $path = $working_dir . "/$filename";
    if (file_exists($path)) {
      $contents = file_get_contents($path);
      $new_contents = replace_token($contents);
      file_put_contents($path, $new_contents);
    }
  }

  $alias_contents = <<<EOT
dev:
  root: $target_dir/web
  uri: dev
stage:
  root: $target_dir/web
  uri: stage
EOT;
  mkdir("$working_dir/drush");
  mkdir("$working_dir/drush/site-aliases");
  file_put_contents("$working_dir/drush/site-aliases/sut.alias.yml", $alias_contents);

  $verbose = unishIsVerbose();
  $cmd = "composer $verbose install --prefer-dist --no-progress --no-suggest --working-dir " . escapeshellarg($working_dir);
  fwrite(STDERR, 'Executing: ' . $cmd . "\n");
  exec($cmd, $output, $return);

  // If requirements force it to, Composer downloads a second Drush, instead of symlink.
  $drush_sut = $working_dir . '/vendor/drush/drush';
  if (!is_link($drush_sut)) {
    fwrite(STDERR, "Drush not symlinked in the System-Under-Test.\n");
    $return = 1;
  }

  // Move the sut into place
  drush_delete_dir($target_dir, TRUE);
  rename($working_dir, $target_dir);

  // If there is no 'vendor' directory in the Drush home dir, then make
  // a symlink from the SUT
  if (!is_dir(__DIR__ . '/vendor')) {
    symlink("$target_dir/vendor", __DIR__ . '/vendor');
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
