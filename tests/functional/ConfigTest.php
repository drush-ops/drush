<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Serialization\Yaml;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\core\StatusCommands;
use Drush\Commands\config\ConfigCommands;
use Drush\Commands\config\ConfigExportCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\core\StateCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for Configuration Management commands.
 *
 * @group commands
 * @group config
 */
class ConfigTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    public function setup(): void
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            // Field module is needed for now for --existing-config. It is not actually
            // enabled after testing profile is installed. Its required by file and update though.
            $this->drush(PmCommands::INSTALL, ['config, field']);
        }
    }

    /**
     * @todo If this becomes an integration test, add test for stdin handling.
     */
    public function testConfigGetSet()
    {
        // Simple value
        $this->drush(ConfigCommands::SET, ['system.site', 'name', 'config_test']);
        $this->drush(ConfigCommands::GET, ['system.site', 'name']);
        $this->assertEquals("'system.site:name': config_test", $this->getOutput());

        // Nested value
        $this->drush(ConfigCommands::SET, ['system.site', 'page.front', 'llama']);
        $this->drush(ConfigCommands::GET, ['system.site', 'page.front']);
        $this->assertEquals("'system.site:page.front': llama", $this->getOutput());

        // Simple sequence value
        $this->drush(ConfigCommands::SET, ['user.role.authenticated', 'permissions', '[foo,bar]'], ['input-format' => 'yaml']);
        $this->drush(ConfigCommands::GET, ['user.role.authenticated', 'permissions'], ['format' => 'json']);
        $output = $this->getOutputFromJSON('user.role.authenticated:permissions');

        // Mapping value
        $this->drush(ConfigCommands::SET, ['system.site', 'page', "{403: '403', front: home}"], ['input-format' => 'yaml']);
        $this->drush(ConfigCommands::GET, ['system.site', 'page'], ['format' => 'json']);
        $output = $this->getOutputFromJSON('system.site:page');
        $this->assertSame(['403' => '403', 'front' => 'home'], $output);

        // Multiple top-level keys
        $this->drush(ConfigCommands::SET, ['user.role.authenticated', '?', "{label: 'Auth user', weight: 5}"], ['input-format' => 'yaml']);
        $this->drush(ConfigCommands::GET, ['user.role.authenticated'], ['format' => 'json']);
        $output = $this->getOutputFromJSON();
        $this->assertSame('Auth user', $output['label']);
        $this->assertSame(5, $output['weight']);
    }

    public function testConfigExportImportStatusExistingConfig()
    {
        $system_site_yml = $this->getConfigSyncDir() . '/system.site.yml';

        // Test export.
        $this->drush(ConfigExportCommands::EXPORT);
        $this->assertFileExists($system_site_yml);

        // Test import and status by finishing the round trip.
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish', $contents);
        file_put_contents($system_site_yml, $contents);

        // Test status of changed configuration.
        $this->drush(ConfigCommands::STATUS);
        $this->assertStringContainsString('system.site', $this->getOutput(), 'config:status correctly reports changes.');

        // Test import.
        $this->drush(ConfigImportCommands::IMPORT);
        $this->drush(ConfigCommands::GET, ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertStringContainsString('unish', $page['front'], 'Config was successfully imported.');

        // Test status of identical configuration, in different formatters.
        $expected_output = [
            'list' => '',
            'table' => '',
            'json' => '[]',
            'xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<document/>
XML
        ];
        foreach ($expected_output as $formatter => $output) {
            $this->drush(ConfigCommands::STATUS, [], ['format' => $formatter]);
            $this->assertEquals($output, $this->getOutput(), 'config:status correctly reports identical config.');
        }

        // Test the --existing-config option for site:install.
        $this->drush(StatusCommands::STATUS, [], ['field' => 'drupal-version']);
        $drupal_version = $this->getOutputRaw();
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish existing', $contents);
        file_put_contents($system_site_yml, $contents);
        $this->installDrupal('dev', true, ['existing-config' => true], false);
        $this->drush(ConfigCommands::GET, ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertStringContainsString('unish existing', $page['front'], 'Existing config was successfully imported during site:install.');

        // Similar, but this time via --partial option.
        $contents = file_get_contents($system_site_yml);
        $contents = preg_replace('/front: .*/', 'front: unish partial', $contents);
        $partial_path = self::getSandbox() . '/partial';
        $this->mkdir($partial_path);
        $contents = file_put_contents($partial_path . '/system.site.yml', $contents);
        $this->drush(ConfigImportCommands::IMPORT, [], ['partial' => null, 'source' => $partial_path]);
        $this->drush(ConfigCommands::GET, ['system.site', 'page'], ['format' => 'json']);
        $page = $this->getOutputFromJSON('system.site:page');
        $this->assertStringContainsString('unish partial', $page['front'], '--partial was successfully imported.');
    }

    public function testConfigImport()
    {
        $options = [
            'include' => __DIR__,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Export the configuration.
        $this->drush(ConfigExportCommands::EXPORT);

        $root = $this->webroot();

        // Introduce a new service in the Woot module that depends on a service
        // in the Devel module (which is not yet enabled).
        $filename = Path::join($root, self::WOOT_SERVICES_PATH);
        copy($filename, $filename . '.BAK');
        $serviceDefinition = <<<YAML_FRAGMENT
  woot.depending_service:
    class: Drupal\woot\DependingService
    arguments: ['@drush_empty_module.service']
YAML_FRAGMENT;
        file_put_contents($filename, $serviceDefinition, FILE_APPEND);

        $filename = Path::join($root, self::WOOT_INFO_PATH);
        copy($filename, $filename . '.BAK');
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

        // When importing config, the 'woot' module should warn about a validation error.
        $this->drush(ConfigImportCommands::IMPORT, [], [], null, null, CommandUnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString("woot config error", $this->getErrorOutput(), 'Woot returned an expected config validation error.');

        // Now we disable the error, and retry the config import.
        $this->drush(StateCommands::SET, ['woot.shoud_not_fail_on_cim', 'true']);
        $this->drush(ConfigImportCommands::IMPORT);
        $this->drush(PhpCommands::EVAL, ["return Drupal::getContainer()->getParameter('container.modules')"], ['format' => 'json']);

        // Assure that new modules are fully enabled.
        $out = $this->getOutputFromJSON();
        $this->assertArrayHasKey('woot', $out);
        $this->assertArrayHasKey('drush_empty_module', $out);

        // We make sure that the service inside the newly enabled module exists now. A fatal
        // error will be thrown by Drupal if the service does not exist.
        $this->drush(PhpCommands::EVAL, ['Drupal::service("drush_empty_module.service");']);
    }

    protected function getConfigSyncDir()
    {
        $this->drush(StatusCommands::STATUS, [], ['format' => 'json', 'fields' => 'config-sync']);
        return $this->webroot() . '/' . $this->getOutputFromJSON('config-sync');
    }

    protected function tearDown(): void
    {
        // Undo our yml mess.
        $filenames = [
            Path::join($this->webroot(), self::WOOT_INFO_PATH),
            Path::join($this->webroot(), self::WOOT_SERVICES_PATH),
        ];
        foreach ($filenames as $filename) {
            if (file_exists($filename . '.BAK')) {
                rename($filename . '.BAK', $filename);
            }
        }
        parent::tearDown();
    }
}
