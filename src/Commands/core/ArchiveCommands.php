<?php

namespace Drush\Commands\core;

use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Backend\BackendPathEvaluator;
use Drush\Commands\DrushCommands;
use Drush\Sql\SqlBase;
use Drush\Utils\FsUtils;
use Exception;
use Phar;
use PharData;

class ArchiveCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Backup your code, files, and database into a single file.
     *
     * @command archive:dump
     * @aliases ard
     *
     * @option description Describe the archive contents.
     * @option tags Add tags to the archive manifest. Delimit several by commas.
     * @option destination The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory and a filename will be generated.
     * @option overwrite Do not fail if the destination file exists; overwrite it instead. Default is --no-overwrite.
     *
     * @optionset_sql
     * @optionset_table_selection
     *
     * @bootstrap max configuration
     *
     * @param array $options
     *
     * @throws \Exception
     */
    public function dump(array $options = []): void
    {
        // Prepare "archives" directory.
        $archiveDir = FsUtils::prepareBackupDir('archives');
        $this->logger()->success(dt('Archive dir: !path', ['!path' => $archiveDir]));

        // Create SQL dump (database.tar) file.
        [ $sqlDumpFilePath, $sqlDumpFileName ] = $this->createSqlDump($archiveDir, $options);
        $this->logger()->success(dt('Database dump saved to !path', ['!path' => $sqlDumpFilePath]));
        $databaseArchivePath = $archiveDir . '/' . 'database.tar';
        $archive = new PharData($databaseArchivePath);
        $archive->addFile($sqlDumpFilePath, $sqlDumpFileName);
        $this->logger()->success(dt('Database archive path: !path', ['!path' => $databaseArchivePath]));
        unlink($sqlDumpFilePath);

        // Create "files" archive.
        $filesDirPath = $this->getDrupalFilesDir();
        $this->logger()->success(dt('Files dir: !path', ['!path' => $filesDirPath]));
        $filesArchivePath = $archiveDir . '/' . 'files.tar';
        $archive = new PharData($filesArchivePath);
        $archive->buildFromDirectory($filesDirPath);
        $this->logger()->success(dt('Files archive path: !path', ['!path' => $filesArchivePath]));

        // Create the final archive.tar.gz file
        $archivePath = $archiveDir . '/' . 'archive.tar';
        $archive = new PharData($archivePath);
        $archive->addFile($filesArchivePath, 'files.tar');
        $archive->addFile($databaseArchivePath, 'database.tar');
        $archive->compress(Phar::GZ, 'tar.gz');
        unset($archive);
        Phar::unlinkArchive($archivePath);

        $this->logger()->success(dt('Archive path: !path', ['!path' => $archivePath . '.gz']));
    }

    /**
     * Returns the path to Drupal "files" directory
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getDrupalFilesDir(): string
    {
      $evaluatedPath = HostPath::create($this->siteAliasManager(), '%files');
      $pathEvaluator = new BackendPathEvaluator();
      $pathEvaluator->evaluate($evaluatedPath);

      return $evaluatedPath->fullyQualifiedPath();
    }

    /**
     * Executes an SQL dump and returns the path to the resulting dump file.
     *
     * @param string $archiveDir
     * @param array $options
     *
     * @return array
     *  [0] - the full path to the SQl dump file;
     *  [1] - the SQL dump file name.
     *
     * @throws \Exception
     *
     * @see \Drush\Commands\sql\SqlCommands::dump()
     */
    private function createSqlDump(string $archiveDir, array $options): array
    {
        $sql = SqlBase::create();
        $dbName = $sql->getDbSpec()['database'];

        $sqlDumpFileName = sprintf('%s.sql', $dbName);
        $options['result-file'] = implode(
          [$archiveDir, DIRECTORY_SEPARATOR, $sqlDumpFileName]
        );
        $sql = SqlBase::create($options);
        $sqlDumpPath = $sql->dump();
        if (false === $sqlDumpPath) {
            throw new Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        return [ $sqlDumpPath, $sqlDumpFileName ];
    }
}
