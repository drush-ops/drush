<?php

namespace Unish;

use Webmozart\PathUtil\Path;

class commandUnitCase extends UnitUnishTestCase {
  /**
   * Assure that matching version-specific command files are loaded and others are ignored.
   */
  function testCommandVersionSpecific() {
    $path = Path::join(UNISH_SANDBOX, 'commandUnitCase');
    $major = $this->drush_major_version();
    $major_plus1 = $major + 1;

    // Write matched and unmatched files to the system search path.
    $files = array(
      Path::join($path, "$major.drush$major.inc"),
      Path::join($path, "drush$major/drush$major.drush.inc"),
      Path::join($path, "$major_plus1.drush$major_plus1.inc"),
      Path::join($path, "drush$major_plus1/drush$major_plus1.drush.inc"),
    );
    $this->mkdir(Path::join($path, 'drush'. $major));
    $this->mkdir(Path::join($path, 'drush'. $major_plus1));
    foreach ($files as $file) {
      $contents = <<<EOD
<?php
// Written by Unish. This file is safe to delete.
\$GLOBALS['unish_foo'][] = '$file';
EOD;
      $return = file_put_contents($file, $contents);
    }
    drush_set_context('DRUSH_INCLUDE', array($path));
    drush_preflight();
    $loaded = drush_commandfile_list();
    $this->assertContains($files[0], $loaded); //Loaded a version-specific command file.
    $this->assertContains($files[1], $loaded); //Loaded a version-specific command directory.
    $this->assertNotContains($files[2], $loaded); //Did not load a mismatched version-specific command file.
    $this->assertNotContains($files[3], $loaded); //Did not load a a mismatched version-specific command directory.
  }

  /**
   * Assert that $command has interesting properties. Reference command by
   * it's alias (dl) to assure that those aliases are built as expected.
   */
  public function testGetCommands() {
    drush_preflight();
    $commands = drush_get_commands();
    $command = $commands['dl'];

    $this->assertEquals('dl', current($command['aliases']));
    $this->assertArrayHasKey('version_control', $command['engines']);
    $this->assertArrayHasKey('package_handler', $command['engines']);
    $this->assertArrayHasKey('release_info', $command['engines']);
    $this->assertEquals('pm-download', $command['command']);
    $this->assertEquals('pm', $command['commandfile']);
    $this->assertEquals('drush_command', $command['callback']);
    $this->assertArrayHasKey('examples', $command['sections']);
    $this->assertTrue($command['is_alias']);

    $command = $commands['pm-download'];
    $this->assertArrayNotHasKey('is_alias', $command);
  }
}
