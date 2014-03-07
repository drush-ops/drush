<?php

namespace Drush\Sql;

class Sqlsqlite extends SqlBase {
  public function command() {
    return 'sqlite3';
  }

  public function creds() {
    // SQLite doesn't do user management, instead relying on the filesystem
    // for that. So the only info we really need is the path to the database
    // file, and not as a "--key=value" parameter.
    return ' '  .  $this->db_spec['database'];
  }

  public function createdb_sql() {
    return '';
  }

  /**
   * Create a new database.
   *
   * @param boolean $quoted
   *   Quote the database name. Mysql uses backticks to quote which can cause problems
   *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
   */
  public function createdb($dbname, $quoted = FALSE) {
    // Make sure sqlite can create file
    $file = $this->db_spec['database'];
    $path = dirname($file);
    drush_log("SQLITE: creating '$path' for creating '$file'", 'debug');
    drush_mkdir($path);
    if (!file_exists($path)) {
      drush_log("SQLITE: Cannot create $path", 'error');
    }
  }

  public function db_exists() {
    return file_exists($this->db_spec['database']);
  }

  public function listTables() {
    return '.tables';
  }

  public function drop($tables) {
    $sql = '';
    // SQLite only wants one table per DROP TABLE command (so we have to do
    // "DROP TABLE foo; DROP TABLE bar;" instead of "DROP TABLE foo, bar;").
    foreach ($tables as $table) {
      $sql .= "DROP TABLE $table; ";
    }
    // We can't use drush_op('db_query', $sql) because it will only perform one
    // SQL command and we're technically performing several.
    // @todo fix.
    $exec = _drush_sql_connect($db_spec);
    $exec .= " '{$sql}'";
    return drush_op_system($exec) == 0;
  }
}
