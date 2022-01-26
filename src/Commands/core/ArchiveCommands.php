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
use FilesystemIterator;
use Phar;
use PharData;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

class ArchiveCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * @var string
     */
    private string $archiveDir;

    private const CODE_ARCHIVE_FILE_NAME = 'code.tar';

    private const DRUPAL_FILES_ARCHIVE_ROOT_DIR = 'files';
    private const DRUPAL_FILES_ARCHIVE_FILE_NAME = 'files.tar';

    private const SQL_DUMP_FILE_NAME = 'database.sql';
    private const DATABASE_ARCHIVE_ROOT_DIR = 'database';
    private const DATABASE_ARCHIVE_FILE_NAME = 'database.tar';

    private const ARCHIVE_DIR_NAME = 'archive';
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
     * @option destination The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory.
     * @option overwrite Overwrite destination file if exists.
     * @option description Describe the archive contents.
     * @option tags Add tags to the archive manifest. Delimit several by commas.
     * @option overwrite Do not fail if the destination file exists; overwrite it instead. Default is --no-overwrite.
     * @option generator The generator name to store in the MANIFEST.yml file. The default is "Drush archive-dump".
     * @option generatorversion The generator version number to store in the MANIFEST file. The default is Drush version.
     * @option exclude-code-paths Comma-separated list of paths to exclude from the code archive.
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
        'destination' => null,
        'overwrite' => false,
        'description' => null,
        'tags' => null,
        'generator' => null,
        'generatorversion' => null,
        'exclude-code-paths' => null,
    ]): void
    {
        $this->archiveDir = implode([FsUtils::prepareBackupDir('archives'), DIRECTORY_SEPARATOR, self::ARCHIVE_DIR_NAME]);
        mkdir($this->archiveDir);

        if (!$options['code'] && !$options['files'] && !$options['db']) {
            $options['code'] = $options['files'] = $options['db'] = true;
        }

        $archiveComponents = [];

        if ($options['code']) {
             $codeComponentPath = $this->getCodeComponentComponentPath();

             $excludes = $this->getExcludesByPaths([
                 '.git',
                 'vendor',
                 'files',
                 'web' . DIRECTORY_SEPARATOR . 'core',
                 'web' . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'files',
             ]);
             if ($options['exclude-code-paths']) {
                 $excludes = array_merge(
                     $excludes,
                     $this->getExcludesByPaths(explode(',', $options['exclude-code-paths']))
                 );
             }

             $archiveComponents[] = [
                 'name' => 'code',
                 'path' => $codeComponentPath,
                 'excludes' => $excludes,
             ];
        }

        if ($options['files']) {
            $drupalFilesComponentPath = $this->getDrupalFilesComponentPath();
            $archiveComponents[] = [
                'name' => 'files',
                'path' => $drupalFilesComponentPath,
            ];
        }

        if ($options['db']) {
            $databaseComponentPath = $this->getDatabaseComponentPath($options);
            $archiveComponents[] = [
                'name' => 'database',
                'path' => $databaseComponentPath,
            ];
        }

        $this->createArchiveFile($archiveComponents, $options);
    }

    /**
     * Creates the archive file.
     *
     * @param array $archiveComponents
     *   The list of components (files) to include into the archive file.
     * @param array $options
     *   The command options.
     *
     * @throws \Exception
     */
    private function createArchiveFile(array $archiveComponents, array $options): void
    {
        if (!$archiveComponents) {
            throw new Exception(dt('Nothing to archive'));
        }

        $this->logger()->info(dt('Creating archive...'));
        $archivePath = implode([$this->archiveDir, DIRECTORY_SEPARATOR, self::ARCHIVE_FILE_NAME]);
        $archive = new PharData($archivePath);

        foreach ($archiveComponents as $component) {
            $path = $component['path'];
            $name = $component['name'];
            $excludes = $component['excludes'] ?? [];

            $this->logger()->info(dt('Adding !path to archive...', ['!path' => $path]));

            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        $path,
                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
                    ),
                    function ($file) use ($excludes, $path) {
                        $localFileName = str_replace($path . DIRECTORY_SEPARATOR, '', $file);
                        foreach ($excludes as $exclude) {
                            if (preg_match($exclude, $localFileName)) {
                                $this->logger()->info(dt(
                                    'Path excluded (!exclude): !path',
                                    ['!exclude' => $exclude, '!path' => $localFileName]
                                ));

                                return false;
                            }
                        }

                        return true;
                    }
                )
            );

            $archive->addEmptyDir($name);
            foreach ($iterator as $file) {
                $localFileName = str_replace($path . DIRECTORY_SEPARATOR, '', $file);
                $archive->addFile($file, $name . DIRECTORY_SEPARATOR . $localFileName);
            }

            $this->logger()->info(
                dt('!path has been added to archive.', ['!path' => $path])
            );
        }

        $archive->addFile($this->createManifestFile($options), self::MANIFEST_FILE_NAME);

        $archive->compress(Phar::GZ);
        unset($archive);
        Phar::unlinkArchive($archivePath);
        $archivePath .= '.gz';

        if (!$options['destination']) {
            $this->logger()->success(
                dt('Archive file has been created: !path', ['!path' => $archivePath])
            );

            return;
        }

        if (is_file($options['destination'])) {
            if (!$options['overwrite']) {
                throw new Exception(
                    sprintf(
                        'The destination file %s already exists. Use "--overwrite" option for overwriting an existing file.',
                        $options['destination']
                    )
                );
            }

            unlink($options['destination']);
        }

        $this->logger()->info(
            dt(
                'Moving archive file from !from to !to',
                ['!from' => $archivePath, '!to' => $options['destination']]
            )
        );
        if (!rename($archivePath, $options['destination'])) {
            throw new Exception(
                sprintf(
                    'Failed moving archive from %s to %s.',
                    $archivePath,
                    $options['destination']
                )
            );
        }

        $this->logger()->success(
            dt('Archive file has been created: !path', ['!path' => $options['destination']])
        );
    }

    /**
     * Creates the MANIFEST file.
     *
     * @param array $options
     *   The command options.
     *
     * @return string
     */
    private function createManifestFile(array $options): string
    {
        $this->logger()->info(dt('Creating !manifest file...', ['!manifest' => self::MANIFEST_FILE_NAME]));
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
        $this->logger()->info(dt('Manifest file has been created: !path', ['!path' => $manifestFilePath]));

        return $manifestFilePath;
    }

    /**
     * Returns the path to the site's project directory.
     *
     * @return string
     *  The full path to the site's project directory.
     */
    private function getCodeComponentComponentPath(): string
    {
        return dirname(Drush::bootstrapManager()->getRoot());
    }

    /**
     * Returns the path to the site's Drupal files directory.
     *
     * @return string
     *  The full path to the site's Drupal files directory.
     *
     * @throws \Exception
     */
    private function getDrupalFilesComponentPath(): string
    {
        $evaluatedPath = HostPath::create($this->siteAliasManager(), '%files');
        $pathEvaluator = new BackendPathEvaluator();
        $pathEvaluator->evaluate($evaluatedPath);

        return $evaluatedPath->fullyQualifiedPath();
    }

    /**
     * Creates a database archive (SQL dump) and returns the path the database archive component directory.
     *
     * @param array $options
     *
     * @return string
     *   The full path to the database archive component directory.
     *
     * @throws \Exception
     *
     * @see \Drush\Commands\sql\SqlCommands::dump()
     */
    private function getDatabaseComponentPath(array $options): string
    {
        $this->logger()->info(dt('Creating database SQL dump file...'));
        $databaseArchiveDir = implode([$this->archiveDir, DIRECTORY_SEPARATOR, self::DATABASE_ARCHIVE_ROOT_DIR]);
        if (!mkdir($databaseArchiveDir)) {
            throw new Exception(sprintf('Failed to created directory %s for database archive', $databaseArchiveDir));
        }

        $options['result-file'] = implode([$databaseArchiveDir, DIRECTORY_SEPARATOR, self::SQL_DUMP_FILE_NAME]);
        $sql = SqlBase::create($options);
        if (false === $sql->dump()) {
            throw new Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        return $databaseArchiveDir;
    }

    /**
     * Returns the list of PCRE regular expressions to match paths.
     *
     * @param array $paths
     *
     * @return array
     */
    private function getExcludesByPaths(array $paths): array
    {
        $regexps = [];
        foreach ($paths as $path) {
            $regexps[] = sprintf('#^%s$#', addslashes(trim($path)));
        }

        return $regexps;
    }
}
