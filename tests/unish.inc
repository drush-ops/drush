<?php

function unish_env() {
  // Set some environment variables that are used by unish.phpunit.php and unish.sut.php.
  $unish_tmp = realpath(getenv('UNISH_TMP') ?: sys_get_temp_dir());
  define('UNISH_TMP', $unish_tmp);
  $unish_sandbox = getenv('UNISH_TMP') . DIRECTORY_SEPARATOR . 'drush-sandbox';
  define('UNISH_SANDBOX', $unish_sandbox);
  $unish_drush_dir = dirname($unish_sandbox). '/drush-sut/vendor/drush/drush';
  define('UNISH_DRUSH_DIR', $unish_drush_dir);
}