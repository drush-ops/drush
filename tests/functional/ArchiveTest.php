<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\ArchiveDumpCommands;
use Drush\Commands\core\ArchiveRestoreCommands;
use Drush\Commands\core\StatusCommands;
use PharData;
use Symfony\Component\Filesystem\Path;
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
            ArchiveDumpCommands::DUMP,
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
            StatusCommands::STATUS,
            [],
            ['format' => 'json']
        );
        $this->fixtureDatabaseSettings = json_decode($this->getOutput(), true);
        $this->fixtureDatabaseSettings['db-name'] = 'archive_dump_restore_test_' .  mt_rand();
        $dbUrlParts = explode(':', self::getDbUrl());
        $this->fixtureDatabaseSettings['db-password'] = substr($dbUrlParts[2], 0, (int)strpos($dbUrlParts[2], '@'));
    }

    public function testArchiveDumpCommand(): void
    {
        // Create a file at the destination to confirm that archive:dump
        // will fail without --overwrite in this instance.
        file_put_contents($this->archivePath, "Existing file at destination");

        // Try to overwrite the existing archive with "--destination".
        $this->drush(
            ArchiveDumpCommands::DUMP,
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
            ArchiveDumpCommands::DUMP,
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
            ArchiveDumpCommands::DUMP,
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

}
