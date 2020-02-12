<?php

namespace Unish;

use Composer\Semver\Comparator;
use Drupal\Core\Serialization\Yaml;
use Webmozart\PathUtil\Path;

/**
 * Tests for Configuration Management commands for D8+.
 * @group commands
 * @group config
 */
class ConfigCase extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    public function setUp()
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            // Field module is needed for now for --existing-config. It is not actually
            // enabled after testing profile is installed. Its required by file and update though.
            $this->drush('pm:enable', ['config, field']);
        }
    }

    public function testConfigGetSet()
    {
        $this->drush('config:set', ['system.site', 'name', 'config_test']);
        $this->drush('config:get', ['system.site', 'name']);
        $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config was successfully set and get.');
    }

    public function testConfigExportImportStatusExistingConfig()
    {
        $system_site_yml = $this->getConfigSyncDir() . '/system.site.yml';

        // Test export.
        $this->drush('config-export');
        $this->assertFileExists($system_site_yml);

        // Test import and status by finishing the round trip.
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish', $contents);
        file_put_contents($system_site_yml, $contents);

        // Test status of changed configuration.
        $this->drush('config:status');
        $this->assertContains('system.site', $this->getOutput(), 'config:status correctly reports changes.');

        // Test import.
        $this->drush('config-import');
        $this->drush('config-get', ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertContains('unish', $page['front'], 'Config was successfully imported.');

        // Test status of identical configuration.
        $this->drush('config:status', [], ['format' => 'list']);
        $this->assertEquals('', $this->getOutput(), 'config:status correctly reports identical config.');

        // Test the --existing-config option for site:install.
        $this->drush('core:status', [], ['field' => 'drupal-version']);
        $drupal_version = $this->getOutputRaw();
        if (Comparator::greaterThanOrEqualTo($drupal_version, '8.6')) {
            $contents = file_get_contents($system_site_yml);
            $contents = preg_replace('/front: .*/', 'front: unish existing', $contents);
            file_put_contents($system_site_yml, $contents);
            $this->installDrupal('dev', true, ['existing-config' => true], false);
            $this->drush('config-get', ['system.site', 'page'], ['format' => 'json']);
            $page = $this->getOutputFromJSON('system.site:page');
            $this->assertContains('unish existing', $page['front'], 'Existing config was successfully imported during site:install.');
        }

        // Similar, but this time via --partial option.
        if ($this->isDrupalGreaterThanOrEqualTo('8.8.0')) {
            $this->markTestSkipped('Partial config import not yet working on 8.8.0');
        }
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish partial', $contents);
        $partial_path = self::getSandbox() . '/partial';
        $this->mkdir($partial_path);
        $contents = file_put_contents($partial_path. '/system.site.yml', $contents);
        $this->drush('config-import', [], ['partial' => null, 'source' => $partial_path]);
        $this->drush('config-get', ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertContains('unish partial', $page['front'], '--partial was successfully imported.');
    }

    public function testConfigImport()
    {
        $options = [
            'include' => __DIR__,
        ];
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Export the configuration.
        $this->drush('config:export');

        $root = $this->webroot();

        // Introduce a new service in the Woot module that depends on a service
        // in the Devel module (which is not yet enabled).
        $filename = Path::join($root, 'modules/unish/woot/woot.services.yml');
        $serviceDefinition = <<<YAML_FRAGMENT
  woot.depending_service:
    class: Drupal\woot\DependingService
    arguments: ['@drush_empty_module.service']
YAML_FRAGMENT;
        file_put_contents($filename, $serviceDefinition, FILE_APPEND);

        $filename = Path::join($root, 'modules/unish/woot/woot.info.yml');
        $moduleDependency = <<<YAML_FRAGMENT
dependencies:
  - drush_empty_module
YAML_FRAGMENT;
        file_put_contents($filename, $moduleDependency, FILE_APPEND);

        // Add the 'drush_empty_module' module in core.extension.yml.
        $extensionFile = $this->getConfigSyncDir() . '/core.extension.yml';
        $this->assertFileExists($extensionFile);
        $extension = Yaml::decode(file_get_contents($extensionFile));
        $extension['module']['drush_empty_module'] = 0;
        require_once $root . "/core/includes/module.inc";
        $extension['module'] = module_config_sort($extension['module']);
        file_put_contents($extensionFile, Yaml::encode($extension));

        $this->drush('config:import');
    }

    protected function getConfigSyncDir()
    {
        $this->drush('core:status', [], ['format' => 'json', 'fields' => 'config-sync']);
        return $this->webroot().'/'.$this->getOutputFromJSON('config-sync');
    }
}
