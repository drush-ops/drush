<?php

/**
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 *
 * @group commands
 */
class ConfigCase extends Drush_CommandTestCase {

  function testConfig() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Config only available on D8+.');
    }

    $sites = $this->setUpDrupal(1, TRUE, '8');
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    // Include test site's settings file to get the config directories.
    include $options['root'] . '/sites/' . key($sites) . '/settings.php';

    $this->drush('config-set', array('system.site', 'name', 'config_test'), $options);
    $this->drush('config-get', array('system.site', 'name'), $options);
    $this->assertEquals("system.site:name: 'config_test'", $this->getOutput(), 'Config was successfully set and get.');

    // @todo, test that config-view or similar matches what filesystem says.
    //$this->drush('config-view', array(), $options);
    //$system_site_file = $options['root'] . '/sites/' . key($sites) . '/files/' . $config_directories['staging']['path'] . '/system.site.yml';
    //$this->assertFileExists($system_site_file);
    //$this->drush('config-get', array('system.site'), $options);
    //$config_view_yaml = $this->getOutput();
    //$this->assertEquals($config_view_yaml, file_get_contents($system_site_file), 'Config-view displays YAML that matches the config management system.');

    $this->drush('config-list', array(), $options);
    $result = $this->getOutputAsList();
    $this->assertNotEmpty($result, 'An array of config names was returned.');
    $this->assertTrue(in_array('update.settings', $result), 'update.settings name found in the config names.');

    $this->drush('config-list', array('system'), $options);
    $result = $this->getOutputAsList();
    $this->assertTrue(in_array('system.site', $result), 'system.site found in list of config names with "system" prefix.');

    $this->drush('config-list', array('system'), $options += array('format' => 'json'));
    $result = $this->getOutputFromJSON();
    $this->assertNotEmpty($result, 'Valid, non-empty JSON output was returned.');
  }
}
