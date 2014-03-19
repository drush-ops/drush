<?php
namespace Drush\Sql;

use Drupal\Core\Database\Database;

class Sql8 extends Sql7 {
  public function get_db_spec() {
    $db_spec = NULL;
    if (!$db_spec = parent::get_db_spec()) {
      if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
        $database = drush_get_option('database', 'default');
        $target = drush_get_option('target', 'default');
        if ($info = Database::getAllConnectionInfo()) {
          return $info[$database][$target];
        }
      }
    }
    return $db_spec;
  }
}
