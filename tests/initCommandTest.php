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
    $this->drush('core-init', array(), array('backend' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    // First test to ensure that the command claimed to have made the expected progress
    $this->assertLogHasMessage($parsed['log'], "Copied example Drush configuration file", 'ok');
    $this->assertLogHasMessage($parsed['log'], "Copied example Drush bash configuration file", 'ok');
    $this->assertLogHasMessage($parsed['log'], "Updated bash configuration file", 'ok');
    // Next we will test to see if there is evidence that those
    // operations worked.
    $home = getenv("HOME");
    $this->assertTrue(is_file("$home/.drush/drushrc.php"), "The expected ~/.drush/drushrc.php file does not exist");
    $this->assertTrue(is_file("$home/.drush/drush.bashrc"), "The expected ~/.drush/drush.bashrc file does not exist");
    $this->assertTrue(is_file("$home/.bashrc"), "The expected ~/.bashrc file does not exist");
  }
}
