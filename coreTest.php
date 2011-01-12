<?php

require_once dirname(__FILE__) . '/drush_testcase.inc';

class Core_TestCase extends Drush_TestCase {
  public function testVersion() {
    $this->execute("$this->drush version --pipe");
    $this->assertEquals('5.0-dev', $this->getOutput(), 'Downloaded expected version of drush.');
  }
}