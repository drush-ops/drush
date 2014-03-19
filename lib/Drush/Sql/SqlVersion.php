<?php

namespace Drush\Sql;

class SqlVersion {
  /*
   * Determine $db_spec by inspecting the global environment (D6/7) or the DB API (D8+).
   *
   * @return array $db_spec
   *   An array specifying a database connection.
   */
  public function get_db_spec() {}

  public function valid_credentials($db_spec) {
    $type = $db_spec['driver'];
    $type = ( $type=='oracle' ? 'oci' : $type); // fix PDO driver name, should go away in Drupal 8
    // Drupal >=7 requires PDO and Drush requires php 5.3 which ships with PDO
    // but it may be compiled with --disable-pdo.
    if (!class_exists('\PDO')) {
      drush_log(dt('PDO support is required.'), 'bootstrap');
      return FALSE;
    }
    // Check the database specific driver is available.
    if (!in_array($type, \PDO::getAvailableDrivers())) {
      drush_log(dt('!type extension for PHP PDO is not installed. Check your php.ini to see how you can enable it.', array('!type' => $type)), 'bootstrap');
      return FALSE;
    }
    return TRUE;
  }
}
