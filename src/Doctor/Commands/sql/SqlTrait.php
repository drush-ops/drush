<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Core\Database\Database;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Shared by the sql commands that run under dr.
 *
 * Everything here comes from core's Database registry: the connection info
 * that settings.php declares, or one parsed from --db-url. Nothing is read
 * from Drush configuration.
 */
trait SqlTrait
{
    /**
     * The connection info the command works with.
     *
     * @param string $database
     *   The connection key in settings.php, usually 'default'.
     * @param string $target
     *   The target within that connection, usually 'default'.
     * @param string|null $dbUrl
     *   A database URL that replaces the site's settings when given.
     *
     * @return array<string, mixed>
     *   Core connection info: driver, database, username, password, host,
     *   port, prefix, pdo and so on, as the driver needs them.
     */
    protected function connectionInfo(string $database, string $target, ?string $dbUrl): array
    {
        if ($dbUrl !== null && $dbUrl !== '') {
            return Database::convertDbUrlToConnectionInfo($dbUrl);
        }
        $info = Database::getConnectionInfo($database);
        if (!$info) {
            throw new \InvalidArgumentException("Database connection $database is not defined.");
        }
        if (!isset($info[$target])) {
            throw new \InvalidArgumentException("Database connection $database has no target $target.");
        }
        return $info[$target];
    }

    /**
     * The command-line client for the connection's driver.
     *
     * Core's driver is 'mysql' for MariaDB too. The MariaDB client is used
     * only when no mysql client is installed, since either speaks to both
     * servers.
     */
    protected function clientProgram(array $info): string
    {
        return match ($info['driver'] ?? '') {
            'mysql' => $this->mysqlProgram(),
            'pgsql' => 'psql',
            'sqlite' => 'sqlite3',
            default => throw new \InvalidArgumentException(sprintf('No database client is known for the %s driver.', $info['driver'] ?? '')),
        };
    }

    /**
     * A shell command line that opens the client on the connection.
     *
     * @param string|null $extra
     *   Appended verbatim, for client options such as --skip-column-names.
     */
    protected function connectString(array $info, ?string $extra = null): string
    {
        $program = $this->clientProgram($info);
        $parts = match ($info['driver']) {
            'mysql' => [$program, $this->mysqlArguments($info)],
            'pgsql' => [$program, '-q', 'ON_ERROR_STOP=1', $this->pgsqlArguments($info)],
            'sqlite' => [$program, $this->shellArg($info['database'])],
            // clientProgram() has already rejected any other driver.
            default => throw new \LogicException('Unreachable'),
        };
        if ($extra !== null && $extra !== '') {
            $parts[] = $extra;
        }
        return implode(' ', $parts);
    }

    protected function mysqlProgram(): string
    {
        $finder = new ExecutableFinder();
        if ($finder->find('mysql') === null && $finder->find('mariadb') !== null) {
            return 'mariadb';
        }
        return 'mysql';
    }

    protected function mysqlArguments(array $info): string
    {
        $parameters = [];
        if (isset($info['username'])) {
            $parameters['user'] = $info['username'];
        }
        // An empty password is a password; only an absent one is left out.
        if (isset($info['password'])) {
            $parameters['password'] = $info['password'];
        }
        // Connect to a built-in database when the site's does not exist yet,
        // which is what sql:create needs.
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

        return $this->toOptions($parameters);
    }

    /**
     * psql takes no password on the command line; it reads PGPASSFILE or
     * ~/.pgpass instead. Host and port default as core's pgsql driver does.
     */
    protected function pgsqlArguments(array $info): string
    {
        $parameters = [
            'dbname' => empty($info['database']) ? 'template1' : $info['database'],
            'host' => empty($info['host']) ? 'localhost' : $info['host'],
            'port' => empty($info['port']) ? '5432' : $info['port'],
        ];
        if (isset($info['username'])) {
            $parameters['username'] = $info['username'];
        }
        return $this->toOptions($parameters);
    }

    /**
     * @param array<string, string|int> $parameters
     */
    protected function toOptions(array $parameters): string
    {
        $options = [];
        foreach ($parameters as $name => $value) {
            $options[] = "--$name=" . $this->shellArg((string) $value);
        }
        return implode(' ', $options);
    }

    /**
     * Quotes a value for the shell only when it needs it, so that the usual
     * `$(dr sql:connect)` keeps working without eval. Same rule as Drush's
     * own sql:connect.
     */
    protected function shellArg(string $value): string
    {
        return preg_match('|^[a-zA-Z0-9@=.:/_-]*$|', $value) ? $value : escapeshellarg($value);
    }
}
