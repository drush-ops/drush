<?php

namespace Drush\Sql;

class Sql7 extends SqlVersion {
  public function get_db_spec() {
    $db_spec = NULL;
    drush_sql_bootstrap_database_configuration();
    $database = drush_get_option('database', 'default');
    $target = drush_get_option('target', 'default');
    // We don't use DB API here `sql-sync` would have to messily addConnection.
    if (!isset($GLOBALS['databases']) || !array_key_exists($database, $GLOBALS['databases']) || !array_key_exists($target, $GLOBALS['databases'][$database])) {
      // Do nothing
    }
    else {
      $db_spec = $GLOBALS['databases'][$database][$target];;
    }
    return $db_spec;
  }

  public function getAll() {
    if (isset($GLOBALS['databases'])) {
      return $GLOBALS['databases'];
    }
  }
}
