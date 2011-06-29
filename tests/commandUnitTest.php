<?php

class commandUnitCase extends Drush_UnitTestCase {
  /*
   * Assert that $command has interesting properties. Reference command by
   * it's alias (dl) to assure that those aliases are built as expected.
   */
  public function testGetCommands() {
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUSH);
    $commands = drush_get_commands();
    $command = $commands['dl'];

    $this->assertEquals('dl', current($command['aliases']));
    $this->assertEquals('download', current($command['deprecated-aliases']));
    $this->assertArrayHasKey('version_control', $command['engines']);
    $this->assertArrayHasKey('package_handler', $command['engines']);
    $this->assertEquals('pm-download', $command['command']);
    $this->assertEquals('pm', $command['commandfile']);
    $this->assertEquals('drush_command', $command['callback']);
    $this->assertArrayHasKey('examples', $command['sections']);
    $this->assertTrue($command['is_alias']);

    $command = $commands['pm-download'];
    $this->assertArrayNotHasKey('is_alias', $command);

  }
}
