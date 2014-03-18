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

  public function silent() {
    return '--silent';
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
    return $this->query("SELECT 1;", NULL, TRUE);
  }

  public function listTables() {
    $return = $this->query('SHOW TABLES;', NULL, TRUE);
    $tables = drush_shell_exec_output();
    return $tables;
  }

  public function dumpCmd($table_selection, $file) {
    $skip_tables = $table_selection['skip'];
    $structure_tables = $table_selection['structure'];
    $tables = $table_selection['tables'];

    $ignores = array();
    $skip_tables  = array_merge($structure_tables, $skip_tables);
    $data_only = drush_get_option('data-only');
    // The ordered-dump option is only supported by MySQL for now.
    // @todo add documention once a hook for drush_get_option_help() is available.
    // @see drush_get_option_help() in drush.inc
    $ordered_dump = drush_get_option('ordered-dump');

    $exec = 'mysqldump';
    if ($file) {
      $exec .= ' --result-file '. $file;
    }
    // mysqldump wants 'databasename' instead of 'database=databasename' for no good reason.
    // We had --skip-add-locks here for a while to help people with insufficient permissions,
    // but removed it because it slows down the import a lot.  See http://drupal.org/node/1283978
    $extra = ' --no-autocommit --single-transaction --opt -Q' . str_replace('--database=', ' ', $this->creds());
    if (isset($data_only)) {
      $extra .= ' --no-create-info';
    }
    if (isset($ordered_dump)) {
      $extra .= ' --skip-extended-insert --order-by-primary';
    }
    $exec .= $extra;

    if (!empty($tables)) {
      $exec .= ' ' . implode(' ', $tables);
    }
    else {
      // Append the ignore-table options.
      foreach ($skip_tables as $table) {
        $ignores[] = '--ignore-table=' . $this->db_spec['database'] . '.' . $table;
      }
      $exec .= ' '. implode(' ', $ignores);

      // Run mysqldump again and append output if we need some structure only tables.
      if (!empty($structure_tables)) {
        $exec .= " && mysqldump --no-data $extra " . implode(' ', $structure_tables);
        if ($file) {
          $exec .= " >> $file";
        }
      }
    }
    return array($exec, $file);
  }
}
