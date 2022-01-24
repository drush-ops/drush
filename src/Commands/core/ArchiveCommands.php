<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Sql\SqlBase;
use Exception;

class ArchiveCommands extends DrushCommands
{
    /**
     * Backup your code, files, and database into a single file.
     *
     * @command archive:dump
     * @aliases ard
     *
     * @optionset_sql
     * @optionset_table_selection
     *
     * @bootstrap max configuration
     *
     * @throws \Exception
     */
    public function dump(array $options = []): void
    {
        $sqlDumpFilePath = $this->performSqlDump($options);

        $this->logger()->success(dt('Database dump saved to !path', ['!path' => $sqlDumpFilePath]));
    }

    /**
     * Executes an SQL dump and returns the path to the resulting dump file.
     *
     * @param array $options
     *
     * @return string
     *
     * @throws \Exception
     *
     * @see \Drush\Commands\sql\SqlCommands::dump()
     */
    private function performSqlDump(array $options): string
    {
        $options['result-file'] = implode(
          [$this->getProjectPath(), DIRECTORY_SEPARATOR, 'archive_sql_dump.sql']
        );
        $sql = SqlBase::create($options);
        $sqlDumpResult = $sql->dump();
        if (false === $sqlDumpResult) {
            throw new Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        return $sqlDumpResult;
    }

    /**
     * Returns the path to the site's Composer project.
     *
     * The site's Composer project is one level up since the Drupal root is in the "web" directory.
     *
     * @return string
     */
    private function getProjectPath(): string
    {
        return dirname(Drush::bootstrapManager()->getRoot());
    }
}
