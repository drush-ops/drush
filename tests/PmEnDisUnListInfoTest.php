<?php

/**
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 */

namespace Unish;

/**
 *  @group slow
 *  @group pm
 */
class EnDisUnListInfoCase extends CommandUnishTestCase {

    public function testEnDisUnList()
    {
        $sites = $this->setUpDrupal(1, true);

        // Test that pm-list lists uninstalled modules.
        $this->drush('pm-list', [], ['no-core' => null, 'status' => 'disabled']);
        $out = $this->getOutput();
        $this->assertContains('devel', $out);

        // Test pm-enable enables a module, and pm-list verifies that.
        $this->drush('pm-enable', ['devel']);
        $this->drush('pm-list', [], ['status' => 'enabled']);
        $out = $this->getOutput();
        $this->assertContains('devel', $out);
        // Test the testing install profile theme is installed.;
        $this->assertContains('classy', $out, 'Themes are in the pm-list');

        // Test cache was cleared after enabling a module.
        $table = 'router';
        $path = '/admin/config/development/devel';
        $this->drush('sql-query', ["SELECT path FROM $table WHERE path = '$path';"]);
        $list = $this->getOutputAsList();
        $this->assertTrue(in_array($path, $list), 'Cache was cleared after modules were enabled');

        // Test pm-list filtering.
        $this->drush('pm-list', [], ['package' => 'Core']);
        $out = $this->getOutput();
        $this->assertNotContains('devel', $out, 'Devel is not part of core package');

        // Test module uninstall.
        $this->drush('pm-uninstall', ['devel']);
        $this->drush('pm-list', [], ['status' => 'disabled', 'type' => 'module']);
        $out = $this->getOutput();
        $this->assertContains('devel', $out);
    }
}
