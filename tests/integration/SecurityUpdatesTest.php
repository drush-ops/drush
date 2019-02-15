<?php

namespace Unish;

/**
 * Tests "pm:security" command.
 * @group commands
 * @group pm
 */
class SecurityUpdatesTest extends UnishIntegrationTestCase
{

  /**
   * Test that insecure packages are correctly identified.
   */
    public function testInsecurePackage()
    {
        $this->drush('pm:security', [], ['format' => 'json'], self::EXIT_ERROR);
        $this->assertContains('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertContains('Try running: composer require drupal/alinks --update-with-dependencies', $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->assertObjectHasAttribute('drupal/alinks', $security_advisories);
        $this->assertEquals('drupal/alinks', $security_advisories->{"drupal/alinks"}->name);
        $this->assertEquals('1.0.0', $security_advisories->{"drupal/alinks"}->version);
    }
}
