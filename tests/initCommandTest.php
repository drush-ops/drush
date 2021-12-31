<?php

namespace Unish;

/**
 *  Test to see if the `drush init` command does the
 *  setup that it is supposed to do.
 *
 *  @group base
 */
class initCommandCase extends CommandUnishTestCase {

  function testInitCommand() {
    // Call `drush core-init`
    $this->drush('core-init', array(), array('backend' => NULL, 'add-path' => TRUE, 'yes' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    // First test to ensure that the command claimed to have made the expected progress
    $this->assertLogHasMessage($parsed['log'], "Copied example Drush configuration file", 'ok');
    $this->assertLogHasMessage($parsed['log'], "Copied example Drush bash configuration file", 'ok');
    $this->assertLogHasMessage($parsed['log'], "Updated bash configuration file", 'ok');
    // Next we will test to see if there is evidence that those
    // operations worked.
    $home = getenv("HOME");
    $this->assertFileExists("$home/.drush/drushrc.php");
    $this->assertFileExists("$home/.drush/drush.bashrc");
    $this->assertFileExists("$home/.bashrc");

    // Check to see if the .bashrc file sources our drush.bashrc file,
    // and whether it adds the path to UNISH_DRUSH to the $PATH
    $bashrc_contents = file_get_contents("$home/.bashrc");
    $this->assertStringContainsString('drush.bashrc', $bashrc_contents);
    $this->assertStringContainsString(dirname(UNISH_DRUSH), $bashrc_contents);
  }
}
