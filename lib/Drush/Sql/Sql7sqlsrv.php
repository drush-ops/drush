<?php

namespace Drush\Sql;

class Sql7sqlsrv extends Sql7 {

  public function command() {
    return 'sqlcmd';
  }

  public function creds() {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $database = empty($this->db_spec['database']) ? 'master' : $this->db_spec['database'];
    // Host and port are optional but have defaults.
    $host = empty($this->db_spec['host']) ? '.\SQLEXPRESS' : $this->db_spec['host'];
    return ' -S ' . $host . ' -d ' . $database . ' -U ' . $this->db_spec['username'] . ' -P ' . $this->db_spec['password'];
  }

}
