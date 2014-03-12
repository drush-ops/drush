<?php

namespace Drush\Sql;

class SqlVersion {
  public function determine_db_spec() {
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
  }
}
