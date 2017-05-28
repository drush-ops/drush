<?php

namespace Unish;

/**
 * @group commands
 */
class GenerateCase extends CommandUnishTestCase {

  /**
   * Test callback.
   */
  function  testGenerateDrushCommandFile() {
    // @todo Find a way to pass command input.
    $this->drush('generate', ['drush-command-file']);
    $output = $this->getOutput();

    $this->markTestIncomplete('This test has not been completed yet.');
  }

}
