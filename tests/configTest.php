<?php

namespace Unish;

/**
 * Tests for Configuration Management commands for D8+.
 * @group commands
 * @group config
 */
class ConfigCase extends CommandUnishTestCase {

  function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Config only available on D8+.');
    }

    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
      $this->drush('pm-enable', array('config'), $this->options());
    }
  }

  function testConfigGetSet() {
    $options = $this->options();
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $options);
    $this->drush('config-get', array('system.site', 'name'), $options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config was successfully set and get.');
  }

  function testConfigList() {
    $options = $this->options();
    $this->drush('config-list', array(), $options);
    $result = $this->getOutputAsList();
    $this->assertNotEmpty($result, 'An array of config names was returned.');
    $this->assertTrue(in_array('update.settings', $result), 'update.settings name found in the config names.');

    $this->drush('config-list', array('system'), $options);
    $result = $this->getOutputAsList();
    $this->assertTrue(in_array('system.site', $result), 'system.site found in list of config names with "system" prefix.');

    $this->drush('config-list', array('system'), $options + array('format' => 'json'));
    $result = $this->getOutputFromJSON();
    $this->assertNotEmpty($result, 'Valid, non-empty JSON output was returned.');
  }

  function testConfigExportImport() {
    $options = $this->options();
    // Get path to sync dir.
    $this->drush('core-status', array('config-sync'), $options + array('format' => 'json'));
    $sync_relative_path = $this->getOutputFromJSON('config-sync');
    $this->assertNotEmpty($sync_relative_path);
    $sync = $this->webroot() . '/' . $sync_relative_path;
    $system_site_yml = $sync . '/system.site.yml';
    $core_extension_yml = $sync . '/core.extension.yml';

    // Test export
    $this->drush('config-export', array(), $options);
    $this->assertFileExists($system_site_yml);

    // Test import by finishing the round trip.
    $contents = file_get_contents($system_site_yml);
    $contents = preg_replace('/front: .*/', 'front: unish', $contents);
    $contents = file_put_contents($system_site_yml, $contents);
    $this->drush('config-import', array(), $options);
    $this->drush('config-get', array('system.site', 'page'), $options + array('format' => 'json'));
    $page = $this->getOutputFromJSON('system.site:page');
    $this->assertContains('unish', $page->front, 'Config was successfully imported.');

    // Similar, but this time via --partial option.
    $contents = file_get_contents($system_site_yml);
    $contents = preg_replace('/front: .*/', 'front: unish partial', $contents);
    $partial_path = UNISH_SANDBOX . '/partial';
    mkdir($partial_path);
    $contents = file_put_contents($partial_path. '/system.site.yml', $contents);
    $this->drush('config-import', array(), $options + array('partial' => NULL, 'source' => $partial_path));
    $this->drush('config-get', array('system.site', 'page'), $options + array('format' => 'json'));
    $page = $this->getOutputFromJSON('system.site:page');
    $this->assertContains('unish partial', $page->front, '--partial was successfully imported.');
  }

  function options() {
    return array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
  }
}
