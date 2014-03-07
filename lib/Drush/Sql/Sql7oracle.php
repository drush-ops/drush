<?php

namespace Drush\Sql;

class Sql7oracle extends Sql7 {

  public function command() {
    // use rlwrap if available for readline support
    if ($handle = popen('rlwrap -v', 'r')) {
      $command = 'rlwrap sqlplus';
      pclose($handle);
    }
    else {
      $command = 'sqlplus';
    }
    return $command;
  }

  public function creds() {
    return ' ' . $this->db_spec['username'] .'/' . $this->db_spec['password'] . ($this->db_spec['host']=='USETNS' ? '@' . $this->db_spec['database'] : '@//' . $this->db_spec['host'] . ':' . ($db_spec['port'] ? $db_spec['port'] : '1521') . '/' . $this->db_spec['database']);
  }
}
