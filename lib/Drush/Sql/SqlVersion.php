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

  /*
   * Return all configured DB connections by inspecting the global environment (D6/7) or the DB API (D8+).
   *
   * @return array $all
   *   An array specifying one or more database connections.
   */
  public function getAll() {}

  /*
   * Validate that required PDO PHP extension is available.
   */
  public function hasPDO() {
    // Drupal requires PDO, and Drush requires php 5.6+ which ships with PDO
    // but PHP may be compiled with --disable-pdo.
    if (!class_exists('\PDO')) {
      drush_log(dt('PDO support is required.'), LogLevel::BOOTSTRAP);
      return FALSE;
    }
    return TRUE;
  }
}
