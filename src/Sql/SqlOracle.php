<?php

namespace Drush\Sql;

use Drush\Log\LogLevel;

class SqlOracle extends SqlBase
{

    // The way you pass a sql file when issueing a query.
    public $queryFile = '@';

    public function command()
    {
        // use rlwrap if available for readline support
        if ($handle = popen('rlwrap -v', 'r')) {
            $command = 'rlwrap sqlplus';
            pclose($handle);
        } else {
            $command = 'sqlplus';
        }
        return $command;
    }

    public function creds($hide_password = true)
    {
        return ' ' . $this->dbSpec['username'] . '/' . $this->dbSpec['password'] . ($this->dbSpec['host'] == 'USETNS' ? '@' . $this->dbSpec['database'] : '@//' . $this->dbSpec['host'] . ':' . ($db_spec['port'] ? $db_spec['port'] : '1521') . '/' . $this->dbSpec['database']);
    }

    public function createdbSql($dbname)
    {
        return drush_log("Unable to generate CREATE DATABASE sql for $dbname", LogLevel::ERROR);
    }

    // @todo $suffix = '.sql';
    public function queryFormat($query)
    {
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

    public function listTables()
    {
        $return = $this->alwaysQuery("SELECT TABLE_NAME FROM USER_TABLES WHERE TABLE_NAME NOT IN ('BLOBS','LONG_IDENTIFIERS')");
        $tables = drush_shell_exec_output();
        if (!empty($tables)) {
            // Shift off the header of the column of data returned.
            array_shift($tables);
            return $tables;
        }
    }

      // @todo $file is no longer provided. We are supposed to return bash that can be piped to gzip.
      // Probably Oracle needs to override dump() entirely - http://stackoverflow.com/questions/2236615/oracle-can-imp-exp-go-to-stdin-stdout.
    public function dumpCmd($table_selection)
    {
        $create_db = $this->getOption('create-db');
        $exec = 'exp ' . $this->creds();
        // Change variable '$file' by reference in order to get drush_log() to report.
        if (!$file) {
            $file = $this->dbSpec['username'] . '.dmp';
        }
        $exec .= ' file=' . $file;

        if (!empty($tables)) {
            $exec .= ' tables="(' . implode(',', $tables) . ')"';
        }
        $exec .= ' owner=' . $this->dbSpec['username'];
        if ($option = $this->getOption('extra-dump', $this->queryExtra)) {
            $exec .= " $option";
        }
        return [$exec, $file];
    }
}
