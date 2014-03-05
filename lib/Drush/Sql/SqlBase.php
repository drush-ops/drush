<?php

namespace Drush\Sql;

abstract class SqlBase {

  // An Drupal style array containing specs for connecting to database.
  public $db_spec;

  /**
   * This constructor defaults to honoring CLI options if
   * not explicitly passed.
   */
  public function __construct() {
    $database = drush_get_option('database', 'default');
    $target = drush_get_option('target', 'default');

    if ($url = drush_get_option('db-url')) {
      $url =  is_array($url) ? $url[$database] : $url;
      $db_spec = drush_convert_db_from_db_url($url);
      $db_spec['db_prefix'] = drush_get_option('db-prefix');
    }
    elseif (($databases = drush_get_option('databases')) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
      $db_spec = $databases[$database][$target];
    }
    elseif (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
      switch (drush_drupal_major_version()) {
        case 6:
          if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
            $url =  is_array($url) ? $url[$database] : $url;
            $db_spec = drush_convert_db_from_db_url($url);
            $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
          }
          break;
        default:
          // We don't use DB API here `sql-sync` would have to messily addConnection.
          if (!isset($GLOBALS['databases']) || !array_key_exists($database, $GLOBALS['databases']) || !array_key_exists($target, $GLOBALS['databases'][$database])) {
            // Do nothing
          }
          else {
            $db_spec = $GLOBALS['databases'][$database][$target];;
          }
      }
    }

    if (empty($db_spec)) {
      throw new SqlException(dt('Could not find a matching database connection.'));
    }
    else {
      $this->db_spec = $db_spec;
    }
  }

  /**
   * The unix command used to connect to the database.
   * @return string
   */
  public function command() {}

  public function connect($module) {
    return array();
  }

  public function query() {

  }

  public function role_create($role_machine_name, $role_human_readable_name = '') {
  }

  public function delete() {
  }

  /**
   * Build a fragment containing credentials and other connection parameters.
   * @return string
   */
  public function creds() {}

  public function scheme() {
    return $this->db_spec['driver'];
  }

  public function remove($perm) {
    $perms = $this->getPerms();
    if (in_array($perm, $perms)) {
      $this->revoke_permissions(array($perm));
      return TRUE;
    }
    else {
      drush_log(dt('"!role" does not have the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )), 'ok');
      return FALSE;
    }
  }

  /*
   * Helper method to turn associative array into options with values.
   */
  public function params_to_options($parameters) {
    // Turn each parameter into a valid parameter string.
    $parameter_strings = array();
    foreach ($parameters as $key => $value) {
      // Only escape the values, not the keys or the rest of the string.
      $value = drush_escapeshellarg($value);
      $parameter_strings[] = "--$key=$value";
    }

    // Join the parameters and return.
    return ' ' . implode(' ', $parameter_strings);
  }
}
