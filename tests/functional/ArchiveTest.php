<?php

namespace Unish;

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
    /**
     * @var string
     */
    protected string $archivePath;

    /**
     * @var array
     */
    protected array $archiveDumpOptions;

    /**
     * @var null|string
     */
    protected ?string $testFilePath = null;

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
            'exclude-code-paths' => 'sut/sites/.+/settings.php,(?!sut).*',
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
        $archivePath = Path::join($this->getSandbox(), 'archive.tar.gz');
        $this->drush(
            'archive:restore',
            [$archivePath]
        );

        // Restore archive from an existing file and an existing uncompressed directory.

        // Restore without options.
        $this->drush(
            'archive:restore',
            [$archivePath],
            [],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertMatchesRegularExpression(
            '/Extract directory .+ already exists/',
            $this->getErrorOutput()
        );

        // Restore with --overwrite option.
        $this->drush(
            'archive:restore',
            [$archivePath],
            ['overwrite' => null],
        );

        $this->drush(
            'status',
            [],
            ['format' => 'json']
        );
        $sutStatus = json_decode($this->getOutput(), true);

        // Restore archive from paths.

        $archiveBasePath = Path::join($this->getSandbox(), 'archive');
        $testFileName = 'test-file-' . mt_rand() . '.txt';

        // Restore code.
        file_put_contents(Path::join($archiveBasePath, 'code', 'sut', $testFileName), 'foo_bar');
        $this->testFilePath = Path::join($sutStatus['root'], $testFileName);
        $this->drush(
            'archive:restore',
            [],
            [
                'code' => null,
                'code-source-path' => Path::join($archiveBasePath, 'code'),
            ]
        );
        $this->assertTrue(is_file($this->testFilePath));

        // Restore Drupal files.
        file_put_contents(Path::join($archiveBasePath, 'files', $testFileName), 'foo_bar');
        $this->drush(
            'archive:restore',
            [],
            [
                'files' => null,
                'files-source-path' => Path::join($archiveBasePath, 'files'),
            ]
        );
        $this->assertTrue(is_file(Path::join($sutStatus['root'], $sutStatus['files'], $testFileName)));

        // Restore database.
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
            ]
        );

        // Restore database with invalid --db-url.
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
                'db-url' => 'bad://db@url/schema',
            ],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Failed to get database specification:',
            $this->getErrorOutput()
        );

        // Restore database with valid --db-url option.
        $sutDbUrl = self::getDbUrl() . '/' . $sutStatus['db-name'];
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
                'db-url' => $sutDbUrl,
            ]
        );

        // Restore database with valid --db-url option with an invalid password.
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
                'db-url' => sprintf(
                    '%s://%s:%s@%s/%s',
                    $sutStatus['db-driver'],
                    $sutStatus['db-username'],
                    'invalid_password',
                    $sutStatus['db-hostname'],
                    $sutStatus['db-name']
                ),
            ],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Database import has failed.',
            $this->getErrorOutput()
        );

        // Restore database with a set of database connection options.
        $dbUrlParts = explode(':', self::getDbUrl());
        $sutDbPassword = substr($dbUrlParts[2], 0, strpos($dbUrlParts[2], '@'));
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
                'db-name' => $sutStatus['db-name'],
                'db-host' => $sutStatus['db-hostname'],
                'db-user' => $sutStatus['db-username'],
                'db-password' => $sutDbPassword,
            ]
        );

        // Restore database with a set of database connection options with an invalid password.
        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
                'db-name' => $sutStatus['db-name'],
                'db-host' => $sutStatus['db-hostname'],
                'db-user' => $sutStatus['db-username'],
                'db-password' => 'invalid_password',
            ],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Database import has failed.',
            $this->getErrorOutput()
        );

        // Restore archive from a non-existing file.

        $nonExistingArchivePath = Path::join($this->getSandbox(), 'arch.tar.gz');
        $this->drush(
            'archive:restore',
            [$nonExistingArchivePath],
            [],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'arch.tar.gz is not found',
            $this->getErrorOutput()
        );

        // Restore archive to a specified destination.

        $destination = Path::join($this->getSandbox(), 'restore-to-destination-' . mt_rand());
        $this->assertFalse(is_dir($destination));

        // Restore code with --destination-path option.
        $this->drush(
            'archive:restore',
            [],
            [
                'code' => null,
                'code-source-path' => Path::join($archiveBasePath, 'code'),
                'destination-path' => $destination,
            ]
        );
        $this->assertTrue(is_file(Path::join($destination, 'sut', $testFileName)));

        // Restore Drupal files with --destination-path option.
        $this->drush(
            'archive:restore',
            [],
            [
                'files' => null,
                'files-source-path' => Path::join($archiveBasePath, 'files'),
                'destination-path' => $destination,
            ],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Can\'t detect relative path for Drupal files',
            $this->getErrorOutput()
        );

        // Restore Drupal files with --destination-path and --files-destination-relative-path options.
        $filesRelativePath = 'files-destination';
        $this->drush(
            'archive:restore',
            [],
            [
                'files' => null,
                'files-source-path' => Path::join($archiveBasePath, 'files'),
                'destination-path' => $destination,
                'files-destination-relative-path' => $filesRelativePath,
            ]
        );
        $this->assertTrue(is_file(Path::join($destination, $filesRelativePath, $testFileName)));
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->testFilePath && is_file($this->testFilePath)) {
           unlink($this->testFilePath);
        }
    }
}
