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

  function testConfigExportImport() {
    $options = $this->options();
    // Get path to sync dir.
    $this->drush('core-status', array(), $options + array('format' => 'json', 'fields' => 'config-sync'));
    $sync = $this->webroot() . '/' . $this->getOutputFromJSON('config-sync');
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
    $partial_path = self::getSandbox() . '/partial';
    mkdir($partial_path);
    $contents = file_put_contents($partial_path. '/system.site.yml', $contents);
    $this->drush('config-import', array(), $options + array('partial' => NULL, 'source' => $partial_path));
    $this->drush('config-get', array('system.site', 'page'), $options + array('format' => 'json'));
    $page = $this->getOutputFromJSON('system.site:page');
    $this->assertContains('unish partial', $page->front, '--partial was successfully imported.');

    $this->drush('pm-enable', array('tracker'), $options);
    $ignored_modules = array('skip-modules' => 'tracker');

    // Run config-export again - note that 'tracker' is enabled, but we
    // are going to ignore it on write, so no changes should be written
    // to core.extension when it is exported.
    $this->drush('config-export', array(), $options + $ignored_modules);
    $this->assertFileExists($core_extension_yml);
    $contents = file_get_contents($core_extension_yml);
    $this->assertNotContains('tracker', $contents);

    // Run config-import again, but ignore 'tracker' when importing.
    // It is not presently in the exported configuration, because we enabled
    // it after export.  If we imported again without adding 'tracker' with
    // 'skip-modules' option, then it would be disabled.
    $this->drush('config-import', array(), $options + $ignored_modules);
    $this->drush('config-get', array('core.extension', 'module'), $options + array('format' => 'yaml'));
    $modules = $this->getOutput();
    $this->assertContains('tracker', $modules, 'Tracker module appears in extension list after import, as it should.');

    // Run config-export one final time.  'tracker' is still enabled, even
    // though it was ignored in the previous import/export operations.
    // When we remove the skip-modules option, then 'tracker' will
    // be exported.
    $this->drush('config-export', array(), $options);
    $this->assertFileExists($core_extension_yml);
    $contents = file_get_contents($core_extension_yml);
    $this->assertContains('tracker', $contents);
  }

  function options() {
    return array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
  }
}
