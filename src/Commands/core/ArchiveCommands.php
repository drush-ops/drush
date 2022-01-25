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
     * @var string
     */
    private string $archiveDir;

    private const SQL_DUMP_FILE_NAME = 'database.sql';
    private const SQL_DUMP_ARCHIVE_FILE_NAME = 'database.tar';
    private const DRUPAL_FILES_ARCHIVE_FILE_NAME = 'files.tar';
    private const ARCHIVE_FILE_NAME = 'archive.tar';

    /**
     * ArchiveCommands constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->archiveDir = FsUtils::prepareBackupDir('archives');
    }

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
        // Create SQL dump (database.tar) file.
        $sqlDumpArchiveFilePath = $this->createSqlDumpArchive($options);

        // Create "files" archive.
        $drupalFilesArchiveFilePath = $this->createDrupalFilesArchive();

        // Create the final archive.tar.gz file
        $archivePath = implode([$this->archiveDir, DIRECTORY_SEPARATOR, self::ARCHIVE_FILE_NAME]);
        $archive = new PharData($archivePath);
        $archive->addFile($drupalFilesArchiveFilePath, self::DRUPAL_FILES_ARCHIVE_FILE_NAME);
        $archive->addFile($sqlDumpArchiveFilePath, self::SQL_DUMP_ARCHIVE_FILE_NAME);
        $archive->compress(Phar::GZ, 'tar.gz');
        unset($archive);
        Phar::unlinkArchive($archivePath);

        $this->logger()->info(dt('Archive path: !path', ['!path' => $archivePath . '.gz']));
    }

    /**
     * Create an archive of site's Drupal files.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function createDrupalFilesArchive(): string
    {
        $evaluatedPath = HostPath::create($this->siteAliasManager(), '%files');
        $pathEvaluator = new BackendPathEvaluator();
        $pathEvaluator->evaluate($evaluatedPath);
        $drupalFilesDir = $evaluatedPath->fullyQualifiedPath();

        $this->logger()->info(dt('Archiving files !dir...', ['!dir' => $drupalFilesDir]));
        $drupalFilesArchiveFilePath = implode(
            [$this->archiveDir, DIRECTORY_SEPARATOR, self::DRUPAL_FILES_ARCHIVE_FILE_NAME]
        );
        $archive = new PharData($drupalFilesArchiveFilePath);
        $archive->buildFromDirectory($drupalFilesDir);
        $this->logger()->success(
            dt('Files archive has been created: !path', ['!path' => $drupalFilesArchiveFilePath])
        );

        return $drupalFilesArchiveFilePath;
    }

    /**
     * Creates an archive with SQL dump file and returns the path to the resulting archive file.
     *
     * @param array $options
     *
     * @return string
     *   The full path to the SQl dump file.
     *
     * @throws \Exception
     *
     * @see \Drush\Commands\sql\SqlCommands::dump()
     */
    private function createSqlDumpArchive(array $options): string
    {
        $this->logger()->info(dt('Creating database SQL dump file...'));
        $options['result-file'] = implode([$this->archiveDir, DIRECTORY_SEPARATOR, self::SQL_DUMP_FILE_NAME]);
        $sql = SqlBase::create($options);
        $sqlDumpFilePath = $sql->dump();
        if (false === $sqlDumpFilePath) {
            throw new Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        $this->logger()->info(dt('Archiving database SQL dump file...'));
        $sqlDumpArchiveFilePath = implode(
            [$this->archiveDir, DIRECTORY_SEPARATOR,  self::SQL_DUMP_ARCHIVE_FILE_NAME]
        );
        $archive = new PharData($sqlDumpArchiveFilePath);
        $archive->addFile($sqlDumpFilePath, self::SQL_DUMP_FILE_NAME);
        unlink($sqlDumpFilePath);

        $this->logger()->success(
            dt('Database SQL dump archive file has been created: !path', ['!path' => $sqlDumpArchiveFilePath])
        );

        return $sqlDumpArchiveFilePath;
    }
}
