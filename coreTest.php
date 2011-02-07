<?php

/*
 * @file
 *   Tests for core commands.
 */
class coreCase extends Drush_TestCase {

  /*
   * Test standalone php-script scripts. Assure that script args and options work.
   */
  public function testStandaloneScript() {
    $this->drush('version', array('drush_version'), array('pipe' => NULL));
    $standard = $this->getOutput();

    $this->execute(dirname(__FILE__) . '/hellounish.script drush_version --pipe');
    $standalone = $this->getOutput();
    $this->assertEquals($standard, $standalone);
  }
}