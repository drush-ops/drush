<?php

namespace Unish;

/**
 * Tests for Configuration Management commands for D8+.
 * @group commands
 */
class ConfigCase extends CommandUnishTestCase {

  function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Config only available on D8+.');
    }

    if (!$this->getSites()) {
      // Remove the '.x' once there is a stable release.
      $this->setUpDrupal(1, TRUE, '8.0.x');
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
    // Get path to staging dir.
    $this->drush('core-status', array(), $options + array('format' => 'json'));
    $staging = $this->webroot() . '/' . $this->getOutputFromJSON('config-staging');
    $system_site_yml = $staging . '/system.site.yml';

    // Test export
    $this->drush('config-export', array(), $options);
    $this->assertFileExists($system_site_yml);

    // Test import by finish the round trip.
    $contents = file_get_contents($system_site_yml);
    $contents = str_replace('front: user', 'front: unish', $contents);
    $contents = file_put_contents($system_site_yml, $contents);
    $this->drush('config-import', array(), $options);
    $this->drush('config-get', array('system.site', 'page'), $options + array('format' => 'json'));
    $page = $this->getOutputFromJSON('system.site:page');
    $this->assertContains('unish', $page->front, 'Config was successfully imported.');
  }

  function options() {
    return array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
  }
}
