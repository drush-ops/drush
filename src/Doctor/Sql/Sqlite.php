<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

/**
 * SQLite. The database is a file, so there are no credentials, and a dump
 * is `.dump` of that file. Table selection does not apply to the dump.
 */
class Sqlite extends Client
{
    public function program(): string
    {
        return 'sqlite3';
    }

    public function dumpProgram(): string
    {
        return 'sqlite3';
    }

    public function connectArguments(): string
    {
        return self::shellArg($this->database());
    }

    public function listTablesSql(): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name";
    }

    public function dropTablesSql(array $tables): array
    {
        // SQLite drops one table per statement.
        return array_map(fn (string $t) => "DROP TABLE \"$t\"", $tables);
    }

    public function dumpCommand(TableSelection $tables, DumpOptions $options): string
    {
        // Dumping a sqlite database is usually unnecessary, since the file can
        // be copied, but it still serves migrations and readable diffs.
        $command = $this->connectString() . ' ".dump"';
        if ($options->extraDump !== null) {
            $command .= ' ' . $options->extraDump;
        }
        return $command;
    }
}
