<?php

class commandUnitCase extends Drush_UnitTestCase {
  /**
   * Assure that matching version-specific command files are loaded and others are ignored.
   */
  function testCommandVersionSpecific() {
    $path = UNISH_SANDBOX . '/commandUnitCase';
    $major = $this->drush_major_version();
    $major_plus1 = $major + 1;

    // Write matched and unmatched files to the system search path.
    $files = array(
      $path .  "/$major.drush$major.inc",
      $path .  "/drush$major/drush$major.drush.inc",
      $path .  "/$major_plus1.drush$major_plus1.inc",
      $path .  "/drush$major_plus1/drush$major_plus1.drush.inc",
    );
    mkdir($path);
    mkdir($path . '/drush' . $major);
    mkdir($path . '/drush' . $major_plus1);
    foreach ($files as $file) {
      $contents = <<<EOD
<?php
// Written by Unish. This file is safe to delete.
\$GLOBALS['unish_foo'][] = '$file';
EOD;
      $return = file_put_contents($file, $contents);
    }
    drush_set_context('DRUSH_INCLUDE', array($path));
    drush_bootstrap(DRUSH_BOOTSTRAP_DRUSH);
    $loaded = drush_commandfile_list();
    $this->assertTrue(in_array($files[0], $loaded), 'Loaded a version-specific command file.');
    $this->assertTrue(in_array($files[1], $loaded), 'Loaded a version-specific command directory.');
    $this->assertFalse(in_array($files[2], $loaded), 'Did not load a mismatched version-specific command file.');
    $this->assertFalse(in_array($files[3], $loaded), 'Did not load a a mismatched version-specific command directory.');
  }

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
