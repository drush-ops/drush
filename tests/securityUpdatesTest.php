<?php

namespace Unish;

/**
 * Tests "pm:security" commands for D8+.
 * @group commands
 * @group slow
 * @group pm
 */
class securityUpdatesTest extends CommandUnishTestCase {

  /**
   * Test that insecure packages are correctly identified.
   */
  function testInsecurePackage() {
    $this->drush('pm:security', array(), array('format' => 'json'), NULL, NULL, self::EXIT_ERROR);
    $this->assertContains('One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.', $this->getErrorOutput());
    $this->assertContains('Try running: composer require drupal/alinks:^1.1 --update-with-dependencies', $this->getErrorOutput());
    $security_advisories = $this->getOutputFromJSON();
    $this->assertObjectHasAttribute('drupal/alinks', $security_advisories);
    $this->assertEquals('drupal/alinks', $security_advisories->{"drupal/alinks"}->name);
    $this->assertEquals('1.0.0', $security_advisories->{"drupal/alinks"}->version);
    $this->assertEquals('1.1', $security_advisories->{"drupal/alinks"}->{"min-version"});
  }
}
