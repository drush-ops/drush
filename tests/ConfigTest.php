<?php

namespace Unish;

/**
 * Tests for Configuration Management commands for D8+.
 * @group commands
 * @group config
 */
class ConfigCase extends CommandUnishTestCase
{

    public function setUp()
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            $this->drush('pm-enable', ['config']);
        }
    }

    public function testConfigGetSet()
    {
        $this->drush('config-set', ['system.site', 'name', 'config_test']);
        $this->drush('config-get', ['system.site', 'name']);
        $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config was successfully set and get.');
    }

    public function testConfigExportImportStatus()
    {
        // Get path to sync dir.
        $this->drush('core-status', [], ['format' => 'json', 'fields' => 'config-sync']);
        $sync = $this->webroot() . '/' . $this->getOutputFromJSON('config-sync');
        $system_site_yml = $sync . '/system.site.yml';
        $core_extension_yml = $sync . '/core.extension.yml';

        // Test export.
        $this->drush('config-export');
        $this->assertFileExists($system_site_yml);

        // Test import and status by finishing the round trip.
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish', $contents);
        $contents = file_put_contents($system_site_yml, $contents);
    
        // Test status of changed configuration.
        $this->drush('config:status');
        $this->assertContains('system.site', $this->getOutput(), 'config:status correctly reports changes.');
    
        // Test import.
        $this->drush('config-import');
        $this->drush('config-get', ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertContains('unish', $page->front, 'Config was successfully imported.');

        // Test status of identical configuration.
        $this->drush('config:status', [], ['format' => 'list']);
        $this->assertEquals('', $this->getOutput(), 'config:status correctly reports identical config.');
      
        // Similar, but this time via --partial option.
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish partial', $contents);
        $partial_path = self::getSandbox() . '/partial';
        $this->mkdir($partial_path);
        $contents = file_put_contents($partial_path. '/system.site.yml', $contents);
        $this->drush('config-import', [], ['partial' => null, 'source' => $partial_path]);
        $this->drush('config-get', ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertContains('unish partial', $page->front, '--partial was successfully imported.');
    }
}
