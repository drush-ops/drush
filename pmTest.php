<?php

require_once dirname(__FILE__) . '/drush_testcase.inc';

/**
  * pm-download testing without any Drupal.
  */  
class pmDownload_TestCase extends Drush_TestCase {
  public function testPmDownload() {
    $destination = UNISH_SANDBOX;
    $this->drush('pm-download', array('devel'), array('destination' => $destination));
    $this->assertFileExists($destination . '/devel/README.txt');
  }
}

/**
  * pm-download testing with Drupal.
  */
class pmDownload_DrupalTestCase extends Drush_DrupalTestCase {
  public function testDestination() {
    $root = $this->sites['dev']['root'];
    $this->drush('pm-download', array('devel'), array('root' => $root));
    $this->assertFileExists($root . '/sites/all/modules/devel/README.txt');
  }
}