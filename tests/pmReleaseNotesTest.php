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
    $this->assertStringContainsString("RELEASE NOTES FOR 'DRUPAL' PROJECT, VERSION 7.1", $output);
    $this->assertStringContainsString('SA-CORE-2011-001 - Drupal core - Multiple vulnerabilities', $output);
  }
}

