<?php

namespace Drush\Sql;

use Drush\Log\LogLevel;

class SqlVersion {
  /*
   * Determine $db_spec by inspecting the global environment (D6/7) or the DB API (D8+).
   *
   * @return array $db_spec
   *   An array specifying a database connection.
   */
  public function get_db_spec() {}
}
