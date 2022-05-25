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
        $testFileName = 'test-file.txt';
        file_put_contents(Path::join($archiveBasePath, 'code', 'sut', $testFileName), 'foo_bar');
        $this->drush(
            'archive:restore',
            [],
            [
                'code' => null,
                'code-source-path' => Path::join($archiveBasePath, 'code'),
            ]
        );
        $this->assertTrue(is_file(Path::join($sutStatus['root'], $testFileName)));

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

        $this->drush(
            'archive:restore',
            [],
            [
                'db' => null,
                'db-source-path' => Path::join($archiveBasePath, 'database', 'database.sql'),
            ]
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
}
