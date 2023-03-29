<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\RsyncCommands;

/**
 * @file
 *   Tests for rsync command
 *
 * @group commands
 */
class RsyncIntegrationTest extends UnishIntegrationTestCase
{
    /**
     * Test drush rsync --simulate.
     */
    public function testRsyncSimulated()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('rsync paths may not contain colons on Windows.');
        }

        $options = [
            'simulate' => true,
            'alias-path' => __DIR__ . '/../functional/resources/alias-fixtures',
        ];

        // Test simulated simple rsync between two imaginary files / directories
        $this->drush(RsyncCommands::RSYNC, ['a', 'b'], $options, self::EXIT_SUCCESS, '');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz a b";
        $this->assertErrorOutputContains($expected);

        // Test simulated simple rsync with two local sites
        $this->drush(RsyncCommands::RSYNC, ['@example.stage', '@example.dev'], $options, self::EXIT_SUCCESS, '');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz /path/to/stage /path/to/dev";
        $this->assertErrorOutputContains($expected);

        // Test simulated rsync with relative paths
        $this->drush(RsyncCommands::RSYNC, ['@example.dev:files', '@example.stage:files'], $options, self::EXIT_SUCCESS, '');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz /path/to/dev/files /path/to/stage/files";
        $this->assertErrorOutputContains($expected);

        // Test simulated rsync on local machine with a remote target
        $this->drush(RsyncCommands::RSYNC, ['@example.dev:files', '@example.live:files'], $options, self::EXIT_SUCCESS, '');
        $expected = "[notice] Simulating: rsync -e 'ssh -o PasswordAuthentication=example' -akz /path/to/dev/files www-admin@service-provider.com:/path/on/service-provider/files";
        $this->assertErrorOutputContains($expected);
    }

    public function testRsyncSimulatedWithSelfAlias()
    {
        $options = [
            'simulate' => true,
        ];

        // Test simulated simple rsync between two imaginary files / directories
        $this->drush(RsyncCommands::RSYNC, ['a', '@self.fake:b'], $options, self::EXIT_SUCCESS, '');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz a /path/to/fake/root/b";
        $this->assertErrorOutputContains($expected);
    }
}
