<?php

namespace Drush\Commands\generate\Generators\Migrate;

use DrupalCodeGenerator\Command\Plugin\PluginGenerator;

/**
 * Implements `generate migration` command.
 */
class MigrationGenerator extends PluginGenerator
{

    protected $name = 'migration';
    protected $description = 'Generates the yml and PHP class for a Migration';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function generate(): void
    {
        $vars = &$this->collectDefault();
        $vars['migration_group'] = $this->ask('Migration group', 'default');
        $vars['destination_plugin'] = $this->ask('Destination plugin', 'entity:node');

        $this->addFile('src/Plugin/migrate/source/{class}.php')
            ->template('migration.php');

        $this->addFile('config/install/migrate_plus.migration.{plugin_id}.yml')
            ->template('migration.yml');
    }

}
