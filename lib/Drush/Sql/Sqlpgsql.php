<?php

namespace Drush\Sql;

define('PSQL_SHOW_TABLES', "SELECT tablename FROM pg_tables WHERE schemaname='public';");

class Sqlpgsql extends SqlBase {

  public $query_extra = "--no-align --field-separator=\"\t\" --pset tuples_only=on";

  public $query_file = "--file";

  private $password_file = NULL;

  private function password_file() {
    if (!isset($password_file) && isset($this->db_spec['password'])) {
      $pgpass_parts = array(
        empty($this->db_spec['host']) ? 'localhost' : $this->db_spec['host'],
        empty($this->db_spec['port']) ? '5432' : $this->db_spec['port'],
        // Database
        '*',
        $this->db_spec['username'],
        $this->db_spec['password']
      );
      // Escape colon and backslash characters in entries.
      // @see http://www.postgresql.org/docs/9.1/static/libpq-pgpass.html
      array_walk($pgpass_parts, function (&$part) {
        // The order of the replacements is important so that backslashes are
        // not replaced twice.
        $part = str_replace(array('\\', ':'), array('\\\\', '\:'), $part);
      });
      $pgpass_contents = implode(':', $pgpass_parts);
      $password_file = drush_save_data_to_temp_file($pgpass_contents);
      chmod($password_file, 0600);
    }
    return $password_file;
  }

  public function command() {
    $environment = "";
    $pw_file = $this->password_file();
    if (isset($pw_file)) {
      $environment = "PGPASSFILE={$pw_file} ";
    }
    return "{$environment}psql -q";
  }

  /*
   * @param $hide_password
   *   Not used in postgres. Use .pgpass file instead. See http://drupal.org/node/438828.
   */
  public function creds($hide_password = TRUE) {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $parameters['dbname'] = empty($this->db_spec['database']) ? 'template1' : $this->db_spec['database'];

    // Host and port are optional but have defaults.
    $parameters['host'] = empty($this->db_spec['host']) ? 'localhost' : $this->db_spec['host'];
    $parameters['port'] = empty($this->db_spec['port']) ? '5432' : $this->db_spec['port'];

    // Username is required.
    $parameters['username'] = $this->db_spec['username'];

    // Don't set the password.
    // @see http://drupal.org/node/438828

    return $this->params_to_options($parameters);
  }

  public function createdb_sql($dbname, $quoted = FALSE) {
    if ($quoted) {
      $dbname = '`' . $dbname . '`';
    }
    $sql[] = sprintf('drop database if exists %s;', $dbname);
    $sql[] = sprintf("create database %s ENCODING 'UTF8';", $dbname);
    return implode(' ', $sql);
  }

  public function db_exists() {
    $database = $this->db_spec['database'];
    // Get a new class instance that has no 'database'.
    $db_spec_no_db = $this->db_spec;
    unset($db_spec_no_db['database']);
    $sql_no_db = drush_sql_get_class($db_spec_no_db);
    $query = "SELECT 1 AS result FROM pg_database WHERE datname='$database'";
    drush_shell_exec($sql_no_db->connect() . ' -t -c %s', $query);
    $output = drush_shell_exec_output();
    return (bool)$output[0];
  }

  public function query_format($query) {
    if (strtolower($query) == 'show tables;') {
      return PSQL_SHOW_TABLES;
    }
    return $query;
  }

  public function listTables() {
    $return = $this->query(PSQL_SHOW_TABLES);
    $tables = drush_shell_exec_output();
    if (!empty($tables)) {
      return $tables;
    }
    return array();
  }

  public function dumpCmd($table_selection) {
    $parens = FALSE;
    $skip_tables = $table_selection['skip'];
    $structure_tables = $table_selection['structure'];
    $tables = $table_selection['tables'];

    $ignores = array();
    $skip_tables  = array_merge($structure_tables, $skip_tables);
    $data_only = drush_get_option('data-only');

    $create_db = drush_get_option('create-db');
    $exec = 'pg_dump ';
    // Unlike psql, pg_dump does not take a '--dbname=' before the database name.
    $extra = str_replace('--dbname=', ' ', $this->creds());
    if (isset($data_only)) {
      $extra .= ' --data-only';
    }
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $extra .= " $option";
    }
    $exec .= $extra;
    $exec .= (!isset($create_db) && !isset($data_only) ? ' --clean' : '');

    if (!empty($tables)) {
      foreach ($tables as $table) {
        $exec .= " --table=$table";
      }
    }
    else {
      foreach ($skip_tables as $table) {
        $ignores[] = "--exclude-table=$table";
      }
      $exec .= ' '. implode(' ', $ignores);
      // Run pg_dump again and append output if we need some structure only tables.
      if (!empty($structure_tables)) {
        $parens = TRUE;
        $schemaonlies = array();
        foreach ($structure_tables as $table) {
          $schemaonlies[] = "--table=$table";
        }
        $exec .= " && pg_dump --schema-only " . implode(' ', $schemaonlies) . $extra;
        $exec .= (!isset($create_db) && !isset($data_only) ? ' --clean' : '');
      }
    }
    return $parens ? "($exec)" : $exec;
  }
}
