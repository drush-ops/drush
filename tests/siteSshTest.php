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
    $aliases['interactive'] = array(
      'remote-host' => 'my.server.com',
      'remote-user' => 'user123',
    );
    $this->write_alias($aliases);

    $options = array(
      'simulate' => NULL,
      'alias-path' => UNISH_SANDBOX,
    );
    $this->drush('ssh', array('@interactive', ), $options);
    $output = $this->getOutput();
    $expected = sprintf('ssh -o PasswordAuthentication=no %s@%s', self::escapeshellarg('user123'), self::escapeshellarg('my.server.com'));
    $this->assertEquals($expected, $output);
  }

  /*
   * Test drush ssh --simulate 'date'. Runs over a site listadditional bash.
   */
  public function testNonInteractive() {
    $aliases['non-interactive'] = array(
      'remote-host' => 'my.server.com',
      'remote-user' => 'user123',
    );
    $this->write_alias($aliases);

    $options = array(
      'simulate' => NULL,
      'alias-path' => UNISH_SANDBOX,
    );
    $this->drush('ssh', array('@non-interactive', 'date'), $options);
    $output = $this->getOutput();
    $expected = sprintf('ssh -o PasswordAuthentication=no %s@%s %s', self::escapeshellarg('user123'), self::escapeshellarg('my.server.com'), self::escapeshellarg('date'));
    $this->assertEquals($expected, $output);
  }

  /*
   * Write an alias file to the sandbox.
   */
  public function write_alias($aliases) {
    $contents = $this->file_aliases($aliases);
    $alias_path = UNISH_SANDBOX . "/aliases.drushrc.php";
    file_put_contents($alias_path, $contents);
  }
}
