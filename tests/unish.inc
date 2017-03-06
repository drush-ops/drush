<?php

/**
 * Discover key directories for the System-Under-Test. Used by Unish launcher
 * scripts and by \Unish\UnishTestCase::__construct.
 *
 * @return array
 *   A list of paths.
 */
function unishGetPaths() {
  $unish_tmp = realpath(getenv('UNISH_TMP') ?: sys_get_temp_dir());
  $unish_sandbox = getenv('UNISH_TMP') . DIRECTORY_SEPARATOR . 'drush-sandbox';
  $unish_drush_dir = dirname($unish_sandbox) . '/drush-sut/vendor/drush/drush';
  return [$unish_tmp, $unish_sandbox, $unish_drush_dir];
}