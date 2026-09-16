<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\ModuleCommands;
use Drush\Commands\core\PhpCommands;
use Drush\Commands\pm\PmCommands;

/**
 * @group commands
 */
class ModuleSchemaTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests the module:schema-set command.
     */
    public function testModuleSchemaSet()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];

        // Install a test module
        $this->drush(PmCommands::INSTALL, ['drush_empty_module'], $options);

        // Set the schema version to 8001
        $this->drush(ModuleCommands::SCHEMA_SET, ['drush_empty_module', '8001'], $options);

        // Verify the schema version was set correctly
        $this->drush(
            PhpCommands::EVAL,
            [
            'echo \Drupal::service("update.update_hook_registry")->getInstalledVersion("drush_empty_module");'
            ],
            $options
        );
        $this->assertEquals('8001', trim($this->getOutput()));

        // Set the schema version to a different value
        $this->drush(ModuleCommands::SCHEMA_SET, ['drush_empty_module', '8005'], $options);

        // Verify the schema version was updated
        $this->drush(
            PhpCommands::EVAL,
            ['echo \Drupal::service("update.update_hook_registry")->getInstalledVersion("drush_empty_module");'],
            $options
        );
        $this->assertEquals('8005', trim($this->getOutput()));

        // Test with a non-existent module
        $this->drush(
            ModuleCommands::SCHEMA_SET,
            ['non_existent_module', '8001'],
            $options,
            null,
            null,
            self::EXIT_ERROR
        );
        $this->assertStringContainsString(
            'Module non_existent_module does not exist or is not installed',
            $this->getErrorOutput()
        );
    }
}
