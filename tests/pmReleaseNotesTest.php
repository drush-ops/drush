<?php

namespace Unish;

/**
  * @group pm
  */
class pmReleaseNotesCase extends CommandUnishTestCase {

  /**
   * Tests for pm-releasenotes command.
   */
  public function testReleaseNotes() {
    $this->drush('pm-releasenotes', array('drupal-7.1'));
    $output = $this->getOutput();
    $this->assertContains("RELEASE NOTES FOR 'DRUPAL' PROJECT, VERSION 7.1", $output);
    $this->assertContains('Last updated:  May 25, 2011 - 20:59', $output);
    $this->assertContains('SA-CORE-2011-001 - Drupal core - Multiple vulnerabilities', $output);
  }
}

