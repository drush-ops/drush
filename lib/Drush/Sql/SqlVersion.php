<?php

namespace Drush\Sql;

class SqlVersion {
  public function get_db_spec() {
    $db_spec = NULL;
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
    return $db_spec;
  }

  public function valid_credentials() {
    $db_spec = $this->get_db_spec();
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
