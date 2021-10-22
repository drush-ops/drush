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
        $this->drush('pm:security', [], ['format' => 'json'], self::EXIT_ERROR_WITH_CLARITY);
        $this->assertStringContainsString('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertStringContainsString("$expected_package", $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->arrayHasKey($expected_package, $security_advisories);
        $this->assertEquals($expected_package, $security_advisories[$expected_package]['name']);
        $this->assertEquals($expected_version, $security_advisories[$expected_package]['version']);

        // If our SUT is 9.0.0, then we should find a security update for Drupal core too.
        if (\Drupal::VERSION != '9.0.0') {
            $this->markTestSkipped("We only test for drupal/core security updates if the SUT is on Drupal 9.0.0");
        }
        $this->assertStringContainsString("Try running: composer require drupal/core", $this->getErrorOutput());
        $this->arrayHasKey('drupal/core', $security_advisories);
        $this->assertEquals('drupal/core', $security_advisories['drupal/core']['name']);
        $this->assertEquals('9.0.0', $security_advisories['drupal/core']['version']);
    }

    /**
     * Test that dev modules are correctly excluded.
     */
    public function testNoInsecureProductionDrupalPackage()
    {
        $this->drush('pm:security', [], ['format' => 'json', 'no-dev' => true], self::EXIT_SUCCESS);
        $this->assertStringContainsString('There are no outstanding security updates for Drupal projects', $this->getErrorOutput());
    }

    /**
     * Test that insecure PHP packages are correctly identified.
     */
    public function testInsecurePhpPackage()
    {
        $this->drush('pm:security-php', [], ['format' => 'json'], self::EXIT_ERROR_WITH_CLARITY);
        $this->assertStringContainsString('One or more of your dependencies has an outstanding security update.', $this->getErrorOutput());
        $this->assertStringContainsString('Run composer why david-garcia/phpwhois', $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->arrayHasKey('david-garcia/phpwhois', $security_advisories);
    }

    /**
     * Test that dev dependencies are correctly excluded.
     */
    public function testNoInsecureProductionPhpPackage()
    {
        $this->drush('pm:security-php', [], ['format' => 'json', 'no-dev' => true], self::EXIT_SUCCESS);
        $this->assertStringContainsString('There are no outstanding security updates for your dependencies.', $this->getErrorOutput());
    }
}
