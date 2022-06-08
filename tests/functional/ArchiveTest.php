<?php

namespace Unish;

use PharData;
use Symfony\Component\Process\Process;
use Unish\Utils\FSUtils;
use Webmozart\PathUtil\Path;

/**
 * Class ArchiveTest.
 *
 * @group slow
 * @group commands
 * @group archive
 */
class ArchiveTest extends CommandUnishTestCase
{
    use FSUtils;

    /**
     * @var string
     */
    protected string $archivePath;

    /**
     * @var string
     */
    protected string $restorePath;

    /**
     * @var string
     */
    protected string $extractPath;

    /**
     * @var array
     */
    protected array $archiveDumpOptions;

    /**
     * @var array
     */
    protected array $archiveRestoreOptions;

    /**
     * @var array
     */
    protected array $fixtureDatabaseSettings;

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $this->setUpDrupal(1, true);
        $this->archiveDumpOptions = [
            'db' => null,
            'files' => null,
            'code' => null,
            'exclude-code-paths' => 'sut/sites/.+/settings.php,(?!sut|composer\.json|composer\.lock).*',
        ];

        $this->archivePath = Path::join($this->getSandbox(), 'archive.tar.gz');
        $this->drush(
            'archive:dump',
            [],
            array_merge($this->archiveDumpOptions, [
                'destination' => $this->archivePath,
                'overwrite' => null,
            ])
        );
        $actualArchivePath = $this->getOutput();
        $this->assertEquals($this->archivePath, $actualArchivePath);

        $this->restorePath = Path::join($this->getSandbox(), 'restore');
        $this->removeDir($this->restorePath);

        $this->extractPath = Path::join($this->getSandbox(), 'extract');
        $this->removeDir($this->extractPath);
        $archive = new PharData($this->archivePath);
        $archive->extractTo($this->extractPath);

        $this->drush(
            'status',
            [],
            ['format' => 'json']
        );
        $this->fixtureDatabaseSettings = json_decode($this->getOutput(), true);
        $this->fixtureDatabaseSettings['db-name'] = 'archive_dump_restore_test_' .  mt_rand();
        $dbUrlParts = explode(':', self::getDbUrl());
        $this->fixtureDatabaseSettings['db-password'] = substr($dbUrlParts[2], 0, strpos($dbUrlParts[2], '@'));
        $fixtureDbUrl = self::getDbUrl() . '/' . $this->fixtureDatabaseSettings['db-name'];
        $this->backupSettingsPhp();

        $this->archiveRestoreOptions = [
            'destination-path' => $this->restorePath,
            'overwrite' => null,
            'db-url' => $fixtureDbUrl,
        ];
    }

    public function testArchiveDumpCommand(): void
    {
        // Create a file at the destination to confirm that archive:dump
        // will fail without --overwrite in this instance.
        file_put_contents($this->archivePath, "Existing file at destination");

        // Try to overwrite the existing archive with "--destination".
        $this->drush(
            'archive:dump',
            [],
            array_merge($this->archiveDumpOptions, [
                'destination' => $this->archivePath,
            ]),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString('The destination file already exists.', $this->getErrorOutput());

        // Overwrite the existing archive with "--destination" and "--override".
        $this->drush(
            'archive:dump',
            [],
            array_merge($this->archiveDumpOptions, [
                'destination' => $this->archivePath,
                'overwrite' => null,
            ])
        );
        $actualArchivePath = $this->getOutput();
        $this->assertEquals($this->archivePath, $actualArchivePath);

        // Validate database credentials are present in settings.php file.
        $this->drush(
            'archive:dump',
            [],
            [],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Found database connection settings',
            $this->getErrorOutput()
        );
    }

    public function testArchiveRestoreCommand(): void
    {
        // [info] Copying files from "C:/projects/work/sandbox/archive/code\" to "C:\projects\work\"...
        // [info] Executing: rsync -akz --stats --progress -v C:/projects/work/sandbox/archive/code\ C:\projects\work\
        // > The source and destination cannot both be remote.
        if ($this->isWindows()) {
            $this->markTestSkipped('The command archive:restore does not work on Windows yet due to an rsync issue.');
        }

        // Restoring to sqlite fails
        if ($this->dbDriver() === 'sqlite') {
            $this->markTestSkipped('The command archive:restore cannot restore to an sqlite database.');
        }

        // Restore archive from an existing file.
        $this->assertFalse(is_dir($this->restorePath));
        $this->drush(
            'archive:restore',
            [$this->archivePath],
            array_diff_key($this->archiveRestoreOptions, ['overwrite' => null])
        );
        $this->assertTrue(is_dir($this->restorePath));
        $this->assertTrue(is_file(Path::join($this->restorePath, 'composer.json')));
        $this->assertTrue(is_file(Path::join($this->restorePath, 'composer.lock')));

        $this->setupSettingsPhp();

        $process = new Process(['composer', 'install'], $this->restorePath, null, null, 120);
        $process->run();
        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('"composer install" has failed: %s', $process->getErrorOutput())
        );

        // Restore archive from an existing file and an existing destination path.
        $this->drush(
            'archive:restore',
            [$this->archivePath],
            array_diff_key($this->archiveRestoreOptions, ['overwrite' => null]),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertMatchesRegularExpression(
            '/Destination path .+ already exists/',
            str_replace("\n", " ", $this->getErrorOutput())
        );

        // Restore archive from an existing file and an existing destination path with --overwrite option.
        $this->drush(
            'archive:restore',
            [$this->archivePath],
            $this->archiveRestoreOptions
        );

        // Restore archive from paths.

        // Restore code.
        $testFileName = 'test-file-' . mt_rand() . '.txt';
        file_put_contents(Path::join($this->extractPath, 'code', 'sut', $testFileName), 'foo_bar');
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'code' => null,
                'code-source-path' => Path::join($this->extractPath, 'code'),
                'destination-path' => $this->restorePath,
            ])
        );
        $this->assertTrue(is_file(Path::join($this->restorePath, 'sut', $testFileName)));

        // Restore Drupal files.
        file_put_contents(Path::join($this->extractPath, 'files', $testFileName), 'foo_bar');
        $filesRelativePath = 'files-destination';
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'files' => null,
                'files-source-path' => Path::join($this->extractPath, 'files'),
                'files-destination-relative-path' => $filesRelativePath,
            ])
        );
        $this->assertTrue(is_file(Path::join($this->restorePath, $filesRelativePath, $testFileName)));

        // Restore database.
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'db' => null,
                'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
            ])
        );

        // Restore database with invalid --db-url.
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'db' => null,
                'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
                'db-url' => 'bad://db@url/schema',
            ]),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Failed to get database specification:',
            $this->getErrorOutput()
        );

        // Restore database with --db-url option with an invalid host.
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'db' => null,
                'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
                'db-url' => sprintf(
                    '%s://%s:%s@%s/%s',
                    $this->fixtureDatabaseSettings['db-driver'],
                    $this->fixtureDatabaseSettings['db-username'],
                    $this->fixtureDatabaseSettings['db-password'],
                    'invalid_host',
                    $this->fixtureDatabaseSettings['db-name']
                ),
            ]),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            sprintf('Failed to create database %s.', $this->fixtureDatabaseSettings['db-name']),
            $this->getErrorOutput()
        );

        // Restore database with a set of database connection options.
        $this->drush(
            'archive:restore',
            [],
            array_merge(
                array_diff_key($this->archiveRestoreOptions, ['db-url' => null]),
                [
                    'db' => null,
                    'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
                    'db-driver' => $this->fixtureDatabaseSettings['db-driver'],
                    'db-name' => $this->fixtureDatabaseSettings['db-name'],
                    'db-host' => $this->fixtureDatabaseSettings['db-hostname'],
                    'db-user' => $this->fixtureDatabaseSettings['db-username'],
                    'db-password' => $this->fixtureDatabaseSettings['db-password'],
                ]
            )
        );

        // Restore database with a set of database connection options with an invalid host.
        $this->drush(
            'archive:restore',
            [],
            array_merge(
                array_diff_key($this->archiveRestoreOptions, ['db-url' => null]),
                [
                    'db' => null,
                    'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
                    'db-driver' => $this->fixtureDatabaseSettings['db-driver'],
                    'db-name' => $this->fixtureDatabaseSettings['db-name'],
                    'db-host' => 'invalid_host',
                    'db-user' => $this->fixtureDatabaseSettings['db-username'],
                    'db-password' => $this->fixtureDatabaseSettings['db-password'],
                ]
            ),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            sprintf('Failed to create database %s.', $this->fixtureDatabaseSettings['db-name']),
            $this->getErrorOutput()
        );

        // Restore archive from a non-existing file.
        $nonExistingArchivePath = Path::join($this->getSandbox(), 'arch.tar.gz');
        $this->drush(
            'archive:restore',
            [$nonExistingArchivePath],
            $this->archiveRestoreOptions,
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'arch.tar.gz is not found',
            $this->getErrorOutput()
        );

        // Restore database without database connection settings.
        $this->drush(
            'archive:restore',
            [],
            array_merge(
                array_diff_key($this->archiveRestoreOptions, ['db-url' => null]),
                [
                    'db' => null,
                    'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
                ]
            ),
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Database connection settings are required if --destination-path',
            $this->getErrorOutput()
        );
    }

    /**
     * Creates a backup of settings.php file and replaces the database name with a fixture.
     */
    private function backupSettingsPhp(): void
    {
        copy(
            Path::join('sut', 'sites', 'dev', 'settings.php'),
            Path::join($this->getSandbox(), 'settings.php')
        );
        $settingsPhp = file_get_contents(Path::join($this->getSandbox(), 'settings.php'));
        $settingsPhp = preg_replace(
            "/'database' => '(.+)'/",
            sprintf("'database' => '%s'", $this->fixtureDatabaseSettings['db-name']),
            $settingsPhp
        );
        file_put_contents(Path::join($this->getSandbox(), 'settings.php'), $settingsPhp);
    }

    /**
     * Sets up settings.php for the restored site.
     */
    private function setupSettingsPhp(): void
    {
        $settingsPhpPath = Path::join($this->restorePath, 'sut', 'sites', 'dev', 'settings.php');
        if (is_file($settingsPhpPath)) {
            return;
        }

        mkdir(Path::join($this->restorePath, 'sut', 'sites', 'dev'), 0777, true);
        copy(Path::join($this->getSandbox(), 'settings.php'), $settingsPhpPath);
        $this->assertTrue(is_file($settingsPhpPath));
        $settingsPhp = file_get_contents($settingsPhpPath);
        $this->assertStringContainsString($this->fixtureDatabaseSettings['db-name'], $settingsPhp);
    }

    /**
     * Executes `composer install` in the restored site's composer root.
     */
    private function installComposerDependencies(): void
    {
        $process = new Process(['composer', 'install'], $this->restorePath, null, null, 180);
        $process->run();
        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('"composer install" has failed: %s', $process->getErrorOutput())
        );
    }
}
