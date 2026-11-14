<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

/**
 * The command-line client for one database connection, for the sql commands
 * that run under dr.
 *
 * Built from core's connection info (settings.php or a --db-url) and nothing
 * else: no Drush configuration, no temp files, no process manager. Listing
 * and dropping tables go through core's Schema API; only dumps, file imports
 * and the interactive cli need the client binary.
 */
abstract class Client
{
    /**
     * @param array<string, mixed> $info
     *   Core connection info: driver, database, username, password, host,
     *   port, prefix, pdo and so on.
     */
    final public function __construct(protected readonly array $info)
    {
    }

    /**
     * @param array<string, mixed> $info
     *   Core connection info.
     */
    public static function fromInfo(array $info): self
    {
        $class = match ($info['driver'] ?? '') {
            'mysql' => Mysql::class,
            'pgsql' => Pgsql::class,
            'sqlite' => Sqlite::class,
            default => throw new \InvalidArgumentException(sprintf('No database client is known for the %s driver.', $info['driver'] ?? '')),
        };
        return new $class($info);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->info;
    }

    /**
     * The database name, or the file path for sqlite.
     */
    public function database(): string
    {
        return (string) ($this->info['database'] ?? '');
    }

    /**
     * The client binary.
     */
    abstract public function program(): string;

    /**
     * The dump binary.
     */
    abstract public function dumpProgram(): string;

    /**
     * Options that make the client connect to this database.
     */
    abstract public function connectArguments(): string;

    /**
     * Environment variables for the client processes: the password, so that
     * it is on no command line.
     *
     * @return array<string, string>
     */
    public function environment(): array
    {
        return [];
    }

    /**
     * A shell command line that dumps the database to stdout.
     */
    abstract public function dumpCommand(TableSelection $tables, DumpOptions $options): string;

    /**
     * A shell command line that opens the client on the database.
     *
     * @param string|null $extra
     *   Appended verbatim, for client options such as --skip-column-names.
     */
    public function connectString(?string $extra = null): string
    {
        $command = $this->program() . ' ' . $this->connectArguments();
        if ($extra !== null && $extra !== '') {
            $command .= ' ' . $extra;
        }
        return $command;
    }

    /**
     * Quotes a value for the shell only when it needs it, so that the usual
     * `$(dr sql:connect)` keeps working without eval. Same rule as Drush's
     * own sql:connect.
     */
    public static function shellArg(string $value): string
    {
        return preg_match('|^[a-zA-Z0-9@=.:/_-]*$|', $value) ? $value : escapeshellarg($value);
    }

    /**
     * @param array<string, string|int> $parameters
     */
    protected function toOptions(array $parameters): string
    {
        $options = [];
        foreach ($parameters as $name => $value) {
            $options[] = "--$name=" . self::shellArg((string) $value);
        }
        return implode(' ', $options);
    }
}
