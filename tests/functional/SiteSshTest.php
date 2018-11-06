<?php

namespace Unish;

/**
 * @file
 *   Tests for ssh.drush.inc
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
        'simulate' => null,
        ];
        $this->drush('ssh', [], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getErrorOutput();
        $expected = "[notice] Simulating: ssh -t -o PasswordAuthentication=no user@server 'cd /path/to/drupal && bash -l'";
        $this->assertContains($expected, $output);
    }

  /**
   * Test drush ssh --simulate 'date'.
   * @todo Run over a site list. drush_sitealias_get_record() currently cannot
   * handle a site list comprised of longhand site specifications.
   */
    public function testNonInteractive()
    {
        $options = [
        'cd' => '0',
        'simulate' => null,
        ];
        $this->drush('ssh', ['date'], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getErrorOutput();
        $expected = "ssh -o PasswordAuthentication=no user@server date";
        $this->assertContains($expected, $output);
    }

  /**
  * Test drush ssh with multiple arguments (preferred form).
  */
    public function testSshMultipleArgs()
    {
        $options = [
        'cd' => '0',
        'simulate' => null,
        ];
        $this->drush('ssh', ['ls', '/path1', '/path2'], $options, 'user@server/path/to/drupal#sitename');
        $output = $this->getSimplifiedErrorOutput();
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'ls /path1 /path2'";
        $this->assertContains($expected, $output);
    }

  /**
   * Test drush ssh with multiple arguments (legacy form).
   */
    public function testSshMultipleArgsLegacy()
    {
        // @TODO: Bring this back?
        $this->markTestSkipped('Legacy ssh form, where first element of commandline contains both program and arguments is not supported.');

        $options = [
        'cd' => '0',
         'simulate' => null,
        ];
        $this->drush('ssh', ['ls /path1 /path2'], $options, 'user@server/path/to/drupal#sitename');
        $expected = "[notice] Simulating: ssh -o PasswordAuthentication=no user@server 'ls /path1 /path2'";
        $this->assertContains($expected, $this->getSimplifiedErrorOutput());
    }
}
