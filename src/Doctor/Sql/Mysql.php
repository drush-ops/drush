<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

use Symfony\Component\Process\ExecutableFinder;

/**
 * MySQL and MariaDB. Core's driver name is 'mysql' for both; the MariaDB
 * client is used only when no mysql client is installed, since either
 * speaks to both servers.
 */
class Mysql extends Client
{
    public function program(): string
    {
        return $this->mariadb() ? 'mariadb' : 'mysql';
    }

    public function dumpProgram(): string
    {
        return $this->mariadb() ? 'mariadb-dump' : 'mysqldump';
    }

    protected function mariadb(): bool
    {
        static $mariadb;
        if ($mariadb === null) {
            $finder = new ExecutableFinder();
            $mariadb = $finder->find('mysql') === null && $finder->find('mariadb') !== null;
        }
        return $mariadb;
    }

    public function connectArguments(): string
    {
        return $this->toOptions($this->parameters(true));
    }

    public function environment(): array
    {
        return isset($this->info['password']) ? ['MYSQL_PWD' => (string) $this->info['password']] : [];
    }

    /**
     * @param bool $withPassword
     *   Whether to include --password. The connect string carries it since
     *   users paste it; the dump and import processes get it from MYSQL_PWD
     *   instead, so that it is on no command line.
     *
     * @return array<string, string|int>
     */
    protected function parameters(bool $withPassword): array
    {
        $info = $this->info;
        $parameters = [];
        if (isset($info['username'])) {
            $parameters['user'] = $info['username'];
        }
        // An empty password is a password; only an absent one is left out.
        if ($withPassword && isset($info['password'])) {
            $parameters['password'] = $info['password'];
        }
        // Connect to a built-in database when the site's has no name.
        $parameters['database'] = empty($info['database']) ? 'information_schema' : $info['database'];
        if (!empty($info['unix_socket'])) {
            $parameters['socket'] = $info['unix_socket'];
        } elseif (isset($info['host'])) {
            // An empty host is valid alongside a socket, so isset, not empty.
            $parameters['host'] = $info['host'];
        }
        if (!empty($info['port'])) {
            $parameters['port'] = $info['port'];
        }
        if (!empty($info['pdo']['unix_socket'])) {
            $parameters['socket'] = $info['pdo']['unix_socket'];
        }

        // Core's mysql driver reads SSL settings from the pdo key of the
        // connection info, keyed by the PDO attribute constants.
        $ssl = [
            'ssl-ca' => \Pdo\Mysql::ATTR_SSL_CA,
            'ssl-capath' => \Pdo\Mysql::ATTR_SSL_CAPATH,
            'ssl-cert' => \Pdo\Mysql::ATTR_SSL_CERT,
            'ssl-cipher' => \Pdo\Mysql::ATTR_SSL_CIPHER,
            'ssl-key' => \Pdo\Mysql::ATTR_SSL_KEY,
        ];
        foreach ($ssl as $option => $attribute) {
            if (!empty($info['pdo'][$attribute])) {
                $parameters[$option] = $info['pdo'][$attribute];
            }
        }
        // Not declared on PHP builds without the mysqlnd SSL attribute.
        if (defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $attribute = constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT');
            if (isset($info['pdo'][$attribute])) {
                $parameters['ssl-verify-server-cert'] = $info['pdo'][$attribute] ? '1' : '0';
            }
        }

        return $parameters;
    }

    public function listTablesSql(): string
    {
        return 'SHOW TABLES';
    }

    public function dropTablesSql(array $tables): array
    {
        return $tables ? ['DROP TABLE ' . implode(', ', array_map(fn (string $t) => "`$t`", $tables))] : [];
    }

    public function dumpCommand(TableSelection $tables, DumpOptions $options): string
    {
        // mysqldump wants the database name as an argument, not --database=.
        $parameters = $this->parameters(false);
        $database = $parameters['database'];
        unset($parameters['database']);
        $connect = $this->toOptions($parameters) . ' ' . self::shellArg((string) $database);

        // --skip-add-locks was here once to help people with insufficient
        // permissions; it slows the import down a lot, see drupal.org/node/1283978.
        $extra = ' --no-autocommit --single-transaction --opt -Q';
        if ($options->dataOnly) {
            $extra .= ' --no-create-info';
        }
        if ($options->orderedDump) {
            $extra .= ' --skip-extended-insert --order-by-primary';
        }
        if ($options->extraDump !== null) {
            $extra .= ' ' . $options->extraDump;
        }

        $command = $this->dumpProgram() . ' ' . $connect . $extra;
        if ($tables->tables) {
            return $command . ' ' . implode(' ', $tables->tables);
        }
        $ignored = [];
        foreach ([...$tables->structure, ...$tables->skip] as $table) {
            $ignored[] = '--ignore-table=' . $database . '.' . $table;
        }
        if ($ignored) {
            $command .= ' ' . implode(' ', $ignored);
        }
        // A second run appends the schema of the structure-only tables.
        if ($tables->structure) {
            $command .= ' && ' . $this->dumpProgram() . ' ' . $connect . ' --no-data' . $extra . ' ' . implode(' ', $tables->structure);
        }
        return ($ignored || $tables->structure) ? "($command)" : $command;
    }
}
