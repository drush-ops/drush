<?php


require __DIR__. '/unish.inc';
unish_env();
unish_bootstrap();

function unish_bootstrap() {
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

  define('UNISH_CACHE', UNISH_SANDBOX . DIRECTORY_SEPARATOR . 'cache');
  putenv("CACHE_PREFIX=" . UNISH_CACHE);

  define('UNISH_DRUSH', UNISH_DRUSH_DIR . '/drush.php');
  $home = UNISH_SANDBOX . '/home';
  putenv("HOME=$home");
  putenv("HOMEDRIVE=$home");
  $composer_home = UNISH_CACHE . '/.composer';
  putenv("COMPOSER_HOME=$composer_home");

  putenv('ETC_PREFIX=' . UNISH_SANDBOX);
  putenv('SHARE_PREFIX=' . UNISH_SANDBOX);
  putenv('TEMP=' . UNISH_SANDBOX. DIRECTORY_SEPARATOR . 'drush-tmp');
  putenv('DRUSH_AUTOLOAD_PHP=' . PHPUNIT_COMPOSER_INSTALL);
  define('UNISH_USERGROUP', isset($GLOBALS['UNISH_USERGROUP']) ? $GLOBALS['UNISH_USERGROUP'] : NULL);
  define('UNISH_BACKEND_OUTPUT_DELIMITER', 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END');
}

function unish_mkdir($path) {
  if (!is_dir($path)) {
    if (unish_mkdir(dirname($path))) {
      if (@mkdir($path)) {
        return TRUE;
      }
    }
    return FALSE;
  }
  return TRUE;
}