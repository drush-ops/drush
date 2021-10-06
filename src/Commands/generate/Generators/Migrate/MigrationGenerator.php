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
    protected function generate(array &$vars): void
    {
        $this->collectDefault($vars);
        $vars['destination_plugin'] = $this->ask('Destination plugin', 'entity:node');
        $this->addFile('src/Plugin/migrate/source/{class}.php', 'migration.php.twig');
        $this->addFile('migrations/{plugin_id}.yml', 'migration.yml.twig');
    }

}
