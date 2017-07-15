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
    $expected = <<< EXPECTED
------------------------------------------------------------------------------
> RELEASE NOTES FOR 'DRUPAL' PROJECT, VERSION 7.1:
> Last updated:  25 May 2011 at 20:59 UTC.
> Security
------------------------------------------------------------------------------
EXPECTED;
    $this->assertContains($expected, $output, 'Header is fine.');
    $this->assertContains('SA-CORE-2011-001 - Drupal core - Multiple vulnerabilities', $output, 'Release notes includes SA reference.');
  }
}

