<?php

namespace Drush\Commands\generate\Generators\Migrate;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements `generate migration` command.
 */
class MigrationGenerator extends BaseGenerator
{

    protected $name = 'migration';
    protected $description = 'Generates the yml and PHP class for a Migration';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions = Utils::defaultQuestions() + Utils::defaultPluginQuestions() + [
            'migration_group' => ['Migration group', 'default'],
            'destination_plugin' => ['Destination plugin', 'entity:node'],
        ];

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['plugin_label']);

        $plugin_path = 'src/Plugin/migrate/source/' . $vars['class'] . '.php';
        $this->setFile($plugin_path, 'migration.twig', $vars);
        $migration_path = 'config/install/migrate_plus.migration.' . $vars['plugin_id'] . '.yml';
        $this->setFile($migration_path, 'migration.yml.twig', $vars);
    }
}
