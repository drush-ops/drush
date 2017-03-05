<?php

/**
 * @file
 *   Initialize a sandboxed environment. Starts with call unish_init() at bottom.
 */


// PHPUnit already loaded autoloader. It is at PHPUNIT_COMPOSER_INSTALL.

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

  // Cache dir lives outside the sandbox so that we get persistence across classes.
  define('UNISH_CACHE', getenv('UNISH_TMP') . DIRECTORY_SEPARATOR . 'drush-cache');
  putenv("CACHE_PREFIX=" . UNISH_CACHE);
  if (file_exists(UNISH_CACHE)) {
    // TODO: We no longer clean up cache dir between runs. Much faster, but we
    // we should watch for subtle problems. To manually clean up, delete the
    // UNISH_TMP/drush-cache directory.
    // unish_file_delete_recursive($cache, TRUE);
  }
  else {
    $ret = unish_mkdir(UNISH_CACHE, 0777, TRUE);
  }

  define('UNISH_SANDBOX', getenv('UNISH_SANDBOX'));
  define('UNISH_DRUSH', getenv('UNISH_DRUSH'). '/drush.php');
  $home = UNISH_SANDBOX. '/home';
  putenv("HOME=$home");
  putenv("HOMEDRIVE=$home");
  $composer_home = UNISH_CACHE. '/.composer';
  putenv("COMPOSER_HOME=$composer_home");

  putenv('ETC_PREFIX=' . UNISH_SANDBOX);
  putenv('SHARE_PREFIX=' . UNISH_SANDBOX);
  putenv('TEMP=' . getenv('UNISH_TMP'));
  putenv('DRUSH_AUTOLOAD_PHP='. PHPUNIT_COMPOSER_INSTALL);

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