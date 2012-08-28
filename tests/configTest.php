<?php

/*
 * @file
 *   Tests for enable, disable, uninstall, pm-list commands.
 */
class ConfigCase extends Drush_CommandTestCase {

  function testConfig() {
    $sites = $this->setUpDrupal(1, TRUE, '8');
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    // Include test site's settings file to get the config directory.
    include $options['root'] . '/sites/' . key($sites) . '/settings.php';

    $this->drush('config-set', array('system.site', 'name', 'config_test'), $options);
    $this->drush('config-get', array('system.site', 'name'), $options);
    $this->assertEquals('system.site:name: "config_test"', $this->getOutput(), 'Config was successfully set and get.');

    $this->drush('config-export', array(), $options);
    $system_site_file = $options['root'] . '/sites/' . key($sites) . '/files/' . $config_directory_name . '/system.site.yml';
    $this->assertFileExists($system_site_file);
    $this->drush('config-get', array('system.site'), $options);
    $config_view_yaml = $this->getOutput();
    $this->assertEquals($config_view_yaml, file_get_contents($system_site_file), 'Config-view creates YAML that matches the config management system.');

  }
}
