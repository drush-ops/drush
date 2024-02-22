<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManager;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\ProcessBase;
use Drupal;
use Drupal\Component\DependencyInjection\ContainerInterface;
use DrupalFinder\DrupalFinder;
use Drush\Attributes as CLI;
use Drush\Backend\BackendPathEvaluator;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Sql\SqlBase;
use Drush\Utils\FsUtils;
use Exception;
use League\Container\Container as DrushContainer;
use PharData;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Throwable;

final class ArchiveRestoreCommands extends DrushCommands
{
    const RESTORE = 'archive:restore';
    private Filesystem $filesystem;
    private ?string $destinationPath = null;

    private const COMPONENT_CODE = 'code';

    private const COMPONENT_FILES = 'files';

    private const COMPONENT_DATABASE = 'database';
    private const SQL_DUMP_FILE_NAME = 'database.sql';
    private const SITE_SUBDIR = 'default';

    private const TEMP_DIR_NAME = 'uncompressed';

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    public static function createEarly(ContainerInterface $container, DrushContainer $drush_container): self
    {
        $commandHandler = new static(
            $drush_container->get('bootstrap.manager'),
            $drush_container->get('site.alias.manager'),
        );

        return $commandHandler;
    }

    /**
     * Restore (import) your code, files, and database.
     */
    #[CLI\Command(name: self::RESTORE, aliases: ['arr'])]
    #[CLI\Argument(name: 'path', description:
        'The full path to a single archive file (*.tar.gz) or a directory with components to import.
         *   May contain the following components generated by `archive:dump` command:
         *   1) code ("code" directory);
         *   2) database dump file ("database/database.sql" file);
         *   3) Drupal files ("files" directory).')]
    #[CLI\Argument(name: 'site', description: 'Destination site alias. Defaults to @self.')]
    #[CLI\Option(name: 'destination-path', description: 'The base path to restore the code/files into.')]
    #[CLI\Option(name: 'overwrite', description: 'Overwrite files if exists when un-compressing an archive.')]
    #[CLI\Option(name: 'site-subdir', description: 'Site subdirectory to put settings.local.php into.')]
    #[CLI\Option(name: 'setup-database-connection', description: 'Sets up the database connection in settings.local.php file if either --db-url option or set of specific --db-* options are provided.')]
    #[CLI\Option(name: 'code', description: 'Import code.')]
    #[CLI\Option(name: 'code-source-path', description: 'Import code from specified directory. Has higher priority over "path" argument.')]
    #[CLI\Option(name: 'files', description: 'Import Drupal files.')]
    #[CLI\Option(name: 'files-source-path', description: 'Import Drupal files from specified directory. Has higher priority over "path" argument.')]
    #[CLI\Option(name: 'files-destination-relative-path', description: 'Import Drupal files into specified directory relative to Composer root.')]
    #[CLI\Option(name: 'db', description: 'Import database.')]
    #[CLI\Option(name: 'db-source-path', description: 'Import database from specified dump file. Has higher priority over "path" argument.')]
    #[CLI\Option(name: 'db-name', description: 'Destination database name.')]
    #[CLI\Option(name: 'db-port', description: 'Destination database port.')]
    #[CLI\Option(name: 'db-host', description: 'Destination database host.')]
    #[CLI\Option(name: 'db-user', description: 'Destination database user.')]
    #[CLI\Option(name: 'db-password', description: 'Destination database user password.')]
    #[CLI\Option(name: 'db-prefix', description: 'Destination database prefix.')]
    #[CLI\Option(name: 'db-driver', description: 'Destination database driver.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz', description: 'Restore the site from /path/to/archive.tar.gz archive file.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --destination-path=/path/to/restore', description: 'Restore the site from /path/to/archive.tar.gz archive file into /path/to/restore directory.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --code --destination-path=/path/to/restore', description: 'Restore the code from /path/to/archive.tar.gz archive file into /path/to/restore directory.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --code-source-path=/code/source/path', description: 'Restore database and Drupal files from /path/to/archive.tar.gz archive file and the code from /code/source/path directory.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --files --destination-path=/path/to/restore', description: 'Restore the Drupal files from /path/to/archive.tar.gz archive file into /path/to/restore directory')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --files-source-path=/files/source/path', description: 'Restore code and database from /path/to/archive.tar.gz archive file and the Drupal files from /files/source/path directory.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --files-destination-relative-path=web/site/foo-bar/files', description: 'Restore the Drupal files from /path/to/archive.tar.gz archive file into web/site/foo-bar/files site\'s subdirectory.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --db', description: 'Restore the database from /path/to/archive.tar.gz archive file.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --db-source-path=/path/to/database.sql', description: 'Restore code and Drupal files from /path/to/archive.tar.gz archive file and the database from /path/to/database.sql dump file.')]
    #[CLI\Usage(name: 'drush archive:restore /path/to/archive.tar.gz --db-url=mysql://user:password@localhost/database_name --destination-path=/path/to/restore', description: 'Restore code, database and Drupal files from /path/to/archive.tar.gz archive file into /path/to/restore directory using database URL.')]
    #[CLI\OptionsetTableSelection]
    #[CLI\OptionsetSql]
    #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
    #[CLI\ValidatePhpExtensions(extensions: ['Phar'])]
    public function restore(
        string $path = null,
        ?string $site = null,
        array $options = [
            'destination-path' => null,
            'overwrite' => false,
            'site-subdir' => self::SITE_SUBDIR,
            'setup-database-connection' => true,
            'code' => false,
            'code-source-path' => null,
            'files' => false,
            'files-source-path' => null,
            'files-destination-relative-path' => null,
            'db' => false,
            'db-source-path' => null,
            'db-driver' => 'mysql',
            'db-port' => null,
            'db-host' => null,
            'db-name' => null,
            'db-user' => null,
            'db-password' => null,
            'db-prefix' => null,
        ]
    ): void {
        $siteAlias = $this->getSiteAlias($site);
        if (!$siteAlias->isLocal()) {
            throw new Exception(
                dt(
                    'Could not restore archive !path into site !site: restoring an archive into a local site is not supported.',
                    ['!path' => $path, '!site' => $site]
                )
            );
        }

        if (!$options['code'] && !$options['files'] && !$options['db']) {
            $options['code'] = $options['files'] = $options['db'] = true;
        }

        if (($options['code'] || $options['files']) && !self::programExists('rsync')) {
            throw new Exception(
                dt('Could not restore the code or the Drupal files: "rsync" program not found')
            );
        }

        $this->filesystem = new Filesystem();
        $extractDir = $this->getExtractDir($path);

        foreach (['code' => 'code', 'db' => 'database', 'files' => 'files'] as $component => $label) {
            if (!$options[$component]) {
                continue;
            }

            // Validate requested components have sources.
            if (null === $extractDir && null === $options[$component . '-source-path']) {
                throw new Exception(
                    dt(
                        'Missing either "path" input or "!component_path" option for the !label component.',
                        [
                            '!component' => $component,
                            '!label' => $label,
                        ]
                    )
                );
            }
        }

        if ($options['destination-path']) {
            $this->destinationPath = $options['destination-path'];
        }

        // If the destination path was not specified, extract over the current site
        if (!$this->destinationPath) {
            $this->destinationPath = $this->bootstrapManager->getComposerRoot();
        }

        // If there isn't a current site either, then extract to the cwd
        if (!$this->destinationPath) {
            $siteDirName = basename(basename($path, '.tgz'), 'tar.gz');
            $this->destinationPath = Path::join(getcwd(), $siteDirName);
        }

        if ($options['code'] && is_dir($this->destinationPath)) {
            if (!$options['overwrite']) {
                throw new Exception(
                    dt('Destination path !path already exists (use "--overwrite" option).', ['!path' => $this->destinationPath])
                );
            }

            if (
                !$this->io()->confirm(
                    dt(
                        'Destination path !path already exists. Are you sure you want to delete !path directory before restoring the archive into it?',
                        [
                            '!path' => $this->destinationPath,
                        ]
                    )
                )
            ) {
                throw new UserAbortException();
            }

            // Remove destination if --overwrite option is set.
            $this->filesystem->remove($this->destinationPath);
        }

        // Create the destination if it does not already exist
        if (!is_dir($this->destinationPath) && !mkdir($this->destinationPath)) {
            throw new Exception(dt('Failed creating destination directory "!destination"', ['!destination' => $this->destinationPath]));
        }

        $this->destinationPath = realpath($this->destinationPath);

        if ($options['code']) {
            $codeComponentPath = $options['code-source-path'] ?? Path::join($extractDir, self::COMPONENT_CODE);
            $this->importCode($codeComponentPath);
        }

        if ($options['files']) {
            $filesComponentPath = $options['files-source-path'] ?? Path::join($extractDir, self::COMPONENT_FILES);
            $this->importFiles($filesComponentPath, $options);
        }

        if ($options['db']) {
            $databaseComponentPath = $options['db-source-path'] ?? Path::join($extractDir, self::COMPONENT_DATABASE, self::SQL_DUMP_FILE_NAME);
            $this->importDatabase($databaseComponentPath, $options);
        }

        $this->logger()->info(dt('Done!'));
    }

    /**
     * Extracts the archive.
     *
     * @param string|null $path
     *   The path to the archive file.
     *
     * @return string|null
     *
     * @throws \Exception
     */
    protected function getExtractDir(?string $path): ?string
    {
        if (null === $path) {
            return null;
        }

        if (is_dir($path)) {
            return $path;
        }

        $this->logger()->info('Extracting the archive...');

        if (!is_file($path)) {
            throw new Exception(dt('File !path is not found.', ['!path' => $path]));
        }

        if (!preg_match('/\.tar\.gz$/', $path) && !preg_match('/\.tgz$/', $path)) {
            throw new Exception(dt('File !path is not a *.tar.gz file.', ['!path' => $path]));
        }

        ['filename' => $archiveFileName] = pathinfo($path);
        $archiveFileName = str_replace('.tar', '', $archiveFileName);

        $extractDir = Path::join(FsUtils::tmpDir(), $archiveFileName);
        $this->filesystem->mkdir($extractDir);

        $archive = new PharData($path);
        $archive->extractTo($extractDir);

        $this->logger()->info(dt('The archive successfully extracted into !path', ['!path' => $extractDir]));

        return $extractDir;
    }

    /**
     * Imports the code to the site.
     *
     * @param string $source
     *   The path to the code files directory.
     *
     * @throws \Exception
     */
    protected function importCode(string $source): void
    {
        $this->logger()->info('Importing code...');

        if (!is_dir($source)) {
            throw new Exception(dt('Directory !path not found.', ['!path' => $source]));
        }

        $this->rsyncFiles($source, $this->getDestinationPath());

        $composerJsonPath = Path::join($this->getDestinationPath(), 'composer.json');
        if (is_file($composerJsonPath)) {
            $this->logger()->success(
                dt('composer.json is found (!path), install Composer dependencies with composer install.'),
                ['!path' => $composerJsonPath]
            );
        }
    }

    /**
     * Imports Drupal files to the site.
     *
     * @param string $source
     *   The path to the source directory.
     * @param array $options
     *   The options.
     *
     * @throws \Exception
     */
    protected function importFiles(string $source, array $options): void
    {
        $this->logger()->info('Importing files...');

        if (!is_dir($source)) {
            throw new Exception(dt('The source directory !path not found for files.', ['!path' => $source]));
        }

        $destinationAbsolute = $this->fileImportAbsolutePath($options['files-destination-relative-path']);

        if (
            is_dir($destinationAbsolute) &&
            (!$options['code'] || !$options['overwrite']) &&
            !$this->io()->confirm(
                dt(
                    'Destination Drupal files path !path already exists. Are you sure you want restore Drupal files archive into it?',
                    [
                        '!path' => $destinationAbsolute,
                    ]
                )
            )
        ) {
            throw new UserAbortException();
        }

        $this->filesystem->mkdir($destinationAbsolute);
        $this->rsyncFiles($source, $destinationAbsolute);
    }

    /**
     * Determines the path where files should be extracted.
     *
     * @param null|string $destinationRelative
     *   The relative path to the Drupal files directory.
     *
     * @return string
     *   The absolute path to the Drupal files directory.
     *
     * @throws \Exception
     */
    protected function fileImportAbsolutePath(?string $destinationRelative): string
    {
        // If the user specified the path to the files directory, use that.
        if ($destinationRelative) {
            return Path::join($this->getDestinationPath(), $destinationRelative);
        }

        // If we are extracting over an existing site, query Drupal to get the files path
        $path = $this->bootstrapManager->getComposerRoot();
        if (!empty($path)) {
            try {
                $this->bootstrapManager->doBootstrap(DrupalBootLevels::FULL);
                return Drupal::service('file_system')->realpath('public://');
            } catch (Throwable $t) {
                $this->logger()->warning('Could not bootstrap Drupal site at destination to determine file path');
            }
        }

        // Find the Drupal root for the archived code, and assume sites/default/files.
        $drupalRootPath = $this->getDrupalRootPath();
        if ($drupalRootPath) {
            return Path::join($drupalRootPath, 'sites/default/files');
        }

        throw new Exception(
            dt(
                'Can\'t detect relative path for Drupal files for destination "!destination": missing --files-destination-relative-path option.',
                ['!destination' => $this->getDestinationPath()]
            )
        );
    }

    /**
     * Returns the absolute path to Drupal root.
     *
     * @return string|null
     */
    protected function getDrupalRootPath(): ?string
    {
        $composerRoot = $this->getDestinationPath();
        $drupalFinder = new DrupalFinder();
        if (!$drupalFinder->locateRoot($composerRoot)) {
            return null;
        }

        return $drupalFinder->getDrupalRoot();
    }

    /**
     * Returns the destination path.
     *
     * @return string
     */
    protected function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    /**
     * Returns SiteAlias object by the site alias name.
     *
     * @param string|null $site
     *   The site alias.
     *
     * @return SiteAlias
     *
     * @throws \Exception
     */
    protected function getSiteAlias(?string $site): SiteAlias
    {
        $pathEvaluator = new BackendPathEvaluator();
        /** @var SiteAliasManager $manager */
        if (null !== $site) {
            $site .= ':%root';
        }
        $evaluatedPath = HostPath::create($this->siteAliasManager, $site);
        $pathEvaluator->evaluate($evaluatedPath);

        return $evaluatedPath->getSiteAlias();
    }

    /**
     * Copies files from the source to the destination.
     *
     * @param string $source
     *   The source path.
     * @param string $destination
     *   The destination path.
     *
     * @throws \Exception
     */
    protected function rsyncFiles(string $source, string $destination): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $destination = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (
            !$this->io()->confirm(
                dt(
                    'Are you sure you want to sync files from "!source" to "!destination"?',
                    [
                        '!source' => $source,
                        '!destination' => $destination,
                    ]
                )
            )
        ) {
            throw new UserAbortException();
        }

        if (!is_dir($source)) {
            throw new Exception(dt('The source directory !path not found.', ['!path' => $source]));
        }

        $this->logger()->info(
            dt(
                'Copying files from "!source" to "!destination"...',
                [
                    '!source' => $source,
                    '!destination' => $destination,
                ]
            )
        );

        $options[] = '-akz';
        if ($this->output()->isVerbose()) {
            $options[] = '--stats';
            $options[] = '--progress';
            $options[] = '-v';
        }

        $command = sprintf(
            'rsync %s %s %s',
            implode(' ', $options),
            $source,
            $destination
        );

        /** @var ProcessBase $process */
        $process = $this->processManager()->shell($command);
        $process->run($process->showRealtime());
        if ($process->isSuccessful()) {
            return;
        }

        throw new Exception(
            dt(
                'Failed to copy files from !source to !destination: !error',
                [
                    '!source' => $source,
                    '!destination' => $destination,
                    '!error' => $process->getErrorOutput(),
                ]
            )
        );
    }

    /**
     * Imports the database dump to the site.
     *
     * @param string $databaseDumpPath
     *   The path to the database dump file.
     * @param array $options
     *   The command options.
     *
     * @throws UserAbortException
     * @throws \Exception
     */
    protected function importDatabase(string $databaseDumpPath, array $options): void
    {
        $this->logger()->info('Importing database...');

        if (!is_file($databaseDumpPath)) {
            throw new Exception(dt('Database dump file !path not found.', ['!path' => $databaseDumpPath]));
        }

        $sqlOptions = [];
        if (isset($options['db-url'])) {
            $sqlOptions = ['db-url' => $options['db-url']];
        } elseif ($options['db-name']) {
            $connection = [
                'driver' => $options['db-driver'],
                'port' => $options['db-port'],
                'prefix' => $options['db-prefix'],
                'host' => $options['db-host'],
                'database' => $options['db-name'],
                'username' => $options['db-user'],
                'password' => $options['db-password'],
            ];

            $sqlOptions = [
                'databases' => [
                    'default' => [
                        'default' => $connection,
                    ],
                ],
            ];
        } elseif ($options['destination-path']) {
            throw new Exception('Database connection settings are required if --destination-path option is provided');
        } else {
            $this->bootstrapManager->doBootstrap(DrupalBootLevels::CONFIGURATION);
        }

        try {
            $sql = SqlBase::create($sqlOptions);
            $isDbExist = $sql->dbExists();
            $databaseSpec = $sql->getDbSpec();
        } catch (Throwable $t) {
            throw new Exception(dt('Failed to get database specification: !error', ['!error' => $t->getMessage()]), $t->getCode(), $t);
        }

        if (
            $isDbExist &&
            !$this->io()->confirm(
                dt(
                    'Are you sure you want to drop the database "!database" (username: !user, password: !password, port: !port, prefix: !prefix) and import the database dump "!path"?',
                    [
                        '!path' => $databaseDumpPath,
                        '!database' => $databaseSpec['database'],
                        '!user' => $databaseSpec['username'],
                        '!password' => isset($databaseSpec['password']) ? '******' : '[not set]',
                        '!port' => $databaseSpec['port'] ?: dt('n/a'),
                        '!prefix' => $databaseSpec['prefix'] ?: dt('n/a'),
                    ]
                )
            )
        ) {
            throw new UserAbortException();
        }

        if ($isDbExist && !$sql->drop($sql->listTablesQuoted())) {
            throw new Exception(
                dt('Failed to drop database !database.', ['!database' => $databaseSpec['database']])
            );
        }
        if (!$isDbExist && !$sql->createdb(true)) {
            throw new Exception(
                dt('Failed to create database !database.', ['!database' => $databaseSpec['database']])
            );
        }

        $sql->setDbSpec($databaseSpec);
        if (!$sql->query('', $databaseDumpPath)) {
            throw new Exception(dt('Database import has failed: !error', ['!error' => $sql->getProcess()->getErrorOutput()]));
        }

        if ($sqlOptions) {
            // Setup settings.local.php file since database connection settings provided via options.
            $this->setupLocalSettingsPhp($databaseSpec, $options);
        }
    }

    /**
     * Sets up settings.local.php file.
     *
     * 1. Creates settings.php file (a copy of default.settings.php) in the site's subdirectory if not exists;
     * 2. Makes sure settings.php has an active (i.e. uncommented) "include settings.local.php file" directive;
     * 3. Updates settings.local.php file to include database connection settings provided via command's options.
     *
     * @param array $databaseSpec
     *   The database connection specification.
     * @param array $options
     *   The command options.
     *
     * @throws Exception
     */
    private function setupLocalSettingsPhp(array $databaseSpec, array $options): void
    {
        $drupalRootPath = $this->getDrupalRootPath();
        if (!$drupalRootPath) {
            throw new Exception(
                dt('Failed to detect Drupal docroot path for path !path', ['!path' => $this->getDestinationPath()])
            );
        }

        $siteSubdir = Path::join($drupalRootPath, 'sites', $options['site-subdir']);
        $this->filesystem->mkdir($siteSubdir);

        $settingsPhpPath = Path::join($siteSubdir, 'settings.php');
        if (!is_file($settingsPhpPath)) {
            // Create settings.php file as a copy of default.settings.php file.
            $defaultSettingsPath = Path::join($drupalRootPath, 'sites', self::SITE_SUBDIR, 'default.settings.php');
            $this->logger()->info('Copying !from to !to...', ['!from' => $defaultSettingsPath, '!to' => $settingsPhpPath]);
            copy(
                $defaultSettingsPath,
                $settingsPhpPath
            );
        }

        $drushSignature = '// Added by Drush archive:restore command.';

        // Make sure settings.php has an active (i.e. uncommented) "include settings.local.php file" directive.
        $settingsPhpContent = file_get_contents($settingsPhpPath);
        if (preg_match('/\# if \(file_exists.+?settings\.local\.php.+?\# }/ms', $settingsPhpContent, $matches)) {
            $uncommentedLocalSettingsInclude = $drushSignature . "\n" . str_replace('# ', '', $matches[0]);

            $settingsPhpIncludeLocalContent = str_replace(
                $matches[0],
                $uncommentedLocalSettingsInclude,
                $settingsPhpContent
            );

            $this->logger()->info(sprintf('Updating %s to include settings.local.php file...', $settingsPhpPath));
            if (!file_put_contents($settingsPhpPath, $settingsPhpIncludeLocalContent)) {
                throw new Exception(dt('Failed to save updated !path', ['!path' => $settingsPhpPath]));
            }
        }

        $databaseSpecExported = var_export($databaseSpec, true);
        $settingsLocalPhpPath = Path::join($siteSubdir, 'settings.local.php');
        $settingsLocalPhpDatabaseConnection = <<<EOT

$drushSignature
\$databases['default']['default'] = $databaseSpecExported;

EOT;

        if (!is_file($settingsLocalPhpPath)) {
            // Create settings.local.php file with database connection settings provided via command's options.
            $this->logger()->info('Creating !path with database connection settings...', ['!path' => $settingsLocalPhpPath]);
            $settingsLocalPhpModifiedContent = '<?php' . $settingsLocalPhpDatabaseConnection;
            $this->saveSettingsLocalPhp($settingsLocalPhpPath, $settingsLocalPhpModifiedContent);
            return;
        }

        $settingsLocalPhpContent = file_get_contents($settingsLocalPhpPath);
        if (!str_contains($settingsLocalPhpContent, $drushSignature)) {
            $this->logger()->info('Adding database connection settings to !path...', ['!path' => $settingsLocalPhpPath]);
            $settingsLocalPhpModifiedContent = $settingsLocalPhpContent . $settingsLocalPhpDatabaseConnection;
            $this->saveSettingsLocalPhp($settingsLocalPhpPath, $settingsLocalPhpModifiedContent);
            return;
        }

        $this->logger()->info('Updating database connection settings in !path...', ['!path' => $settingsLocalPhpPath]);
        $settingsLocalPhpModifiedContent = preg_replace(
            '/' . preg_quote($drushSignature, '/') . '.+?\);/ms',
            $settingsLocalPhpDatabaseConnection,
            $settingsLocalPhpContent
        );
        $this->saveSettingsLocalPhp($settingsLocalPhpPath, $settingsLocalPhpModifiedContent);
    }

    /**
     * Saves settings.local.php file with actual database connection settings.
     *
     * @param string $path
     * @param string $content
     * @throws Exception
     */
    private function saveSettingsLocalPhp(string $path, string $content): void
    {
        if (!file_put_contents($path, $content)) {
            throw new Exception(dt('Failed to create or update !path.', ['!path' => $path]));
        }
    }
}
