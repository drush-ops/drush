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
        // TODO: re-enable this test once the pm:security command is
        // compatible with Drupal 9.
        if (version_compare(\Drupal::VERSION, '9.0.0', '>=')) {
            $this->markTestSkipped('pm:security not working on Drupal 9 yet.');
        }

        $this->drush('pm:security', [], ['format' => 'json'], self::EXIT_ERROR);
        $this->assertContains('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertContains('Try running: composer require drupal/alinks', $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->arrayHasKey('drupal/alinks', $security_advisories);
        $this->assertEquals('drupal/alinks', $security_advisories["drupal/alinks"]['name']);
        $this->assertEquals('1.0.0', $security_advisories["drupal/alinks"]['version']);
    }
}
