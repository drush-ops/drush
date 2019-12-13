<?php

namespace Unish;

/**
 * @file
 *   Tests for rsync command
 *
 * @group commands
 * @group slow
 */
class RsyncTest extends CommandUnishTestCase
{

    public function setUp()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('rsync paths may not contain colons on Windows.');
        }

        if (!$this->getSites()) {
            $this->setUpDrupal(2, true);
        }
    }

  /**
   * Test drush rsync --simulate.
   */
    public function testRsyncSimulated()
    {
        $options = [
            'uri' => 'OMIT',
            'simulate' => null,
            'alias-path' => __DIR__ . '/resources/alias-fixtures',
        ];

        // Test simulated remote invoke.
        // Note that command-specific options are not processed for remote
        // targets. The aliases are not interpreted at all until they recache
        // the remote side, at which point they will be evaluated & any needed
        // injection will be done.
        $this->drush('rsync', ['@example.dev', '@example.stage'], $options, 'user@server/path/to/drupal#sitename');
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'drush --no-interaction rsync @example.dev @example.stage --uri=sitename --root=/path/to/drupal";
        $this->assertContains($expected, $this->getSimplifiedErrorOutput());
    }

    public function testRsyncPathAliases()
    {
        $aliases = $this->getAliases();
        $source_alias = array_shift($aliases);
        $target_alias = current($aliases);

        $options = [
            'yes' => null,
            'alias-path' => __DIR__ . '/resources/alias-fixtures',
        ];

        $source = $this->webroot() . '/sites/dev/files/a';
        $target = $this->webroot() . '/sites/stage/files/b';

        @mkdir($source);
        @mkdir($target);

        $source_file = "$source/example.txt";
        $target_file = "$target/example.txt";

        // Delete target file just to be sure that we are running a clean test.
        if (file_exists($target_file)) {
            unlink($target_file);
        }

        // Create something on the dev site at $source for us to copy
        $test_data = "This is my test data";
        file_put_contents($source_file, $test_data);

        // We just deleted it -- should be missing
        $this->assertFileNotExists($target_file);
        $this->assertFileExists($source_file);

        // Test an actual rsync between our two fixture sites. Note that
        // these sites share the same web root.
        $this->drush('rsync', ["$source_alias:%files/a/", "$target_alias:%files/b"], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $this->assertContains('Copy new and override existing files at ', $this->getOutput());

        // Test to see if our fixture file now exists at $target
        $this->assertFileExists($target_file);
        $this->assertStringEqualsFile($target_file, $test_data);
    }

  /**
   * Test to see if rsync @site:%files calculates the %files path correctly.
   * This tests the non-optimized code path. The optimized code path (direct
   * call to Drush API functions rather than an `exec`) has not been implemented.
   */
    public function testRsyncAndPercentFiles()
    {
        $site = current($this->getAliases());
        $options['simulate'] = null;
        $this->drush('core:rsync', ["$site:%files", "/tmp"], $options, null, null, self::EXIT_SUCCESS, '2>&1;');
        $this->assertContains('[notice] Simulating: rsync -e \'ssh \' -akz __DIR__/sut/sites/dev/files/ /tmp', $this->getSimplifiedOutput());
    }
}
