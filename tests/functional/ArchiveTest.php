<?php

declare(strict_types=1);

namespace Unish;

use PharData;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Unish\Utils\FSUtils;

/**
 * @group slow
 * @group commands
 * @group archive
 */
class ArchiveTest extends CommandUnishTestCase
{
    use FSUtils;

    protected string $archivePath;
    protected string $restorePath;
    protected string $extractPath;
    protected array $archiveDumpOptions;
    protected array $archiveRestoreOptions;
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
        $actualArchivePath = Path::canonicalize($this->getOutput());
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
        $this->fixtureDatabaseSettings['db-password'] = substr($dbUrlParts[2], 0, (int)strpos($dbUrlParts[2], '@'));
        $fixtureDbUrl = self::getDbUrl() . '/' . $this->fixtureDatabaseSettings['db-name'];

        $this->archiveRestoreOptions = [
            'destination-path' => $this->restorePath,
            'overwrite' => null,
            'site-subdir' => 'dev',
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
        $actualArchivePath = Path::canonicalize($this->getOutput());
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

    public function testArchiveDumpSymlinkSwapCommand(): void
    {
        // Sites that contain symlinks to files outside the project root cause
        // critical errors. To test for this, we manually create a symlink
        // and then run archive:dump. If it completes at all, the symlink fix
        // from Issue #5991 is working.
        $linktarget      = Path::join($this->getSandbox(), '../symlinktest.txt');
        $linkdestination = Path::join($this->getSandbox(), 'symlinkdest.txt');

        file_put_contents($linktarget, "This is a symlink target file.");
        symlink($linktarget, $linkdestination);

        // Overwrite the existing archive with "--destination" and "--overwrite".
        $this->drush(
            'archive:dump',
            [],
            array_merge($this->archiveDumpOptions, [
                'destination' => $this->archivePath,
                'overwrite' => null,
            ])
        );

      unlink($linkdestination);
      unlink($linktarget);
    }

    public function SKIPtestArchiveRestoreCommand(): void
    {
        // [info] Copying files from "C:/projects/work/sandbox/archive/code\" to "C:\projects\work\"...
        // [info] Executing: rsync -akz --stats --progress -v C:/projects/work/sandbox/archive/code\ C:\projects\work\
        // > The source and destination cannot both be remote.
        if ($this->isWindows()) {
            $this->markTestSkipped('The command archive:restore does not work on Windows yet due to an rsync issue.');
        }

        $this->markTestSkipped('This test started failing with an error: "The config sync directory is not defined in $settings["config_sync_directory"]." It appears that this failure is indicative of an actual problem with archive:dump and archive:restore, as the config:sync directory is not restored by the later command. It is not clear how this test was previously passing. The archive commands should be fixed in future work.');

        // Restoring to sqlite fails
        if ($this->dbDriver() === 'sqlite') {
            $this->markTestSkipped('The command archive:restore cannot restore to an sqlite database.');
        }

        // Restore the code from a source path.
        $testFileName = 'test-file-' . mt_rand() . '.txt';
        file_put_contents(Path::join($this->extractPath, 'code', 'sut', $testFileName), 'foo_bar');
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'code' => null,
                'code-source-path' => Path::join($this->extractPath, 'code'),
            ])
        );

        $this->assertTrue(is_file(Path::join($this->restorePath, 'sut', $testFileName)));
        $this->assertTrue(is_file(Path::join($this->restorePath, 'composer.json')));
        $this->assertTrue(!is_dir(Path::join($this->restorePath, 'vendor')));
        $this->assertTrue(!is_file(Path::join($this->restorePath, 'sut', 'sites', 'dev', 'settings.php')));
        $this->assertTrue(!is_file(Path::join($this->restorePath, 'sut', 'sites', 'dev', 'settings.local.php')));

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

        // Restore archive from an existing file.
        $this->drush(
            'archive:restore',
            [$this->archivePath],
            $this->archiveRestoreOptions
        );
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

        // Restore the Drupal files from a source path.
        file_put_contents(Path::join($this->extractPath, 'files', $testFileName), 'foo_bar');
        $filesRelativePath = Path::join('sut', 'sites', 'default', 'files');
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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

        // Restore the database from a source path.
        $this->drush(
            'archive:restore',
            [],
            array_merge($this->archiveRestoreOptions, [
                'db' => null,
                'db-source-path' => Path::join($this->extractPath, 'database', 'database.sql'),
            ])
        );
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();

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
        $this->installComposerDependencies();
        $this->addDrushViaPathRepository();
        $this->assertRestoredSiteStatus();
    }

    private function addDrushViaPathRepository(): void
    {
        // When the Drush SUT is archived, it does not include Drush itself,
        // because Drush is the project root, and therefore does not appear in the
        // composer.json of the archived Drupal site. Drush 12.x+ cannot bootstrap
        // any site other than the one it shares its vendor directory with. We
        // must therefore add Drush via a Composer PATH repository in the site
        // created via archive restore before we can run Drush commands on it.
        $this->execOnRestoredSite(['composer', 'config', 'repositories.drush', 'path', dirname(self::getDrush())]);
        $this->execOnRestoredSite(['composer', 'config', 'name', 'my-org/my-site']);
        $this->execOnRestoredSite(['composer', 'require', 'drush/drush:*']);
    }

    /**
     * Asserts the status of restored site.
     */
    private function assertRestoredSiteStatus(): void
    {
        $this->assertTrue(is_file(Path::join($this->restorePath, 'composer.json')));
        $this->assertTrue(is_file(Path::join($this->restorePath, 'sut', 'sites', 'dev', 'settings.php')));
        $this->assertTrue(is_file(Path::join($this->restorePath, 'sut', 'sites', 'dev', 'settings.local.php')));

        $this->drush(
            'status',
            [],
            ['format' => 'json'],
            null,
            Path::join($this->restorePath, 'sut')
        );
        $restoredSiteStatus = json_decode($this->getOutput(), true);
        $this->assertEquals('Connected', $restoredSiteStatus['db-status']);
        $this->assertEquals(Path::join($this->restorePath, 'sut'), $restoredSiteStatus['root']);
        $this->assertEquals($this->fixtureDatabaseSettings['db-name'], $restoredSiteStatus['db-name']);
    }

    /**
     * Executes `composer install` in the restored site's composer root.
     */
    private function installComposerDependencies(): void
    {
        $this->execOnRestoredSite(['composer', 'install']);
    }

    private function execOnRestoredSite(array $cmdArgs): void
    {
        $process = new Process($cmdArgs, $this->restorePath, null, null, 180);
        $process->run();
        $this->assertTrue(
            $process->isSuccessful(),
            sprintf('"%s" has failed: %s', implode(' ', $cmdArgs), $process->getErrorOutput())
        );
    }
}
