<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group slow
 * @group commands
 * @group archive
 */
class ArchiveTest extends CommandUnishTestCase
{
    public function testArchiveDumpCommand()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'db' => null,
            'exclude-code-paths' => 'sites/.+/settings.php',
        ];

        // Create an archive.
        $this->drush(
            'archive:dump',
            [],
            $options
        );
        $actualArchivePath = $this->getOutput();
        $this->assertMatchesRegularExpression(
            '#\/archives\/\d+\/archive\.tar\.gz$#',
            $actualArchivePath
        );

        // Create an archive with "--destination".
        $expectedArchivePath = Path::join($this->getSandbox(), 'archive.tar.gz');
        $this->drush(
            'archive:dump',
            [],
            array_merge($options, [
                'destination' => $expectedArchivePath,
            ])
        );
        $actualArchivePath = $this->getOutput();
        $this->assertEquals($expectedArchivePath, $actualArchivePath);

        // Try to overwrite the existing archive with "--destination".
        $this->drush(
            'archive:dump',
            [],
            array_merge($options, [
                'destination' => $expectedArchivePath,
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
            array_merge($options, [
                'destination' => $expectedArchivePath,
                'overwrite' => null,
            ])
        );
        $actualArchivePath = $this->getOutput();
        $this->assertEquals($expectedArchivePath, $actualArchivePath);

        // Validate database credentials are present in settings.php file.
        $this->drush(
            'archive:dump',
            [],
            [],
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertMatchesRegularExpression(
            '#Found database connection settings in sites\/.+\/settings\.php#',
            $this->getErrorOutput()
        );
    }
}
