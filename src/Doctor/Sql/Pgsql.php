<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

/**
 * PostgreSQL. psql and pg_dump take no password on the command line; they
 * read PGPASSWORD, which environment() provides to the processes the
 * commands run. The connect string users paste relies on PGPASSFILE or
 * ~/.pgpass instead.
 */
class Pgsql extends Client
{
    public function program(): string
    {
        return 'psql';
    }

    public function dumpProgram(): string
    {
        return 'pg_dump';
    }

    public function connectArguments(): string
    {
        return '-q ON_ERROR_STOP=1 ' . $this->toOptions($this->parameters());
    }

    public function environment(): array
    {
        return isset($this->info['password']) ? ['PGPASSWORD' => (string) $this->info['password']] : [];
    }

    /**
     * Host and port default as core's pgsql driver does.
     *
     * @return array<string, string|int>
     */
    protected function parameters(): array
    {
        $info = $this->info;
        $parameters = [
            'dbname' => empty($info['database']) ? 'template1' : $info['database'],
            'host' => empty($info['host']) ? 'localhost' : $info['host'],
            'port' => empty($info['port']) ? '5432' : $info['port'],
        ];
        if (isset($info['username'])) {
            $parameters['username'] = $info['username'];
        }
        return $parameters;
    }

    public function listTablesSql(): string
    {
        return "SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename";
    }

    public function dropTablesSql(array $tables): array
    {
        return $tables ? ['DROP TABLE ' . implode(', ', array_map(fn (string $t) => "\"$t\"", $tables)) . ' CASCADE'] : [];
    }

    public function dumpCommand(TableSelection $tables, DumpOptions $options): string
    {
        $extra = ' ' . $this->toOptions($this->parameters());
        if ($options->dataOnly) {
            $extra .= ' --data-only';
        }
        if ($options->extraDump !== null) {
            $extra .= ' ' . $options->extraDump;
        }
        // DROP statements interfere with an import into a freshly created
        // database, so --create-db leaves them out.
        $clean = (!$options->createDb && !$options->dataOnly) ? ' --clean' : '';

        $command = $this->dumpProgram() . $extra . $clean;
        if ($tables->tables) {
            foreach ($tables->tables as $table) {
                $command .= " --table=$table";
            }
            return $command;
        }
        foreach ([...$tables->structure, ...$tables->skip] as $table) {
            $command .= " --exclude-table=$table";
        }
        if ($tables->structure) {
            $command .= ' && ' . $this->dumpProgram() . ' --schema-only';
            foreach ($tables->structure as $table) {
                $command .= " --table=$table";
            }
            $command .= $extra . $clean;
            return "($command)";
        }
        return $command;
    }
}
