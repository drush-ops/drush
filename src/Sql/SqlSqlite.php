<?php

declare(strict_types=1);

namespace Drush\Sql;

use Drush\Drush;

class SqlSqlite extends SqlBase
{
    public function command(): string
    {
        return 'sqlite3';
    }

    public function creds($hide_password = true): string
    {
        // SQLite doesn't do user management, instead relying on the filesystem
        // for that. So the only info we really need is the path to the database
        // file, and not as a "--key=value" parameter.
        return ' '  .  $this->getDbSpec()['database'];
    }

    public function createdbSql($dbname, $quoted = false): string
    {
        return '';
    }

    /**
     * Create a new database.
     *
     * @param boolean $quoted
     *   Quote the database name. Mysql uses backticks to quote which can cause problems
     *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
     */
    public function createdb(bool $quoted = false): bool
    {
        $file = $this->getDbSpec()['database'];
        if (file_exists($file)) {
            Drush::logger()->debug("SQLITE: Deleting existing database '$file'");
            drush_delete_dir($file, true);
        }

        // Make sure sqlite can create file
        $path = dirname($file);
        Drush::logger()->debug("SQLITE: creating '$path' for creating '$file'");
        if (!drush_mkdir($path)) {
            throw new \Exception("SQLITE: Cannot create $path");
        }
        return file_exists($path);
    }

    public function dbExists(): bool
    {
        return file_exists($this->getDbSpec()['database']);
    }

    public function listTables(): array
    {
        $this->alwaysQuery('.tables');
        $tables_raw = explode(PHP_EOL, trim($this->getProcess()->getOutput()));
        // SQLite's '.tables' command always outputs the table names in a column
        // format, like this:
        // table_alpha    table_charlie    table_echo
        // table_bravo    table_delta      table_foxtrot
        // …and there doesn't seem to be a way to fix that. So we need to do some
        // clean-up.
        $tables = [];
        foreach ($tables_raw as $line) {
            preg_match_all('/[^\s]+/', $line, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    $tables[] = $match;
                }
            }
        }
        return $tables;
    }

    public function drop($tables): bool
    {
        $return = true;
        $sql = '';
        if ($tables) {
            // SQLite only wants one table per DROP TABLE command (so we have to do
            // "DROP TABLE foo; DROP TABLE bar;" instead of "DROP TABLE foo, bar;").
            foreach ($tables as $table) {
                $sql .= "DROP TABLE $table; ";
            }
            $return = $this->query($sql);
        }
        return $return;
    }

    public function dumpCmd($table_selection): string
    {
        // Dumping is usually not necessary in SQLite, since all database data
        // is stored in a single file which can be copied just
        // like any other file. But it still has a use in migration purposes and
        // building human-readable diffs and such, so let's do it anyway.
        $exec = $this->connect();
        // SQLite's dump command doesn't support many of the features of its
        // Postgres or MySQL equivalents. We may be able to fake some in the
        // future, but for now, let's just support simple dumps.
        $exec .= ' ".dump"';
        if ($option = $this->getOption('extra-dump')) {
            $exec .= " $option";
        }
        return $exec;
    }
}
