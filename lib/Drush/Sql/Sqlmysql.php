<?php

namespace Drush\Sql;

use PDO;
use Symfony\Component\Process\Process;

class Sqlmysql extends SqlBase {

  public function command() {
    return 'mysql';
  }

  public function creds($hide_password = TRUE) {
    if ($hide_password) {
      // EMPTY password is not the same as NO password, and is valid.
      $contents = <<<EOT
#This file was written by Drush's Sqlmysql.php.
[client]
user="{$this->db_spec['username']}"
password="{$this->db_spec['password']}"
EOT;

      $file = drush_save_data_to_temp_file($contents);
      $parameters['defaults-extra-file'] = $file;
    }
    else {
      // User is required. Drupal calls it 'username'. MySQL calls it 'user'.
      $parameters['user'] = $this->db_spec['username'];
      // EMPTY password is not the same as NO password, and is valid.
      if (isset($this->db_spec['password'])) {
        $parameters['password'] = $this->db_spec['password'];
      }
    }

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

    if (!empty($this->db_spec['pdo']['unix_socket'])) {
      $parameters['socket'] = $this->db_spec['pdo']['unix_socket'];
    }

    if (!empty($this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CA])) {
      $parameters['ssl-ca'] = $this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CA];
    }

    if (!empty($this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CAPATH])) {
      $parameters['ssl-capath'] = $this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CAPATH];
    }

    if (!empty($this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CERT])) {
      $parameters['ssl-cert'] = $this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CERT];
    }

    if (!empty($this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CIPHER])) {
      $parameters['ssl-cipher'] = $this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_CIPHER];
    }

    if (!empty($this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_KEY])) {
      $parameters['ssl-key'] = $this->db_spec['pdo'][PDO::MYSQL_ATTR_SSL_KEY];
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
    $db_superuser = drush_get_option('db-su');
    if (isset($db_superuser)) {
      // - For a localhost database, create a localhost user.  This is important for security.
      //   localhost is special and only allows local Unix socket file connections.
      // - If the database is on a remote server, create a wilcard user with %.
      //   We can't easily know what IP adderss or hostname would represent our server.
      $domain = ($this->db_spec['host'] == 'localhost') ? 'localhost' : '%';
      $user = sprintf("'%s'@'%s'", $this->db_spec['username'], $domain);
      $sql[] = sprintf("DROP USER IF EXISTS %s;", $user);
      $sql[] = sprintf("CREATE USER %s IDENTIFIED WITH mysql_native_password;", $user);
      $sql[] = sprintf("SET PASSWORD FOR %s = PASSWORD('%s');", $user, $this->db_spec['password']);
      $sql[] = sprintf('GRANT ALL PRIVILEGES ON %s.* TO %s;', $dbname, $user);
      $sql[] = 'FLUSH PRIVILEGES;';
    }
    return implode(' ', $sql);
  }

  public function db_exists() {
    $current = drush_get_context('DRUSH_SIMULATE');
    drush_set_context('DRUSH_SIMULATE', FALSE);
    // Suppress output. We only care about return value.
    $return = $this->query("SELECT 1;", NULL, drush_bit_bucket());
    drush_set_context('DRUSH_SIMULATE', $current);
    return $return;
  }

  /**
   * List the tables in the database.
   *
   * The client is run through Symfony Process rather than drush_shell_exec()
   * so that stdout is read separately from stderr. drush_shell_exec() merges
   * the two (2>&1), which lets client warnings (e.g. MariaDB's "mysql:
   * Deprecated program name" notice) end up in the list as bogus table names.
   *
   * @return string[]
   *   The table names. An empty array if the query fails, in which case a
   *   Drush error is also set so that the command exits with a failure status.
   */
  public function listTables() {
    // The --extra option is deliberately not passed along. It carries options
    // for the command being run (e.g. mysqldump flags for sql-dump), which the
    // mysql client may reject.
    $command = implode(' ', array_filter([
      $this->command(),
      $this->creds(),
      $this->silent(),
    ]));

    $process = Process::fromShellCommandline($command);
    $process->setTimeout(NULL);
    $process->setInput('SHOW TABLES;');
    $process->run();

    if (!$process->isSuccessful()) {
      drush_set_error('DRUSH_SQL_LIST_TABLES_FAILED', dt('Failed to list tables: !error', ['!error' => trim($process->getErrorOutput())]));
      return [];
    }

    return $this->parseTableList($process->getOutput());
  }

  /**
   * Parse the output of "SHOW TABLES" into a list of table names.
   *
   * Lines are split on "\n" or "\r\n" only. PCRE's \R must not be used here: in
   * non-UTF mode it also matches the single byte 0x85, which is part of the
   * UTF-8 encoding of characters such as "Å" (C3 85) and would split table
   * names containing them.
   *
   * @param string $output
   *   The standard output of the client.
   *
   * @return string[]
   *   The table names.
   */
  protected function parseTableList($output) {
    return preg_split('/\r?\n/', $output, -1, PREG_SPLIT_NO_EMPTY);
  }

  public function listTablesQuoted() {
    $tables = $this->listTables();
    foreach ($tables as &$table) {
      $table = "`$table`";
    }
    return $tables;
  }

  /**
   * Check whether the MySQL server has GTID mode enabled.
   * When it does, mysqldump includes SET @@GLOBAL.GTID_PURGED which
   * causes errors on re-import if the server already has GTID history.
   */
  protected function isGtidEnabled() {
    $result = $this->query("SELECT @@GLOBAL.gtid_mode AS gtid_mode");
    if ($result) {
      $output = drush_shell_exec_output();
      foreach ($output as $line) {
        if (preg_match('/^(ON|ON_PERMISSIVE|OFF_PERMISSIVE)/i', trim($line))) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public function dumpCmd($table_selection) {
    $parens = FALSE;
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

    $exec = 'mysqldump ';
    // mysqldump wants 'databasename' instead of 'database=databasename' for no good reason.
    $only_db_name = str_replace('--database=', ' ', $this->creds());
    $exec .= $only_db_name;

    // We had --skip-add-locks here for a while to help people with insufficient permissions,
    // but removed it because it slows down the import a lot.  See http://drupal.org/node/1283978
    $extra = ' --no-autocommit --single-transaction --opt -Q';
    // Suppress GTID_PURGED statements in the dump; they break re-import on
    // servers with GTID mode enabled (MySQL 5.6+).
    if ($this->isGtidEnabled()) {
      $extra .= ' --set-gtid-purged=OFF';
    }
    if (isset($data_only)) {
      $extra .= ' --no-create-info';
    }
    if (isset($ordered_dump)) {
      $extra .= ' --skip-extended-insert --order-by-primary';
    }
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $extra .= " $option";
    }
    $exec .= $extra;

    if (!empty($tables)) {
      $exec .= ' ' . implode(' ', $tables);
    }
    else {
      // Append the ignore-table options.
      foreach ($skip_tables as $table) {
        $ignores[] = '--ignore-table=' . $this->db_spec['database'] . '.' . $table;
        $parens = TRUE;
      }
      $exec .= ' '. implode(' ', $ignores);

      // Run mysqldump again and append output if we need some structure only tables.
      if (!empty($structure_tables)) {
        $exec .= " && mysqldump " . $only_db_name . " --no-data $extra " . implode(' ', $structure_tables);
        $parens = TRUE;
      }
    }
    return $parens ? "($exec)" : $exec;
  }
}
