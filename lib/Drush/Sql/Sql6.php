<?php

namespace Drush\Sql;

class Sql6 extends SqlVersion {
  public function get_db_spec() {
    $db_spec = NULL;
    if (!$db_spec = parent::get_db_spec()) {
      if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
        $database = drush_get_option('database', 'default');
        $url =  is_array($url) ? $url[$database] : $url;
        $db_spec = drush_convert_db_from_db_url($url);
        $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
      }
    }
    return $db_spec;
  }
}
