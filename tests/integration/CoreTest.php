<?php

namespace Unish;

use Symfony\Component\Yaml\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Tests for core commands.
 *
 * @group commands
 */
class CoreTest extends UnishIntegrationTestCase
{
    public function testCoreRequirements()
    {
        $root = $this->webroot();
        $options = [
        'ignore' => 'cron,http requests,update,update_core,trusted_host_patterns', // no network access when running in tests, so ignore these
        // 'strict' => 0, // invoke from script: do not verify options
        ];
        // Verify that there are no severity 2 items in the status report
        $this->drush('core-requirements', [], $options + ['severity' => '2', 'pipe' => true]);
        $output = $this->getOutput();
        $this->assertEquals('', $output);

        $this->drush('core-requirements', [], $options + ['format' => 'json']);
        $loaded = $this->getOutputFromJSON();
        // Pick a subset that are valid for D6/D7/D8.
        $expected = [
        // 'install_profile' => -1,
        // 'node_access' => -1,
        'php' => -1,
        // 'php_extensions' => -1,
        'php_memory_limit' => -1,
        'php_register_globals' => -1,
        'settings.php' => -1,
        ];
        foreach ($expected as $key => $value) {
            if (isset($loaded[$key])) {
                $this->assertEquals("{$key}={$value}", "{$key}=" . $loaded[$key]['sid']);
            }
        }
    }

    public function testDrupalDirectory()
    {
        $root = $this->webroot();
        $sitewide = $this->drupalSitewideDirectory();

        if ($this->isWindows()) {
            $this->markTestSkipped('Windows escpaping woes.');
        }

        $this->drush('drupal-directory', ['%files']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/sites/default/files'), $output);

        $this->drush('drupal-directory', ['%modules']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, $sitewide . '/modules'), $output);

        $this->drush('pm-enable', ['drush_empty_module']);
        $this->drush('theme-enable', ['drush_empty_theme']);

        $this->drush('drupal-directory', ['drush_empty_module']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/modules/unish/drush_empty_module'), $output);

        $this->drush('drupal-directory', ['drush_empty_theme']);
        $output = $this->getOutput();
        $this->assertEquals(Path::join($root, '/themes/unish/drush_empty_theme'), $output);
    }
}
