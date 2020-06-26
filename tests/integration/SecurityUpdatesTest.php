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
   * Test that insecure Drupal packages are correctly identified.
   */
    public function testInsecureDrupalPackage()
    {
        list($expected_package, $expected_version) = $this->isDrupalGreaterThanOrEqualTo('9.0.0') ? ['drupal/semver_example', '2.2.0'] : ['drupal/alinks', '1.0.0'];
        $this->drush('pm:security', [], ['format' => 'json'], self::EXIT_ERROR);
        $this->assertContains('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertContains("Try running: composer require $expected_package", $this->getErrorOutput());
        $this->assertContains("Try running: composer require drupal/core", $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->arrayHasKey($expected_package, $security_advisories);
        $this->assertEquals($expected_package, $security_advisories[$expected_package]['name']);
        $this->assertEquals($expected_version, $security_advisories[$expected_package]['version']);
    }

    /**
     * Test that insecure PHP packages are correctly identified.
     */
    public function testInsecurePhpPackage()
    {
        $this->drush('pm:security-php', [], ['format' => 'json'], self::EXIT_ERROR);
        $this->assertContains('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertContains('Run composer why david-garcia/phpwhois', $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->arrayHasKey('david-garcia/phpwhois', $security_advisories);
    }
}
