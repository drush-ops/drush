<?php

namespace Unish;

/**
 * @file
 *   Tests for ssh.drush.inc
 *
 * @group commands
 */
class SiteSshCase extends CommandUnishTestCase {

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
        $this->drush('ssh', [], $options, 'user@server/path/to/drupal#sitename', null, self::EXIT_SUCCESS, '2>&1');
        $output = $this->getOutput();
        $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no -t %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), "'cd /path/to/drupal && bash -l'");
        $this->assertEquals($expected, $output);
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
        $this->drush('ssh', ['date'], $options, 'user@server/path/to/drupal#sitename', null, self::EXIT_SUCCESS, '2>&1');
        $output = $this->getOutput();
        $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('date'));
        $this->assertEquals($expected, $output);
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
        $this->drush('ssh', ['ls', '/path1', '/path2'], $options, 'user@server/path/to/drupal#sitename', null, self::EXIT_SUCCESS, '2>&1');
        $output = $this->getOutput();
        $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('ls /path1 /path2'));
        $this->assertEquals($expected, $output);
    }

  /**
   * Test drush ssh with multiple arguments (legacy form).
   */
    public function testSshMultipleArgsLegacy()
    {
        $options = [
        'cd' => '0',
         'simulate' => null,
        ];
        $this->drush('ssh', ['ls /path1 /path2'], $options, 'user@server/path/to/drupal#sitename', null, self::EXIT_SUCCESS, '2>&1');
        $output = $this->getOutput();
        $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('ls /path1 /path2'));
        $this->assertEquals($expected, $output);
    }
}
