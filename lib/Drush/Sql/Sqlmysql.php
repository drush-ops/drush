<?php

namespace Drush\Sql;

class Sqlmysql extends SqlBase {

  public function command() {
    $command = 'mysql';
    if (drush_get_option('A', FALSE)) {
      $command .= ' -A';
    }
    return $command;
  }

  public function creds() {
    // Some drush commands (e.g. site-install) want to connect to the
    // server, but not the database.  Connect to the built-in database.
    $parameters['database'] = empty($this->db_spec['database']) ? 'information_schema' : $this->db_spec['database'];

    // Default to unix socket if configured.
    if (!empty($this->db_spec['unix_socket'])) {
      $parameters['socket'] = $this->db_spec['unix_socket'];
    }
    // EMPTY host is not the same as NO host, and is valid (see unix_socket).
    elseif (isset($this->db_spec['host'])) {
      $parameters['host'] = $this->db_spec['host'];
    }

    if (!empty($this->db_spec['port'])) {
      $parameters['port'] = $this->db_spec['port'];
    }

    // User is required. Drupal calls it 'username'. MySQL calls it 'user'.
    $parameters['user'] = $this->db_spec['username'];

    // EMPTY password is not the same as NO password, and is valid.
    if (isset($this->db_spec['password'])) {
      $parameters['password'] = $this->db_spec['password'];
    }

    return $this->params_to_options($parameters);
  }

  public function createdb_sql($dbname, $quoted = FALSE) {
    if ($quoted) {
      $dbname = '`' . $dbname . '`';
    }
    $sql[] = sprintf('DROP DATABASE IF EXISTS %s;', $dbname);
    $sql[] = sprintf('CREATE DATABASE %s /*!40100 DEFAULT CHARACTER SET utf8 */;', $dbname);
    $sql[] = sprintf('GRANT ALL PRIVILEGES ON %s.* TO \'%s\'@\'%s\'', $dbname, $this->db_spec['username'], $this->db_spec['host']);
    $sql[] = sprintf("IDENTIFIED BY '%s';", $this->db_spec['password']);
    $sql[] = 'FLUSH PRIVILEGES;';
    return implode(' ', $sql);
  }

  public function db_exists() {
    return $this->query("SELECT 1;");
  }

  public function listTables() {
    $return = $this->query('SHOW TABLES', NULL, TRUE);
    $tables = drush_shell_exec_output();
    if (!empty($tables)) {
      // Shift off the header of the column of data returned.
      array_shift($tables);
      return $tables;
    }
  }
}
