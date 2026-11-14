<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drush\Doctor\Commands\ConsoleTrait;
use Drush\Doctor\Sql\Client;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Shared by the sql commands that run under dr.
 *
 * Everything here comes from core's Database registry: the connection info
 * that settings.php declares, or one parsed from --db-url. Nothing is read
 * from Drush configuration. The client binaries are described by
 * Drush\Doctor\Sql\Client and its drivers.
 */
trait SqlTrait
{
    use ConsoleTrait;

    /**
     * The connection key a --db-url is registered under.
     */
    private const string URL_KEY = 'drush_db_url';

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
     * Core's connection for the same selection, for the SQL the commands run
     * themselves. A --db-url connection is registered under its own key.
     */
    protected function connection(string $database, string $target, ?string $dbUrl): Connection
    {
        if ($dbUrl !== null && $dbUrl !== '') {
            if (!Database::getConnectionInfo(self::URL_KEY)) {
                Database::addConnectionInfo(self::URL_KEY, 'default', $this->connectionInfo($database, $target, $dbUrl));
            }
            return Database::getConnection('default', self::URL_KEY);
        }
        // Validates the key and target before core does, for a clearer message.
        $this->connectionInfo($database, $target, null);
        return Database::getConnection($target, $database);
    }

    protected function client(array $info): Client
    {
        return Client::fromInfo($info);
    }

    /**
     * Throws when the client (or dump) binary is not installed.
     */
    protected function requireProgram(string $program): void
    {
        if ((new ExecutableFinder())->find($program) === null) {
            throw new \RuntimeException(sprintf("The shell command '%s' is required but cannot be found. Please install it and retry.", $program));
        }
    }

    /**
     * The tables in the connection's database, as the server names them.
     *
     * @return string[]
     */
    protected function listTables(Connection $connection, Client $client): array
    {
        $tables = $connection->query($client->listTablesSql())->fetchCol();
        sort($tables);
        return $tables;
    }

    /**
     * Runs a shell command line, streaming its output, and returns the exit
     * code. At -v the command line is shown on the error output.
     *
     * @param array<string, string> $env
     */
    protected function runShell(string $command, array $env, OutputInterface $output, ?callable $stdout = null): Process
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        if ($output->isVerbose()) {
            $errorOutput->writeln('<comment>' . $command . '</comment>');
        }
        $process = Process::fromShellCommandline($command, null, $env, null, null);
        $process->run(function (string $type, string $buffer) use ($output, $errorOutput, $stdout): void {
            if ($type === Process::ERR) {
                $errorOutput->write($buffer);
            } elseif ($stdout !== null) {
                $stdout($buffer);
            } else {
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            }
        });
        return $process;
    }

    /**
     * A shell command line that opens the client on the connection.
     */
    protected function connectString(array $info, ?string $extra = null): string
    {
        return $this->client($info)->connectString($extra);
    }
}
