<?php

/**
 * @file
 *   Engine for the sql connection credential commands.
 */

use Drupal\Core\Database\Database;


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
  if (isset($GLOBALS['databases'])) {
    return _drush_get_db_settings_globals($database, $target);
  }
  return _drush_get_db_settings($database, $target);
}

/**
 * Gets the database credentials for the default database.
 *
 * @return array
 *   the database credentials for the default database.
 */
function drush_get_db_credentials() {
  if (isset($GLOBALS['databases'])) {
    return _drush_get_db_credentials_globals();
  }
  return _drush_get_db_credentials();
}

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
function _drush_get_db_settings($database, $target) {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    // We don't use DB API here `sql-sync` would have to messily addConnection.
    if ($info = Database::getAllConnectionInfo()) {
      return $info[$database][$target];
    }
  }
  return NULL;
}

/**
 * Gets the database credentials for the default database.
 *
 * @return array
 *   the database credentials for the default database.
 */
function _drush_get_db_credentials() {

  if ($info = Database::getConnectionInfo('default')) {
    $conn = $info['default'];
    // Fill in defaults to prevent notices.
    $conn += array(
      'username' => NULL,
      'host' => NULL,
      'port' => NULL,
      'password' => NULL,
      'database' => NULL,
      'unix_socket' => NULL,
    );
    $creds['driver'] = $conn['driver'];
    $creds['user'] = $conn['username'];
    $creds['unix_socket'] = $conn['unix_socket'];
    $creds['host'] = $conn['host'];
    $creds['port'] = $conn['port'];
    $creds['name'] = $conn['database'];
    $creds['pass'] = $conn['password'];
    if(isset($conn['pdo'])) {
      $creds['pdo'] = $conn['pdo'];
    } else { $creds['pdo'] = null; }
  }
  return $creds;
}

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
function _drush_get_db_settings_globals($database, $target) {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    // We don't use DB API here `sql-sync` would have to messily addConnection.
    if (isset($GLOBALS['databases']) && array_key_exists($database, $GLOBALS['databases']) && array_key_exists($target, $GLOBALS['databases'][$database])) {
      return $GLOBALS['databases'][$database][$target];
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
function _drush_get_db_credentials_globals() {
  $creds = array();
  if (!empty($GLOBALS['databases']['default']['default'])) {
    $conn = $GLOBALS['databases']['default']['default'];
    // Fill in defaults to prevent notices.
    $conn += array(
      'username' => NULL,
      'host' => NULL,
      'port' => NULL,
      'password' => NULL,
      'database' => NULL,
      'unix_socket' => NULL,
    );
    $creds['driver'] = $conn['driver'];
    $creds['user'] = $conn['username'];
    $creds['unix_socket'] = $conn['unix_socket'];
    $creds['host'] = $conn['host'];
    $creds['port'] = $conn['port'];
    $creds['name'] = $conn['database'];
    $creds['pass'] = $conn['password'];
    if(isset($conn['pdo'])) {
      $creds['pdo'] = $conn['pdo'];
    } else { $creds['pdo'] = null; }
  }
  return $creds;
}
