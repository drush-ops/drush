<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal;
use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Traversable;

final class ArchiveDumpCommands extends DrushCommands
{
    const DUMP = 'archive:dump';
    private Filesystem $filesystem;
    private string $archiveDir;
    private string $drupalFilesDir;

    private const COMPONENT_CODE = 'code';

    private const COMPONENT_FILES = 'files';

    private const COMPONENT_DATABASE = 'database';
    private const SQL_DUMP_FILE_NAME = 'database.sql';

    private const ARCHIVES_DIR_NAME = 'archives';
    private const ARCHIVE_FILE_NAME = 'archive.tar';
    private const MANIFEST_FORMAT_VERSION = '1.0';
    private const MANIFEST_FILE_NAME = 'MANIFEST.yml';

    /**
     * Backup your code, files, and database into a single file.
     *
     * The following paths would be excluded from a code archive:
     *
     *  - .git
     *  - vendor
     *  - [docroot]/sites/@/settings.@.php
     *  - Drupal files directory
     *  - Composer packages installed paths (`composer info --path --format=json`)
     *
     * The following directories would be excluded from a file archive:
     *
     * - css
     * - js
     * - styles
     * - php
     */
    #[CLI\Command(name: self::DUMP, aliases: ['ard'])]
    #[CLI\ValidatePhpExtensions(extensions: ['Phar'])]
    #[CLI\Option(name: 'destination', description: 'The full path and filename in which the archive should be stored. Any relative path will be calculated from Drupal root (usually <info>web</info> for drupal/recommended-project projects). If omitted, it will be saved to the configured temp directory.')]
    #[CLI\Option(name: 'overwrite', description: 'Overwrite destination file if exists.')]
    #[CLI\Option(name: 'code', description: 'Archive codebase.')]
    #[CLI\Option(name: 'convert-symlinks', description: 'Replace all symlinks with copies of the files/directories that they point to. Default is to only convert symlinks that point outside the project root.')]
    #[CLI\Option(name: 'exclude-code-paths', description: 'Comma-separated list of paths (or regular expressions matching paths) to exclude from the code archive.')]
    #[CLI\Option(name: 'extra-dump', description: 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')]
    #[CLI\Option(name: 'files', description: 'Archive Drupal files.')]
    #[CLI\Option(name: 'db', description: 'Archive database SQL dump.')]
    #[CLI\Option(name: 'description', description: 'Describe the archive contents.')]
    #[CLI\Option(name: 'tags', description: 'Add tags to the archive manifest. Delimit several by commas.')]
    #[CLI\Option(name: 'generator', description: 'The generator name to store in the MANIFEST.yml file. The default is "Drush archive-dump".')]
    #[CLI\Option(name: 'generatorversion', description: 'The generator version number to store in the MANIFEST file. The default is Drush version.')]
    #[CLI\Usage(name: 'drush archive:dump', description: 'Create a site archive file in a temporary directory containing code, database and Drupal files.')]
    #[CLI\Usage(name: 'drush archive:dump --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz file containing code, database and Drupal files.')]
    #[CLI\Usage(name: 'drush archive:dump --destination=/path/to/archive.tar.gz --overwrite', description: 'Create (or overwrite if exists) /path/to/archive.tar.gz file containing code, database and Drupal files.')]
    #[CLI\Usage(name: 'drush archive:dump --code --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz file containing the code only.')]
    #[CLI\Usage(name: 'drush archive:dump --exclude-code-paths=foo_bar.txt,web/sites/.+/settings.php --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz file containing code, database and Drupal files but excluding foo_bar.txt file and settings.php files if found in web/sites/* subdirectories.')]
    #[CLI\Usage(name: 'drush archive:dump --extra-dump=--no-data --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz file and pass extra option to <info>mysqldump</info> command.')]
    #[CLI\Usage(name: 'drush archive:dump --files --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz file containing the Drupal files only.')]
    #[CLI\Usage(name: 'drush archive:dump --database --destination=/path/to/archive.tar.gz', description: 'Create /path/to/archive.tar.gz archive file containing the database dump only.')]
    #[CLI\OptionsetTableSelection]
    #[CLI\OptionsetSql]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    public function dump(array $options = [
        'code' => false,
        'files' => false,
        'db' => false,
        'destination' => InputOption::VALUE_REQUIRED,
        'overwrite' => false,
        'description' => InputOption::VALUE_REQUIRED,
        'tags' => InputOption::VALUE_REQUIRED,
        'generator' => InputOption::VALUE_REQUIRED,
        'generatorversion' => InputOption::VALUE_REQUIRED,
        'exclude-code-paths' => InputOption::VALUE_REQUIRED,
        'extra-dump' => self::REQ,
        'convert-symlinks' => false,
    ]): string
    {
        $this->prepareArchiveDir();

        if (!$options['code'] && !$options['files'] && !$options['db']) {
            $options['code'] = $options['files'] = $options['db'] = true;
        }

        $components = [];

        if ($options['code']) {
            $components[] = [
                'name' => self::COMPONENT_CODE,
                'path' => $this->getCodeComponentPath($options),
            ];
        }

        if ($options['files']) {
            $components[] = [
                'name' => self::COMPONENT_FILES,
                'path' => $this->getDrupalFilesComponentPath(),
            ];
        }

        if ($options['db']) {
            $components[] = [
                'name' => self::COMPONENT_DATABASE,
                'path' => $this->getDatabaseComponentPath($options),
            ];
        }

        $this->convertSymlinks($options['convert-symlinks']);

        return $this->createArchiveFile($components, $options);
    }

    /**
     * Creates a temporary directory for the archive.
     *
     * @throws \Exception
     */
    protected function prepareArchiveDir(): void
    {
        $this->filesystem = new Filesystem();
        $this->archiveDir = FsUtils::tmpDir(self::ARCHIVES_DIR_NAME);
    }

    /**
     * Creates the archive file and returns the absolute path.
     *
     * @param $archiveComponents
     *   The list of components (files) to include into the archive file.
     * @param $options
     *   The command options.
     *
     * @return string
     *   The full path to archive file.
     *
     * @throws \Exception
     */
    private function createArchiveFile(array $archiveComponents, array $options): string
    {
        if (!$archiveComponents) {
            throw new Exception(dt('Nothing to archive'));
        }

        $this->logger()->info(dt('Creating archive...'));
        $archivePath = Path::join(dirname($this->archiveDir), self::ARCHIVE_FILE_NAME);

        stream_wrapper_restore('phar');
        $archive = new PharData($archivePath);

        $this->createManifestFile($options);

        $archive->buildFromDirectory($this->archiveDir);

        $this->logger()->info(dt('Compressing archive...'));
        $this->filesystem->remove($archivePath . '.gz');
        $archive->compress(Phar::GZ);

        unset($archive);
        Phar::unlinkArchive($archivePath);
        $archivePath .= '.gz';

        if (!$options['destination']) {
            return $archivePath;
        }

        $options['destination'] = $this->destinationCleanup($options['destination']);

        if ($this->filesystem->exists($options['destination'])) {
            if (!$options['overwrite']) {
                throw new Exception(
                    dt('The destination file already exists. Use "--overwrite" option for overwriting an existing file.')
                );
            }

            $this->filesystem->remove($options['destination']);
        }

        $this->logger()->info(
            dt(
                'Moving archive file from !from to !to',
                ['!from' => $archivePath, '!to' => $options['destination']]
            )
        );
        $this->filesystem->rename($archivePath, $options['destination']);

        return realpath($options['destination']);
    }

    /**
     * Creates the MANIFEST file.
     *
     * @param array $options
     *   The command options.
     *
     * @throws \Exception
     */
    private function createManifestFile(array $options): void
    {
        $this->logger()->info(dt('Creating !manifest file...', ['!manifest' => self::MANIFEST_FILE_NAME]));
        $manifest = [
            'datestamp' => time(),
            'formatversion' => self::MANIFEST_FORMAT_VERSION,
            'components' => [
                self::COMPONENT_CODE => $options['code'],
                self::COMPONENT_FILES => $options['files'],
                self::COMPONENT_DATABASE => $options['db'],
            ],
            'description' => $options['description'] ?? null,
            'tags' => $options['tags'] ?? null,
            'generator' => $options['generator'] ?? 'Drush archive:dump',
            'generatorversion' => $options['generatorversion'] ?? Drush::getVersion(),
        ];
        $manifestFilePath = Path::join($this->archiveDir, self::MANIFEST_FILE_NAME);
        file_put_contents(
            $manifestFilePath,
            Yaml::dump($manifest)
        );
    }

    /**
     * Converts symlinks to the linked files/folders for an archive.
     *
     * @param bool $convert_symlinks
     *  Whether to convert all symlinks.
     *
     */
    public function convertSymlinks(
        bool $convert_symlinks,
    ): void {
        // If symlinks are disabled, convert symlinks to full content.
        $this->logger()->info(dt('Converting symlinks...'));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->archiveDir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (
                $file->isLink() && ($convert_symlinks || strpos(
                    $file->getLinkTarget(),
                    $this->archiveDir
                ) !== 0)
            ) {
                $target = readlink($file->getPathname());

                if (is_file($target)) {
                    $content = file_get_contents($target);
                    unlink($file->getPathname());
                    file_put_contents($file->getPathname(), $content);
                } elseif (is_dir($target)) {
                    $path = $file->getPathname();
                    unlink($path);
                    mkdir($path, 0755);
                    foreach (
                        $iterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator(
                                $target,
                                \RecursiveDirectoryIterator::SKIP_DOTS
                            ),
                            \RecursiveIteratorIterator::SELF_FIRST
                        ) as $item
                    ) {
                        if ($item->isDir()) {
                            mkdir($path . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
                        } else {
                            copy(
                                $item->getPathname(),
                                $path . DIRECTORY_SEPARATOR . $iterator->getSubPathname()
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns TRUE if the site is a "web" docroot site.
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function isWebRootSite(): bool
    {
        return $this->getComposerRoot() !== $this->getRoot();
    }

    /**
     * Returns site's docroot name.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getComposerRoot(): string
    {
        $bootstrapManager = Drush::bootstrapManager();
        $composerRoot = $bootstrapManager->getComposerRoot();
        if (!$composerRoot) {
            throw new Exception(dt('Path to Composer root is empty.'));
        }

        return $composerRoot;
    }

    /**
     * Returns site's docroot path.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getRoot(): string
    {
        $bootstrapManager = Drush::bootstrapManager();
        $root = $bootstrapManager->getRoot();
        if (!$root) {
            throw new Exception(dt('Path to Drupal docroot is empty.'));
        }

        return $root;
    }

    /**
     * Creates "code" archive component and returns the absolute path.
     *
     * @param array $options
     *  The command options.
     *
     * @return string
     *  The full path to the code archive component directory.
     *
     * @throws \Exception
     */
    private function getCodeComponentPath(array $options): string
    {
        $codePath = $this->getComposerRoot();
        $codeArchiveComponentPath = Path::join($this->archiveDir, self::COMPONENT_CODE);

        $this->logger()->info(
            dt(
                'Copying code files from !from_path to !to_path...',
                ['!from_path' => $codePath, '!to_path' => $codeArchiveComponentPath]
            )
        );

        $excludes = $options['exclude-code-paths']
            ? $this->getRegexpsForPaths(explode(',', $options['exclude-code-paths']))
            : [];

        $excludeDirs = [
            '.git',
            'vendor',
        ];

        $process = Process::fromShellCommandline(sprintf('composer info --path --format=json --working-dir=%s', $this->getComposerRoot()));
        $process->mustRun();
        $composerInfoRaw = $process->getOutput();
        $installedPackages = json_decode($composerInfoRaw, true)['installed'] ?? [];
        // Remove path projects ('source' is empty for path projects)
        $installedPackages = array_filter($installedPackages, function ($dependency) {
            return !empty($dependency['source']);
        });
        $installedPackagesPaths = array_filter(array_column($installedPackages, 'path'));
        $installedPackagesRelativePaths = array_map(
            fn($path) => ltrim(str_replace([$this->getComposerRoot()], '', $path), '/'),
            $installedPackagesPaths
        );
        $installedPackagesRelativePaths = array_unique(
            array_filter(
                $installedPackagesRelativePaths,
                fn($path) => '' !== $path && !str_starts_with($path, 'vendor')
            )
        );
        $excludeDirs = array_merge($excludeDirs, $installedPackagesRelativePaths);

        if (Path::isBasePath($this->getComposerRoot(), $this->archiveDir)) {
            $excludeDirs[] = Path::makeRelative($this->archiveDir, $this->getComposerRoot());
        }

        $excludes = array_merge(
            $excludes,
            $this->getRegexpsForPaths(
                $excludeDirs
            ),
            $this->getDrupalExcludes()
        );

        $this->filesystem->mirror(
            $codePath,
            $codeArchiveComponentPath,
            $this->getFileIterator($codePath, $excludes)
        );

        return $codeArchiveComponentPath;
    }

    /**
     * Creates "Drupal files" archive component and returns the absolute path.
     *
     * @return string
     *  The full path to the Drupal files archive component directory.
     *
     * @throws \Exception
     */
    private function getDrupalFilesComponentPath(): string
    {
        $drupalFilesPath = $this->getDrupalFilesDir();
        $drupalFilesArchiveComponentPath = Path::join($this->archiveDir, self::COMPONENT_FILES);
        $this->logger()->info(
            dt(
                'Copying Drupal files from !from_path to !to_path...',
                ['!from_path' => $drupalFilesPath, '!to_path' => $drupalFilesArchiveComponentPath]
            )
        );

        $excludes = $this->getRegexpsForPaths([
            'css',
            'js',
            'styles',
            'php',
        ]);

        $this->filesystem->mirror(
            $drupalFilesPath,
            $drupalFilesArchiveComponentPath,
            $this->getFileIterator($drupalFilesPath, $excludes)
        );

        return $drupalFilesArchiveComponentPath;
    }

    /**
     * Returns the path to Drupal files directory.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getDrupalFilesDir(): string
    {
        if (isset($this->drupalFilesDir)) {
            return $this->drupalFilesDir;
        }

        Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL);
        $drupalFilesPath = Path::join($this->getRoot(), PublicStream::basePath());
        if (!$drupalFilesPath) {
            throw new Exception(dt('Path to Drupal files is empty.'));
        }

        return $this->drupalFilesDir = $drupalFilesPath;
    }

    /**
     * Returns file iterator.
     *
     * Excludes paths according to the list of excludes provides.
     * Validates for sensitive data present.
     *
     * @param string $path
     *   Directory.
     * @param array $excludes
     *   The list of file exclude rules (regular expressions).
     *
     * @return \Traversable
     */
    private function getFileIterator(string $path, array $excludes): Traversable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(
                    $path,
                    FilesystemIterator::SKIP_DOTS
                ),
                function ($file) use ($excludes, $path) {
                    $localFileName = str_replace($path, '', (string)$file);
                    $localFileName = str_replace('\\', '/', $localFileName);
                    $localFileName = trim($localFileName, '\/');

                    foreach ($excludes as $exclude) {
                        if (preg_match($exclude, $localFileName)) {
                            $this->logger()->info(dt(
                                'Path excluded (!exclude): !path',
                                ['!exclude' => $exclude, '!path' => $localFileName]
                            ));

                            return false;
                        }
                    }

                    $this->validateSensitiveData((string)$file, $localFileName);

                    return true;
                }
            )
        );
    }

    /**
     * Creates "database" archive component and returns the absolute path.
     *
     * @param array $options
     *   The command options.
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
        $databaseArchiveDir = Path::join($this->archiveDir, self::COMPONENT_DATABASE);
        $this->filesystem->mkdir($databaseArchiveDir);

        $options['result-file'] = Path::join($databaseArchiveDir, self::SQL_DUMP_FILE_NAME);
        $sql = SqlBase::create($options);
        if (false === $sql->dump()) {
            throw new Exception(dt('Unable to dump database. Rerun with --debug to see any error message.'));
        }

        return $databaseArchiveDir;
    }

    /**
     * Returns the list of regular expressions to match paths.
     *
     * @param array $paths
     *   The list of paths to match.
     *
     * @return array
     */
    private function getRegexpsForPaths(array $paths): array
    {
        return array_map(
            fn($path) => sprintf('#^%s$#', trim($path)),
            $paths
        );
    }

    /**
     * Returns docroot directory name with trailing escaped slash for a "web" docroot site for use in regular expressions, otherwise - empty string.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getDocrootRegexpPrefix(): string
    {
        return $this->isWebRootSite() ? basename($this->getRoot()) . '/' : '';
    }

    /**
     * Returns the list of regular expressions to match Drupal files paths and sites/@/settings.@.php files.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getDrupalExcludes(): array
    {
        $excludes = [
            '#^' . $this->getDocrootRegexpPrefix() . 'sites/.+/settings\..+\.php$#',
        ];

        $drupalFilesPath = $this->getDrupalFilesDir();
        $drupalFilesPathRelative = Path::makeRelative($drupalFilesPath, $this->getComposerRoot());
        $excludes[] = '#^' . $drupalFilesPathRelative . '$#';

        return $excludes;
    }

    /**
     * Validates files for sensitive data (database connection).
     *
     * Prevents creating a code archive containing a [docroot]/sites/@/settings.php file with database connection settings
     * defined.
     *
     * @param string $file
     *   The absolute path to the file.
     * @param string $localFileName
     *   The local (project-base) path to the file.
     *
     * @throws \Exception
     */
    private function validateSensitiveData(string $file, string $localFileName): void
    {
        $regexp = '#^' . $this->getDocrootRegexpPrefix() . 'sites/.*/settings\.php$#';
        if (!preg_match($regexp, $localFileName)) {
            return;
        }

        $settingsPhpFileContents = file_get_contents($file);
        $settingsWithoutComments = preg_replace('/\/\*(.*?)\*\/|(\/\/|#)(.*?)$/ms', '', $settingsPhpFileContents);
        $isDatabaseSettingsPresent = preg_match('/\$databases[^;]*=[^;]*(\[|(array[^;]*\())[^;]+(\]|\))[^;]*;/ms', $settingsWithoutComments);
        if ($isDatabaseSettingsPresent) {
            throw new Exception(
                dt(
                    'Found database connection settings in !path. It is risky to include them to the archive. Please move the database connection settings into a setting.*.php file or exclude them from the archive with "--exclude-code-paths=!path".',
                    ['!path' => $localFileName]
                )
            );
        }
    }

    /**
     * Provides basic verification/correction on destination option.
     */
    private function destinationCleanup(string $destination): string
    {
        // User input may be in the wrong format, this performs some basic
        // corrections. The correct format should include a .tar.gz.
        if (!str_ends_with($destination, ".tar.gz")) {
            // If the user provided .tar but not .gz.
            if (str_ends_with($destination, ".tar")) {
                return $destination . ".gz";
            }

            // If neither, the user provided a directory.
            if (str_ends_with($destination, "/")) {
                return $destination . "archive.tar.gz";
            } else {
                return $destination . "/archive.tar.gz";
            }
        }
        return $destination;
    }
}
