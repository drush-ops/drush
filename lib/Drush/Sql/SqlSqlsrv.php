<?php

namespace Drush\Sql;

class SqlSqlsrv extends SqlBase {

  // The way you pass a sql file when issueing a query.
  public $queryFile = '-h -1 -i';

  public function command() {
    return 'sqlcmd';
  }

  public function creds() {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $database = empty($this->dbSpec['database']) ? 'master' : $this->dbSpec['database'];
    // Host and port are optional but have defaults.
    $host = empty($this->dbSpec['host']) ? '.\SQLEXPRESS' : $this->dbSpec['host'];
    if ($this->dbSpec['username'] == '') {
      return ' -S ' . $host . ' -d ' . $database;
    }
    else {
      return ' -S ' . $host . ' -d ' . $database . ' -U ' . $this->dbSpec['username'] . ' -P ' . $this->dbSpec['password'];
    }
  }

  public function dbExists() {
    // TODO: untested, but the gist is here.
    $database = $this->dbSpec['database'];
    // Get a new class instance that has no 'database'.
    $db_spec_no_db = $this->dbSpec;
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
  public function dumpCmd($table_selection, $options) {
    if (!$file) {
      $file = $this->dbSpec['database'] . '_' . date('Ymd_His') . '.bak';
    }
    $exec = "sqlcmd -U \"" . $this->dbSpec['username'] . "\" -P \"" . $this->dbSpec['password'] . "\" -S \"" . $this->dbSpec['host'] . "\" -Q \"BACKUP DATABASE [" . $this->dbSpec['database'] . "] TO DISK='" . $file . "'\"";
    if ($option = $options['extra-dump'] :? $this->queryExtra) {
      $exec .= " $option";
    }
    return array($exec, $file);
  }


}
