<?php

namespace Drush\Commands\core;

use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Backend\BackendPathEvaluator;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Sql\SqlBase;
use Drush\Utils\FsUtils;
use Exception;
use Phar;
use PharData;
use Symfony\Component\Yaml\Yaml;

class ArchiveCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * @var string
     */
    private string $archiveDir;

    private const CODE_ARCHIVE_FILE_NAME = 'code.tar';
    private const DRUPAL_FILES_ARCHIVE_FILE_NAME = 'files.tar';
    private const SQL_DUMP_FILE_NAME = 'database.sql';
    private const SQL_DUMP_ARCHIVE_FILE_NAME = 'database.tar';
    private const ARCHIVE_FILE_NAME = 'archive.tar';
    private const MANIFEST_FORMAT_VERSION = '1.0';
    private const MANIFEST_FILE_NAME = 'MANIFEST.yml';

    /**
     * Backup your code, files, and database into a single file.
     *
     * @command archive:dump
     * @aliases ard
     *
     * @option code Archive codebase.
     * @option files Archive Drupal files.
     * @option db Archive database SQL dump.
     * @option destination The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory and a filename will be generated.
     * @option description Describe the archive contents.
     * @option tags Add tags to the archive manifest. Delimit several by commas.
     * @option overwrite Do not fail if the destination file exists; overwrite it instead. Default is --no-overwrite.
     * @option generator The generator name to store in the MANIFEST.yml file. The default is "Drush archive-dump".
     * @option generatorversion The generator version number to store in the MANIFEST file. The default is Drush version.
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
    public function dump(array $options = [
        'code' => false,
        'files' => false,
        'db' => false,
        'description' => null,
        'tags' => null,
        'generator' => null,
        'generatorversion' => null,
    ]): void
    {
        $this->archiveDir = FsUtils::prepareBackupDir('archives');

        if (!$options['code'] && !$options['files'] && !$options['db']) {
            $options['code'] = $options['files'] = $options['db'] = true;
        }

        $archiveComponents = [];

        if ($options['code']) {
            // @todo: implement
            // $codeArchiveFilePath = $this->createCodeArchive();
            // $archiveComponents[self::CODE_ARCHIVE_FILE_NAME] = $codeArchiveFilePath;
        }

        if ($options['files']) {
            $drupalFilesArchiveFilePath = $this->createDrupalFilesArchive();
            $archiveComponents[self::DRUPAL_FILES_ARCHIVE_FILE_NAME] = $drupalFilesArchiveFilePath;
        }

        if ($options['db']) {
            $sqlDumpArchiveFilePath = $this->createSqlDumpArchive($options);
            $archiveComponents[self::SQL_DUMP_ARCHIVE_FILE_NAME] = $sqlDumpArchiveFilePath;
        }

        $this->createMasterArchive($archiveComponents, $options);
    }

    /**
     * Creates the master archive file.
     *
     * @param array $archiveComponents
     *   The list of components (files) to include into the master archive file.
     *
     * @throws \Exception
     */
    private function createMasterArchive(array $archiveComponents, array $options): void
    {
        if (!$archiveComponents) {
            throw new Exception(dt('Nothing to archive'));
        }

        $this->logger()->info(dt('Creating master archive...'));
        $archivePath = implode([$this->archiveDir, DIRECTORY_SEPARATOR, self::ARCHIVE_FILE_NAME]);
        $archive = new PharData($archivePath);

        foreach ($archiveComponents as $localName => $fileName) {
            $this->logger()->info(dt('Adding !file to archive...', ['!file' => $fileName]));
            $archive->addFile($fileName, $localName);
            $this->logger()->info(dt('!file has been added.', ['!file' => $fileName]));
        }

        $archive->addFile($this->createManifestFile($options), self::MANIFEST_FILE_NAME);

        $archive->compress(Phar::GZ);
        unset($archive);
        Phar::unlinkArchive($archivePath);

        // @todo: account for --destination options
        $this->logger()->success(
            dt('Master archive has been created: !path', ['!path' => $archivePath . '.gz'])
        );
    }

    /**
     * Creates the MANIFEST file.
     *
     * @param array $options
     *
     * @return string
     */
    private function createManifestFile(array $options): string
    {
        $manifest = [
            'datestamp' => time(),
            'formatversion' => self::MANIFEST_FORMAT_VERSION,
            'components' => [
                'code' => $options['code'],
                'files' => $options['files'],
                'database' => $options['db'],
            ],
            'description' => $options['description'] ?? null,
            'tags' => $options['tags'] ?? null,
            'generator' => $options['generator'] ?? 'Drush archive:dump',
            'generatorversion' => $options['generatorversion'] ?? Drush::getVersion(),
        ];
        $manifestFilePath = $this->archiveDir . DIRECTORY_SEPARATOR . self::MANIFEST_FILE_NAME;
        file_put_contents(
            $manifestFilePath,
            Yaml::dump($manifest)
        );

        return$manifestFilePath;
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
