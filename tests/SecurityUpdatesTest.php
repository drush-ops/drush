<?php

namespace Unish;

use Drush\Commands\pm\SecurityUpdateCommands;

/**
 * Tests "pm:security" commands for D8+.
 * @group commands
 * @group slow
 * @group pm
 */
class SecurityUpdatesTest extends CommandUnishTestCase
{

  /**
   * Test that insecure packages are correctly identified.
   */
    public function testInsecurePackage()
    {
        $this->drush('pm:security', [], ['format' => 'json'], null, null, self::EXIT_ERROR);
        $this->assertContains('One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.', $this->getErrorOutput());
        $this->assertContains('Try running: composer require drupal/alinks:^1.1 --update-with-dependencies', $this->getErrorOutput());
        $security_advisories = $this->getOutputFromJSON();
        $this->assertObjectHasAttribute('drupal/alinks', $security_advisories);
        $this->assertEquals('drupal/alinks', $security_advisories->{"drupal/alinks"}->name);
        $this->assertEquals('1.0.0', $security_advisories->{"drupal/alinks"}->version);
        $this->assertEquals('1.1', $security_advisories->{"drupal/alinks"}->{"min-version"});
    }


  /**
   * Test that insecure packages are correctly identified.
   *
   * @dataProvider testConflictConstraintParsingProvider
   */
    public function testConflictConstraintParsing($package, $conflict_constraint, $min_version, $updates_are_available)
    {
        $available_updates = SecurityUpdateCommands::determineUpdatesFromConstraint($conflict_constraint, $package, $package['name']);
        $this->assertEquals($updates_are_available, (bool) $available_updates);

        if ($available_updates) {
            $this->assertEquals($package['version'], $available_updates['version']);
            $this->assertEquals($min_version, $available_updates['min-version']);
        }
    }

  /**
   * Data provider for testConflictConstraintParsing().
   */
    public function testConflictConstraintParsingProvider()
    {
        return [
        // Test "minimum version" conflict.
        [
        [
          'name' => 'Alinks',
          'version' => '1.0.0'
        ],
        '<1.0.1',
        '1.0.1',
        true,
        ],
        // Test "exact version" conflict.
        [
        [
          'name' => 'Alinks',
          'version' => '1.0.0'
        ],
        '1.0.0',
        '1.0.1',
        true,
        ],
        // Test "exact version" conflict with 2 digits. Should not work.
        [
        [
          'name' => 'Alinks',
          'version' => '1.0.0'
        ],
        '1.0',
        '1.0.1',
        false,
        ],
        ];
    }
}
