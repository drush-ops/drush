<?php

namespace Drush\Sql;

use Drush\Log\LogLevel;

class Sqloracle extends SqlBase {

  // The way you pass a sql file when issueing a query.
  public $query_file = '@';

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
    return ' ' . $this->db_spec['username'] . '/' . $this->db_spec['password'] . ($this->db_spec['host'] == 'USETNS' ? '@' . $this->db_spec['database'] : '@//' . $this->db_spec['host'] . ':' . ($db_spec['port'] ? $db_spec['port'] : '1521') . '/' . $this->db_spec['database']);
  }

  public function createdb_sql($dbname) {
    return drush_log("Unable to generate CREATE DATABASE sql for $dbname", LogLevel::ERROR);
  }

  // @todo $suffix = '.sql';
  public function query_format($query) {
    // remove trailing semicolon from query if we have it
    $query = preg_replace('/\;$/', '', $query);

    // some sqlplus settings
    $settings[] = "set TRIM ON";
    $settings[] = "set FEEDBACK OFF";
    $settings[] = "set UNDERLINE OFF";
    $settings[] = "set PAGES 0";
    $settings[] = "set PAGESIZE 50000";

    // are we doing a describe ?
    if (!preg_match('/^ *desc/i', $query)) {
      $settings[] = "set LINESIZE 32767";
    }

    // are we doing a show tables ?
    if (preg_match('/^ *show tables/i', $query)) {
      $settings[] = "set HEADING OFF";
      $query = "select object_name from user_objects where object_type='TABLE' order by object_name asc";
    }

    // create settings string
    $sqlp_settings = implode("\n", $settings) . "\n";

    // important for sqlplus to exit correctly
    return "${sqlp_settings}${query};\nexit;\n";
  }

  public function listTables() {
    $return = $this->query("SELECT TABLE_NAME FROM USER_TABLES WHERE TABLE_NAME NOT IN ('BLOBS','LONG_IDENTIFIERS')");
    $tables = drush_shell_exec_output();
    if (!empty($tables)) {
      // Shift off the header of the column of data returned.
      array_shift($tables);
      return $tables;
    }
  }

  // @todo $file is no longer provided. We are supposed to return bash that can be piped to gzip.
  // Probably Oracle needs to override dump() entirely - http://stackoverflow.com/questions/2236615/oracle-can-imp-exp-go-to-stdin-stdout.
  public function dumpCmd($table_selection) {
    $create_db = drush_get_option('create-db');
    $exec = 'exp ' . $this->creds();
    // Change variable '$file' by reference in order to get drush_log() to report.
    if (!$file) {
      $file = $this->db_spec['username'] . '.dmp';
    }
    $exec .= ' file=' . $file;

    if (!empty($tables)) {
      $exec .= ' tables="(' . implode(',', $tables) . ')"';
    }
    $exec .= ' owner=' . $this->db_spec['username'];
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $exec .= " $option";
    }
    return array($exec, $file);
  }
}
