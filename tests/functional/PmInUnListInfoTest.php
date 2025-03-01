<?php

declare(strict_types=1);

/**
 * @file
 *   Tests for enable, disable, uninstall, pm:list commands.
 */

namespace Unish;

use Drush\Commands\core\StatusCommands;
use Drush\Commands\pm\PmCommands;
use Drush\Commands\sql\SqlCommands;

/**
 *  @group slow
 *  @group pm
 */
class PmInUnListInfoTest extends CommandUnishTestCase
{
    public function testEnDisUnList()
    {
        $sites = $this->setUpDrupal(1, true);

        // Test that pm-list lists uninstalled modules.
        $this->drush(PmCommands::LIST, [], ['no-core' => null, 'status' => 'disabled']);
        $out = $this->getOutput();
        $this->assertStringContainsString('drush_empty_module', $out);

        // Test that pm:install does not install a module if the install
        // requirements are not met.
        $this->drush(PmCommands::INSTALL, ['drush_empty_module'], [], null, null, self::EXIT_ERROR, null, [
          'UNISH_FAIL_INSTALL_REQUIREMENTS' => 'drush_empty_module',
        ]);
        $err = $this->getErrorOutput();
        $this->assertStringContainsString("Unable to install module 'drush_empty_module' due to unmet requirement(s)", $err);
        $this->assertStringContainsString('Primary install requirements not met.', $err);
        $this->assertStringContainsString('Secondary install requirements not met.', $err);
        $this->drush(PmCommands::LIST, [], ['no-core' => null, 'status' => 'disabled']);
        $out = $this->getOutput();
        $this->assertStringContainsString('drush_empty_module', $out);

        // Test pm:install enables a module, and pm:list verifies that.
        $this->drush(PmCommands::INSTALL, ['drush_empty_module']);
        $this->drush(PmCommands::LIST, [], ['status' => 'enabled']);
        $out = $this->getOutput();
        $this->assertStringContainsString('drush_empty_module', $out);

        $this->drush(StatusCommands::STATUS, [], ['field' => 'drupal-version']);
        $drupal_version = $this->getOutputRaw();

        // Test the testing install profile theme is installed.
        $active_theme = 'stark';
        $this->assertStringContainsString($active_theme, $out, 'Themes are in the pm:list');

        // Test cache was cleared after enabling a module.
        $table = 'router';
        $path = '/admin/config/development/drush_empty_module';
        $this->drush(SqlCommands::QUERY, ["SELECT path FROM $table WHERE path = '$path';"]);
        $list = $this->getOutputAsList();
        $this->assertTrue(in_array($path, $list), 'Cache was cleared after modules were enabled');

        // Test pm-list filtering.
        $this->drush(PmCommands::LIST, [], ['package' => 'Core']);
        $out = $this->getOutput();
        $this->assertStringNotContainsString('drush_empty_module', $out, 'Drush Empty Module is not part of core package');

        // Check output fields in pm:list
        $this->drush(PmCommands::LIST, [], ['fields' => '*', 'format' => 'json']);
        $extensionProperties = $this->getOutputFromJSON();
        $this->assertTrue(isset($extensionProperties['drush_empty_module']));
        $this->assertEquals($extensionProperties['drush_empty_module']['project'], 'drush_empty_module');
        $this->assertEquals($extensionProperties['drush_empty_module']['package'], 'Other');
        $this->assertEquals($extensionProperties['drush_empty_module']['status'], 'Enabled');
        $this->assertEquals($extensionProperties['drush_empty_module']['type'], 'module');

        // Test uninstall of installed module.
        $this->drush(PmCommands::UNINSTALL, ['drush_empty_module']);
        $out = $this->getErrorOutput();
        $this->assertStringContainsString('Successfully uninstalled', $out);

        $this->drush(PmCommands::LIST, [], ['status' => 'disabled', 'type' => 'module']);
        $out = $this->getOutput();
        $this->assertStringContainsString('drush_empty_module', $out);

        // Test uninstall of uninstalled module.
        $this->drush(PmCommands::UNINSTALL, ['drush_empty_module'], [], null, null, self::EXIT_ERROR);
        $out = $this->getErrorOutput();
        $this->assertStringContainsString('The following module(s) are not installed', $out);

        // Test uninstall of required module, and reason printing
        $this->drush(PmCommands::UNINSTALL, ['user'], [], null, null, self::EXIT_ERROR);
        $out = $this->getErrorOutput();
        $this->assertStringContainsString('is required', $out);
    }
}
