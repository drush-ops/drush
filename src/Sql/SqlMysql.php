<?php

namespace Drush\Sql;

use PDO;

class SqlMysql extends SqlBase
{
    public $queryExtra = '-A';

    public function command(): string
    {
        return 'mysql';
    }

    public function creds($hide_password = true): string
    {
        $dbSpec = $this->getDbSpec();
        if ($hide_password) {
            // Default to unix socket if configured.
            $unixSocket = empty($dbSpec['unix_socket']) ? '' : 'socket="' . $dbSpec['unix_socket'] . '"';

            // EMPTY password is not the same as NO password, and is valid.
            $contents = <<<EOT
#This file was written by Drush's Sqlmysql.php.
[client]
user="{$dbSpec['username']}"
password="{$dbSpec['password']}"
{$unixSocket}
EOT;

            $file = drush_save_data_to_temp_file($contents);
            $parameters['defaults-file'] = $file;
        } else {
            // User is required. Drupal calls it 'username'. MySQL calls it 'user'.
            $parameters['user'] = $dbSpec['username'];
            // EMPTY password is not the same as NO password, and is valid.
            if (isset($dbSpec['password'])) {
                $parameters['password'] = $dbSpec['password'];
            }
        }

        // Some Drush commands (e.g. site-install) want to connect to the
        // server, but not the database.  Connect to the built-in database.
        $parameters['database'] = empty($dbSpec['database']) ? 'information_schema' : $dbSpec['database'];

        // Default to unix socket if configured.
        if (!empty($dbSpec['unix_socket'])) {
            $parameters['socket'] = $dbSpec['unix_socket'];
        } elseif (isset($dbSpec['host'])) {
            // EMPTY host is not the same as NO host, and is valid (see unix_socket).
            $parameters['host'] = $dbSpec['host'];
        }

        if (!empty($dbSpec['port'])) {
            $parameters['port'] = $dbSpec['port'];
        }

        if (!empty($dbSpec['pdo']['unix_socket'])) {
            $parameters['socket'] = $dbSpec['pdo']['unix_socket'];
        }

        if (!empty($dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CA])) {
            $parameters['ssl-ca'] = $dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CA];
        }

        if (!empty($dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CAPATH])) {
            $parameters['ssl-capath'] = $dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CAPATH];
        }

        if (!empty($dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CERT])) {
            $parameters['ssl-cert'] = $dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CERT];
        }

        if (!empty($dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CIPHER])) {
            $parameters['ssl-cipher'] = $dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_CIPHER];
        }

        if (!empty($dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_KEY])) {
            $parameters['ssl-key'] = $dbSpec['pdo'][PDO::MYSQL_ATTR_SSL_KEY];
        }

        return $this->paramsToOptions($parameters);
    }

    public function silent(): string
    {
        return '--silent';
    }

    public function createdbSql($dbname, $quoted = false): string
    {
        $dbSpec = $this->getDbSpec();
        if ($quoted) {
            $dbname = '`' . $dbname . '`';
        }
        $sql[] = sprintf('DROP DATABASE IF EXISTS %s;', $dbname);
        $sql[] = sprintf('CREATE DATABASE %s /*!40100 DEFAULT CHARACTER SET utf8 */;', $dbname);
        $db_superuser = $this->getOption('db-su');
        if (isset($db_superuser)) {
            // - For a localhost database, create a localhost user.  This is important for security.
            //   localhost is special and only allows local Unix socket file connections.
            // - If the database is on a remote server, create a wildcard user with %.
            //   We can't easily know what IP address or hostname would represent our server.
            $domain = ($dbSpec['host'] == 'localhost') ? 'localhost' : '%';
            $user = sprintf("'%s'@'%s'", $dbSpec['username'], $domain);
            $sql[] = sprintf("DROP USER IF EXISTS %s;", $user);
            $sql[] = sprintf("CREATE USER %s IDENTIFIED WITH mysql_native_password;", $user);

            // For MariaDB, ALTER USER was introduced in version 10.2. Support
            // for 10.1 ended in October 2020.
            $sql[] = sprintf("ALTER USER %s IDENTIFIED BY '%s';", $user, $dbSpec['password']);
            $sql[] = sprintf('GRANT ALL PRIVILEGES ON %s.* TO %s;', $dbname, $user);
            $sql[] = 'FLUSH PRIVILEGES;';
        }
        return implode(' ', $sql);
    }

    /**
     * @inheritdoc
     */
    public function dbExists(): bool
    {
        // Suppress output. We only care about return value.
        return $this->alwaysQuery("SELECT 1;");
    }

    public function listTables(): array
    {
        $tables = [];
        $this->alwaysQuery('SHOW TABLES;');
        if ($out = trim($this->getProcess()->getOutput())) {
            $tables = explode(PHP_EOL, $out);
        }
        return $tables;
    }

    public function listTablesQuoted(): array
    {
        $tables = $this->listTables();
        foreach ($tables as &$table) {
            $table = "`$table`";
        }
        return $tables;
    }

    public function dumpCmd($table_selection): string
    {
        $dbSpec = $this->getDbSpec();
        $parens = false;
        $skip_tables = $table_selection['skip'];
        $structure_tables = $table_selection['structure'];
        $tables = $table_selection['tables'];

        $ignores = [];
        $skip_tables  = array_merge($structure_tables, $skip_tables);
        $data_only = $this->getOption('data-only');
        // The ordered-dump option is only supported by MySQL for now.
        $ordered_dump = $this->getOption('ordered-dump');

        $exec = 'mysqldump ';
        // mysqldump wants 'databasename' instead of 'database=databasename' for no good reason.
        $only_db_name = str_replace('--database=', ' ', $this->creds());
        $exec .= $only_db_name;

        // We had --skip-add-locks here for a while to help people with insufficient permissions,
        // but removed it because it slows down the import a lot.  See http://drupal.org/node/1283978
        $extra = ' --no-autocommit --single-transaction --opt -Q';
        if ($data_only) {
            $extra .= ' --no-create-info';
        }
        if ($ordered_dump) {
            $extra .= ' --skip-extended-insert --order-by-primary';
        }
        if ($option = $this->getOption('extra-dump')) {
            $extra .= " $option";
        }
        $exec .= $extra;

        if (!empty($tables)) {
            $exec .= ' ' . implode(' ', $tables);
        } else {
            // Append the ignore-table options.
            foreach ($skip_tables as $table) {
                $ignores[] = '--ignore-table=' . $dbSpec['database'] . '.' . $table;
                $parens = true;
            }
            $exec .= ' ' . implode(' ', $ignores);

            // Run mysqldump again and append output if we need some structure only tables.
            if (!empty($structure_tables)) {
                $exec .= " && mysqldump " . $only_db_name . " --no-data $extra " . implode(' ', $structure_tables);
                $parens = true;
            }
        }
        return $parens ? "($exec)" : $exec;
    }
}
