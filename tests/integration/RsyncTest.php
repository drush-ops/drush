<?php

namespace Unish;

/**
 * @file
 *   Tests for rsync command
 *
 * @group commands
 * @group slow
 */
class RsyncTest extends UnishIntegrationTestCase
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
        $this->drush('rsync', ['a', 'b'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz a b";
        $this->assertErrorOutputEquals($expected);

        // Test simulated simple rsync with two local sites
        $this->drush('rsync', ['@example.stage', '@example.dev'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz /path/to/stage /path/to/dev";
        $this->assertErrorOutputEquals($expected);

        // Test simulated rsync with relative paths
        $this->drush('rsync', ['@example.dev:files', '@example.stage:files'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz /path/to/dev/files /path/to/stage/files";
        $this->assertErrorOutputEquals($expected);

        // Test simulated rsync on local machine with a remote target
        $this->drush('rsync', ['@example.dev:files', '@example.live:files'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "[notice] Simulating: rsync -e 'ssh -o PasswordAuthentication=example' -akz /path/to/dev/files www-admin@service-provider.com:/path/on/service-provider/files";
        $this->assertErrorOutputEquals($expected);
    }

    public function testRsyncSimulatedWithSelfAlias()
    {
        $options = [
            'simulate' => true,
        ];

        // Test simulated simple rsync between two imaginary files / directories
        $this->drush('rsync', ['a', '@self.fake:b'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "[notice] Simulating: rsync -e 'ssh ' -akz a /path/to/fake/root/b";
        $this->assertErrorOutputContains($expected);
    }
}
