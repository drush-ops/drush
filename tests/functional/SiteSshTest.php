<?php

namespace Unish;

/**
 * @file
 *   Tests for SSHCommands
 *
 * @group commands
 */
class SiteSshCase extends CommandUnishTestCase
{

    /**
     * Test drush ssh --simulate. No additional bash passed.
     */
    public function testInteractive()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('TTY mode not supported on Windows.');
        }

        $options = [
            'simulate' => true,
        ];
        $this->drush('ssh', [], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getErrorOutput();
        $expected = "[notice] Simulating: ssh -t -o PasswordAuthentication=no user@server 'cd /path/to/drupal && bash -l'";
        $this->assertContains($expected, $output);
    }

    /**
     * Test drush ssh --simulate 'time && date'.
     */
    public function testNonInteractive()
    {
        $options = [
            'simulate' => true,
        ];
        $this->drush('ssh', ['time && date'], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getErrorOutput();
        $expected = "ssh -o PasswordAuthentication=no user@server 'cd /path/to/drupal && time && date'";
        $this->assertContains($expected, $output);
    }

    /**
    * Test drush ssh with multiple arguments (preferred form).
    */
    public function testSshMultipleArgs()
    {
        $options = [
            'simulate' => true,
        ];
        $this->drush('ssh', ['ls', '/path1', '/path2'], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getSimplifiedErrorOutput();
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'cd /path/to/drupal && ls /path1 /path2'";
        $this->assertContains($expected, $output);
    }

    /**
     * Test with single arg and --cd option.
     */
    public function testSshSingleArgs()
    {
        $options = [
            'simulate' => true,
        ];
        $this->drush('ssh', ['ls /path1 /path2'], $options, 'user@server/path/to/drupal#sitename');
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'cd /path/to/drupal && ls /path1 /path2'";
        $this->assertContains($expected, $this->getSimplifiedErrorOutput());
    }
}
