<?php

/**
 * @file
 *   Engine for the sql connection credential commands.
 */

/**
 * Gets the database settings for a specific database / target combination.
 *
 * @param string $database
 *   The database to get credentials for.
 * @param string $target
 *   The name of a target within the specified database.
 *
 * @return array|null
 *   The database credentials for the default database.
 */
function drush_get_db_settings($database, $target) {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
      $url =  is_array($url) ? $url[$database] : $url;
      $db_spec = drush_convert_db_from_db_url($url);
      $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
      return $db_spec;
    }
  }
  return NULL;
}

/**
 * Gets the database credentials for the default database.
 *
 * @return array
 *   The database credentials for the default database.
 */
function drush_get_db_credentials () {
  $creds = array();
  if (!empty($GLOBALS['db_url'])) {
    $url = $GLOBALS['db_url'];
    if (is_array($url)) {
      $url = $url['default'];
    }
    $parts = parse_url($url);
    $parts += array('pass' => '', 'port' => '');
    $creds['driver'] = $parts['scheme'];
    $creds['user'] = urldecode($parts['user']);
    $creds['host'] = $parts['host'];
    $creds['port'] = $parts['port'];
    $creds['pass'] = urldecode($parts['pass']);
    $creds['name'] = trim($parts['path'], '/');
  }
  return $creds;

}
