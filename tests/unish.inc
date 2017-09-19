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
  $unish_sandbox = $unish_tmp . DIRECTORY_SEPARATOR . 'drush-sandbox';
  $unish_drush_dir = dirname($unish_sandbox) . '/drush-sut/vendor/drush/drush';
  return [$unish_tmp, $unish_sandbox, $unish_drush_dir];
}

function unishIsVerbose() {
  $verbose = false;
  foreach (['-v','-vv','-vvv','--verbose', '--debug', '-d', '-dv'] as $needle) {
    if (in_array($needle, $_SERVER['argv'])) {
      $verbose = $needle;
    }
  }
  return $verbose;
}
