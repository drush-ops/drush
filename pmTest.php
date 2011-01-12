<?php

require_once dirname(__FILE__) . '/drush_testcase.inc';

class PM_TestCase extends Drush_TestCase {
  public function testPmDownload() {
    $destination = $this->sandbox;
    $this->execute("$this->drush pm-download devel --destination=$destination");
    $this->assertFileExists($destination . '/devel');
    $this->assertFileExists($destination . '/devel/README.txt');
  }
}