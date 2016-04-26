<?php

namespace Drush\Sql;

class Sqlsqlsrv extends SqlBase {

  // The way you pass a sql file when issueing a query.
  public $query_file = '-h -1 -i';

  public function command() {
    return 'sqlcmd';
  }

  public function creds() {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $database = empty($this->db_spec['database']) ? 'master' : $this->db_spec['database'];
    // Host and port are optional but have defaults.
    $host = empty($this->db_spec['host']) ? '.\SQLEXPRESS' : $this->db_spec['host'];
    if ($this->db_spec['username'] == '') {
      return ' -S ' . $host . ' -d ' . $database;
    }
    else {
      return ' -S ' . $host . ' -d ' . $database . ' -U ' . $this->db_spec['username'] . ' -P ' . $this->db_spec['password'];
    }
  }

  public function db_exists() {
    // TODO: untested, but the gist is here.
    $database = $this->db_spec['database'];
    // Get a new class instance that has no 'database'.
    $db_spec_no_db = $this->db_spec;
    unset($db_spec_no_db['database']);
    $sql_no_db = drush_sql_get_class($db_spec_no_db);
    $query = "if db_id('$database') IS NOT NULL print 1";
    drush_shell_exec($sql_no_db->connect() . ' -Q %s', $query);
    $output = drush_shell_exec_output();
    return $output[0] == 1;
  }

  public function listTables() {
    $return = $this->query('SELECT TABLE_NAME FROM information_schema.tables');
    $tables = drush_shell_exec_output();
    if (!empty($tables)) {
      // Shift off the header of the column of data returned.
      array_shift($tables);
      return $tables;
    }
  }

  // @todo $file is no longer provided. We are supposed to return bash that can be piped to gzip.
  // Probably sqlsrv needs to override dump() entirely.
  public function dumpCmd($table_selection) {
    if (!$file) {
      $file = $this->db_spec['database'] . '_' . date('Ymd_His') . '.bak';
    }
    $exec = "sqlcmd -U \"" . $this->db_spec['username'] . "\" -P \"" . $this->db_spec['password'] . "\" -S \"" . $this->db_spec['host'] . "\" -Q \"BACKUP DATABASE [" . $this->db_spec['database'] . "] TO DISK='" . $file . "'\"";
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $exec .= " $option";
    }
    return array($exec, $file);
  }


}
