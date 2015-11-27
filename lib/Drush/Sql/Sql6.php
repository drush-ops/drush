<?php

namespace Drush\Sql;

use Drush\Log\LogLevel;

class Sql6 extends SqlVersion {
  public function get_db_spec() {
    $db_spec = NULL;
    if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
      $database = drush_get_option('database', 'default');
      $url =  is_array($url) ? $url[$database] : $url;
      $db_spec = drush_convert_db_from_db_url($url);
      $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
      // For uniformity with code designed for Drupal 7/8 db_specs, copy the 'db_prefix' to 'prefix'.
      $db_spec['prefix'] = $db_spec['db_prefix'];
    }
    return $db_spec;
  }

  public function getAll() {
    if (isset($GLOBALS['db_url'])) {
      return drush_sitealias_convert_db_from_db_url($GLOBALS['db_url']);
    }
  }

  public function valid_credentials($db_spec) {
    $type = $db_spec['driver'];
    // Check for Drupal support of configured db type.
    if (file_exists('./includes/install.'. $type .'.inc')) {
      require_once './includes/install.'. $type .'.inc';
      $function = $type .'_is_available';
      if (!$function()) {
        drush_log(dt('!type extension for PHP is not installed. Check your php.ini to see how you can enable it.', array('!type' => $type)), LogLevel::BOOTSTRAP);
        return FALSE;
      }
    }
    else {
      drush_log(dt('!type database type is unsupported.', array('!type' => $type)), LogLevel::BOOTSTRAP);
      return FALSE;
    }
    return TRUE;
  }

}
