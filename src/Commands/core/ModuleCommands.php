<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;

/**
 * Module-related commands.
 */
final class ModuleCommands extends DrushCommands
{
    use AutowireTrait;

    const SCHEMA_SET = 'module:schema-set';

  /**
   * Set the schema version for a module.
   *
   * @throws \Exception
   */
    #[CLI\Command(name: self::SCHEMA_SET, aliases: ['mss'])]
    #[CLI\Argument(name: 'module', description: 'The machine name of the module.')]
    #[CLI\Argument(name: 'version', description: 'The schema version to set.')]
    #[CLI\Usage(
        name: 'drush module:schema-set system 8000',
        description: 'Set the schema version for system module to 8000.'
    )]
    #[CLI\Usage(name: 'drush mss custom_module 8001', description: 'Set the schema version for custom_module to 8001.')]
    public function schemaSet(string $module, int $version): void
    {

      // Check if the module exists
        if (!\Drupal::moduleHandler()->moduleExists($module)) {
            throw new \Exception(dt('Module @module does not exist or is not installed.', ['@module' => $module]));
        }

        // Set the schema version
        try {
            \Drupal::service('update.update_hook_registry')->setInstalledVersion($module, $version);
            $this->logger()->success(dt('Schema version for module @module set to @version.', [
                '@module' => $module,
                '@version' => $version,
            ]));
        } catch (\Exception $e) {
            throw new \Exception(dt('Could not set schema version for module @module: @error', [
                '@module' => $module,
                '@error' => $e->getMessage(),
            ]));
        }
    }
}
