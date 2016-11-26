<?php

namespace Unish;

/**
 * @file
 *   Tests for ssh.drush.inc
 *
 * @group commands
 */
class siteSshCase extends CommandUnishTestCase {

  /**
   * Test drush ssh --simulate. No additional bash passed.
   */
  public function testInteractive() {
    if ($this->is_windows()) {
      $this->markTestSkipped('ssh command not currently available on Windows.');
    }

    $options = array(
      'simulate' => NULL,
    );
    $this->drush('ssh', array(), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no -t %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), "'cd /path/to/drupal && bash -l'");
    $this->assertEquals($expected, $output);
  }

  /**
   * Test drush ssh --simulate 'date'.
   * @todo Run over a site list. drush_sitealias_get_record() currently cannot
   * handle a site list comprised of longhand site specifications.
   */
  public function testNonInteractive() {
    $options = array(
      'cd' => '0',
      'simulate' => NULL,
    );
    $this->drush('ssh', array('date'), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('date'));
    $this->assertEquals($expected, $output);
  }

  /**
  * Test drush ssh with multiple arguments (preferred form).
  */
  public function testSshMultipleArgs() {
    $options = array(
      'cd' => '0',
      'simulate' => NULL,
    );
    $this->drush('ssh', array('ls', '/path1', '/path2'), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('ls /path1 /path2'));
    $this->assertEquals($expected, $output);
  }

  /**
   * Test drush ssh with multiple arguments (legacy form).
   */
  public function testSshMultipleArgsLegacy() {
   $options = array(
      'cd' => '0',
     'simulate' => NULL,
   );
   $this->drush('ssh', array('ls /path1 /path2'), $options, 'user@server/path/to/drupal#sitename', NULL, self::EXIT_SUCCESS, '2>&1');
   $output = $this->getOutput();
   $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('ls /path1 /path2'));
   $this->assertEquals($expected, $output);
 }
}
