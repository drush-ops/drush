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

  function testDrupalDirectory() {
    $this->setUpDrupal('dev', TRUE);
    $root = $this->sites['dev']['root'];
    $options = array(
      'root' => $root,
      'uri' => 'dev',
      'verbose' => NULL,
      'yes' => NULL,
    );
    $this->drush('pm-download', array('devel'), $options);
    $this->drush('pm-enable', array('devel'), $options);

    $this->drush('drupal-directory', array('devel'), $options);
    $output = $this->getOutput();
    $this->assertEquals(escapeshellarg($root . '/sites/all/modules/devel'), $output);

    $this->drush('drupal-directory', array('%files'), $options);
    $output = $this->getOutput();
    $this->assertEquals(escapeshellarg($root . '/sites/dev/files'), $output);

    $this->drush('drupal-directory', array('%modules'), $options);
    $output = $this->getOutput();
    $this->assertEquals(escapeshellarg($root . '/sites/all/modules'), $output);
  }
}