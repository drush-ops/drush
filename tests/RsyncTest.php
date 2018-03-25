<?php

namespace Unish;

/**
 * @file
 *   Tests for rsync command
 *
 * @group commands
 */
class RsyncCase extends CommandUnishTestCase
{

    public function setUp()
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(2, true);
        }
    }

  /**
   * Test drush rsync --simulate.
   */
    public function testRsyncSimulated()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('rsync command not currently available on Windows.');
        }

        $options = [
            'simulate' => null,
            'alias-path' => __DIR__ . '/resources/alias-fixtures',
        ];

        // Test simulated simple rsync with two local sites
        $this->drush('rsync', ['@example.stage', '@example.dev'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "Calling system(rsync -e 'ssh ' -akz /path/to/stage /path/to/dev);";
        $this->assertOutputEquals($expected);

        // Test simulated rsync with relative paths
        $this->drush('rsync', ['@example.dev:files', '@example.stage:files'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "Calling system(rsync -e 'ssh ' -akz /path/to/dev/files /path/to/stage/files);";
        $this->assertOutputEquals($expected);

        // Test simulated rsync on local machine with a remote target
        $this->drush('rsync', ['@example.dev:files', '@example.live:files'], $options, null, null, self::EXIT_SUCCESS, '2>&1');
        $expected = "Calling system(rsync -e 'ssh -o PasswordAuthentication=example' -akz /path/to/dev/files www-admin@service-provider.com:/path/on/service-provider/files);";
        $this->assertOutputEquals($expected);

        // Test simulated backend invoke.
        // Note that command-specific options are not processed for remote
        // targets. The aliases are not interpreted at all until they recache
        // the remote side, at which point they will be evaluated & any needed
        // injection will be done.
        $this->drush('rsync', ['@example.dev', '@example.stage'], $options, 'user@server/path/to/drupal#sitename', null, self::EXIT_SUCCESS, '2>&1');
        $expected = "Simulating backend invoke: ssh -o PasswordAuthentication=no user@server 'drush --root=/path/to/drupal --uri=sitename --no-interaction rsync '\''@example.dev'\'' '\''@example.stage'\'' 2>&1' 2>&1";
        $this->assertOutputEquals($expected, '# --alias-path=[^ ]*#');
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
        $this->assertContains('You will delete files in', $this->getOutput());

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
        $uri = $this->getUri();
        $options['simulate'] = null;
        $this->drush('core-rsync', ["$site:%files", "/tmp"], $options, null, null, self::EXIT_SUCCESS, '2>&1;');
        $output = $this->getOutput();
        $level = $this->logLevel();
        $pattern = in_array($level, ['verbose', 'debug']) ? "Calling system(rsync -e 'ssh ' -akzv --stats --progress %s /tmp);" : "Calling system(rsync -e 'ssh ' -akz %s /tmp);";
        $expected = sprintf($pattern, $this->webroot(). "/sites/$uri/files/");
        $this->assertEquals($expected, $output);
    }
}
