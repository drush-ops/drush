<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\StreamWrapper\PublicStream;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Drush;
use Drush\Sql\SqlBase;
use Drush\Style\DrushStyle;
use Drush\Utils\FsUtils;
use Exception;
use FilesystemIterator;
use Phar;
use PharData;
use Psr\Log\LoggerInterface;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Traversable;

#[AsCommand(
    name: self::NAME,
    description: 'Backup your code, files, and database into a single file.',
    aliases: ['ard']
)]
#[CLI\ValidatePhpExtensions(extensions: ['Phar'])]
#[CLI\OptionsetTableSelection]
#[CLI\OptionsetSql]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class ArchiveDumpCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'archive:dump';
    private Filesystem $filesystem;
    private string $archiveDir;
    private string $drupalFilesDir;

    public function __construct(
        protected readonly BootstrapManager $bootstrapManager,
        protected readonly DrushConfig $drushConfig,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    private const string COMPONENT_CODE = 'code';
    private const string COMPONENT_FILES = 'files';
    private const string COMPONENT_DATABASE = 'database';
    private const string SQL_DUMP_FILE_NAME = 'database.sql';
    private const string ARCHIVES_DIR_NAME = 'archives';
    private const string ARCHIVE_FILE_NAME = 'archive.tar';
    private const string MANIFEST_FORMAT_VERSION = '1.0';
    private const string MANIFEST_FILE_NAME = 'MANIFEST.yml';

    protected function configure(): void
    {
        $this
            ->setHelp("The following paths would be excluded from a code archive:\n\n - .git\n - vendor\n - [docroot]/sites/@/settings.@.php\n - Drupal files directory\n - Composer packages installed paths (\`composer info --path --format=json\`)\n\nThe following directories would be excluded from a file archive:\n\n- css\n- js\n- styles\n- php")
            ->addOption('destination', null, InputOption::VALUE_REQUIRED, 'The full path and filename in which the archive should be stored. Any relative path will be calculated from Drupal root (usually <info>web</info> for drupal/recommended-project projects). If omitted, it will be saved to the configured temp directory.')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite destination file if exists.')
            ->addOption('code', null, InputOption::VALUE_NONE, 'Archive codebase.')
            ->addOption('convert-symlinks', null, InputOption::VALUE_NONE, 'Replace all symlinks with copies of the files/directories that they point to. Default is to only convert symlinks that point outside the project root.')
            ->addOption('exclude-code-paths', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of paths (or regular expressions matching paths) to exclude from the code archive.')
            ->addOption('extra-dump', null, InputOption::VALUE_REQUIRED, 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')
            ->addOption('files', null, InputOption::VALUE_NONE, 'Archive Drupal files.')
            ->addOption('db', null, InputOption::VALUE_NONE, 'Archive database SQL dump.')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Describe the archive contents.')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Add tags to the archive manifest. Delimit several by commas.')
            ->addOption('generator', null, InputOption::VALUE_REQUIRED, 'The generator name to store in the MANIFEST.yml file. The default is "Drush archive-dump".')
            ->addOption('generatorversion', null, InputOption::VALUE_REQUIRED, 'The generator version number to store in the MANIFEST file. The default is Drush version.')
            ->addUsage('archive:dump --destination=/path/to/archive.tar.gz')
            ->addUsage('archive:dump --destination=/path/to/archive.tar.gz --overwrite')
            ->addUsage('archive:dump --code --destination=/path/to/archive.tar.gz')
            ->addUsage('archive:dump --exclude-code-paths=foo_bar.txt,web/sites/.+/settings.php --destination=/path/to/archive.tar.gz')
            ->addUsage('archive:dump --extra-dump=--no-data --destination=/path/to/archive.tar.gz')
            ->addUsage('archive:dump --files --destination=/path/to/archive.tar.gz')
            ->addUsage('archive:dump --database --destination=/path/to/archive.tar.gz');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $options = $input->getOptions();

        $archivePath = $this->dump($options, $io);
        $output->writeln($archivePath);
        $io->success('Archive created');

        return Command::SUCCESS;
    }

    /**
     * Backup your code, files, and database into a single file.
     */
    public function dump(array $options, DrushStyle $io): string
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::CONFIGURATION);

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
            throw new Exception('Nothing to archive');
        }

        $this->logger->info('Creating archive...');
        $archivePath = Path::join(dirname($this->archiveDir), self::ARCHIVE_FILE_NAME);

        stream_wrapper_restore('phar');
        $archive = new PharData($archivePath);

        $this->createManifestFile($options);

        $archive->buildFromDirectory($this->archiveDir);

        $this->logger->info('Compressing archive...');
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
                    'The destination file already exists. Use "--overwrite" option for overwriting an existing file.'
                );
            }

            $this->filesystem->remove($options['destination']);
        }

        $this->logger->info(
            'Moving archive file from {from} to {to}',
            ['from' => $archivePath, 'to' => $options['destination']]
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
        $this->logger->info('Creating {manifest} file...', ['manifest' => self::MANIFEST_FILE_NAME]);
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
        $this->logger->info('Converting symlinks...');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->archiveDir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (
                $file->isLink() && ($convert_symlinks || !str_starts_with(
                    $file->getLinkTarget(),
                    $this->archiveDir
                ))
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
                    $targetIterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $target,
                            FilesystemIterator::SKIP_DOTS
                        ),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($targetIterator as $item) {
                        if ($item->isDir()) {
                            mkdir($path . DIRECTORY_SEPARATOR . $targetIterator->getSubPathname());
                        } else {
                            copy(
                                $item->getPathname(),
                                $path . DIRECTORY_SEPARATOR . $targetIterator->getSubPathname()
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
     *
     * @throws \Exception
     */
    private function getComposerRoot(): string
    {
        $composerRoot = $this->bootstrapManager->getComposerRoot();
        if (!$composerRoot) {
            throw new Exception('Path to Composer root is empty.');
        }

        return $composerRoot;
    }

    /**
     * Returns site's docroot path.
     *
     *
     * @throws \Exception
     */
    private function getRoot(): string
    {
        $root = $this->bootstrapManager->getRoot();
        if (!$root) {
            throw new Exception('Path to Drupal docroot is empty.');
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

        $this->logger->info(
            'Copying code files from {from_path} to {to_path}...',
            ['from_path' => $codePath, 'to_path' => $codeArchiveComponentPath]
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
        $installedPackages = array_filter($installedPackages, fn($dependency): bool => !empty($dependency['source']));
        $installedPackagesPaths = array_filter(array_column($installedPackages, 'path'));
        $installedPackagesRelativePaths = array_map(
            fn($path): string => ltrim(str_replace([$this->getComposerRoot()], '', $path), '/'),
            $installedPackagesPaths
        );
        $installedPackagesRelativePaths = array_unique(
            array_filter(
                $installedPackagesRelativePaths,
                fn($path): bool => '' !== $path && !str_starts_with($path, 'vendor')
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
        $this->logger->info(
            'Copying Drupal files from {from_path} to {to_path}...',
            ['from_path' => $drupalFilesPath, 'to_path' => $drupalFilesArchiveComponentPath]
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
     * Returns the full path to Drupal files directory.
     *
     * @throws \Exception
     */
    private function getDrupalFilesDir(): string
    {
        if (isset($this->drupalFilesDir)) {
            return $this->drupalFilesDir;
        }

        $this->bootstrapManager->doBootstrap(DrupalBootLevels::FULL);
        $drupalFilesPath = Path::join($this->getRoot(), PublicStream::basePath());
        if (!$drupalFilesPath) {
            throw new Exception('Path to Drupal files is empty.');
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
                            $this->logger->info(
                                'Path excluded ({exclude}): {path}',
                                ['exclude' => $exclude, 'path' => $localFileName]
                            );

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
        $this->logger->info('Creating database SQL dump file...');
        $databaseArchiveDir = Path::join($this->archiveDir, self::COMPONENT_DATABASE);
        $this->filesystem->mkdir($databaseArchiveDir);

        $options['result-file'] = Path::join($databaseArchiveDir, self::SQL_DUMP_FILE_NAME);
        $sql = SqlBase::create($options);
        if (false === $sql->dump()) {
            throw new Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        return $databaseArchiveDir;
    }

    /**
     * Returns the list of regular expressions to match paths.
     *
     * @param array $paths
     *   The list of paths to match.
     */
    private function getRegexpsForPaths(array $paths): array
    {
        return array_map(
            fn($path): string => sprintf('#^%s$#', trim($path)),
            $paths
        );
    }

    /**
     * Returns docroot directory name with trailing escaped slash for a "web" docroot site for use in regular expressions, otherwise - empty string.
     *
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
                sprintf(
                    'Found database connection settings in %s. It is risky to include them to the archive. Please move the database connection settings into a setting.*.php file or exclude them from the archive with "--exclude-code-paths=%s".',
                    $localFileName,
                    $localFileName
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
