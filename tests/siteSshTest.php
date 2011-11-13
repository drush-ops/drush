<?php

/*
 * @file
 *   Tests for ssh.drush.inc
 */
class siteSshCase extends Drush_CommandTestCase {

  /*
   * Test drush ssh --simulate. No additional bash passed.
   */
  public function testInteractive() {
    $options = array(
      'simulate' => NULL,
    );
    $this->drush('ssh', array(), $options, 'user@server/path/to/drupal#sitename');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s);', self::escapeshellarg('user'), self::escapeshellarg('server'));
    $this->assertEquals($expected, $output);
  }

  /*
   * Test drush ssh --simulate 'date'.
   * @todo Run over a site list. drush_sitealias_get_record() currently cannot
   * handle a site list comprised of longhand site specifications.
   */
  public function testNonInteractive() {
    $options = array(
      'simulate' => NULL,
    );
    $this->drush('ssh', array('date'), $options, 'user@server/path/to/drupal#sitename');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s %s);', self::escapeshellarg('user'), self::escapeshellarg('server'), self::escapeshellarg('date'));
    $this->assertEquals($expected, $output);
  }

  /**
  * Test drush ssh with multiple arguments (preferred form).
  */
  public function testSshMultipleArgs() {
    $options = array(
      'simulate' => NULL,
    );
    $this->drush('ssh', array('ls', '/path1', '/path2'), $options, 'user@server/path/to/drupal#sitename');
    $output = $this->getOutput();
    $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s \'ls /path1 /path2\');', self::escapeshellarg('user'), self::escapeshellarg('server'));
    $this->assertEquals($expected, $output);
  }

  /**
   * Test drush ssh with multiple arguments (legacy form).
   */
  public function testSshMultipleArgsLegacy() {
   $options = array(
     'simulate' => NULL,
   );
   $this->drush('ssh', array('ls /path1 /path2'), $options, 'user@server/path/to/drupal#sitename');
   $output = $this->getOutput();
   $expected = sprintf('Calling proc_open(ssh -o PasswordAuthentication=no %s@%s \'ls /path1 /path2\');', self::escapeshellarg('user'), self::escapeshellarg('server'));
   $this->assertEquals($expected, $output);
 }
}
