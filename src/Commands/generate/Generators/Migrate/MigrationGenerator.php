<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Migrate;

use DrupalCodeGenerator\Asset\Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;

#[Generator(
    name: 'migration',
    description: 'Generates the yml and PHP class for a Migration',
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class MigrationGenerator extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);

        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();

        $vars['plugin_label'] = $ir->askPluginLabel();
        $vars['plugin_id'] = $ir->askPluginId();
        $vars['class'] = $ir->askClass();

        $vars['destination_plugin'] = $ir->ask('Destination plugin', 'entity:node');

        $assets->addFile('src/Plugin/migrate/source/{class}.php', 'migration.php.twig');
        $assets->addFile('migrations/{plugin_id}.yml', 'migration.yml.twig');
    }
}
