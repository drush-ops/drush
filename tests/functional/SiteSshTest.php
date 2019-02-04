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
            $this->markTestSkipped('ssh command not currently available on Windows.');
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
     * Test drush ssh --simulate 'date'.
     */
    public function testNonInteractive()
    {
        $options = [
            'simulate' => true,
        ];
        $this->drush('ssh', ['date'], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getErrorOutput();
        $expected = "ssh -o PasswordAuthentication=no user@server 'cd /path/to/drupal && date'";
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
     * Test drush ssh with multiple arguments (legacy form). Also test --cd option.
     */
    public function testSshMultipleArgsLegacy()
    {
        $options = [
            'cd' => '/foo/bar',
            'simulate' => true,
        ];
        $this->drush('ssh', ['ls /path1 /path2'], $options, 'user@server/path/to/drupal#sitename');
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'cd /foo/bar && ls /path1 /path2'";
        $this->assertContains($expected, $this->getSimplifiedErrorOutput());
    }
}
