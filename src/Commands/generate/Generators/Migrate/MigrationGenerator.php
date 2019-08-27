<?php

namespace Drush\Commands\generate\Generators\Migrate;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

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
        $questions = Utils::defaultPluginQuestions() + [
            'migration_group' => new Question('Migration group', 'default'),
            'destination_plugin' => new Question('Destination plugin', 'entity:node'),
        ];

        $vars = &$this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['plugin_label']);

        $this->addFile()
            ->path('src/Plugin/migrate/source/{class}.php')
            ->template('migration.php.twig');

        $this->addFile()
            ->path('config/install/migrate_plus.migration.{plugin_id}.yml')
            ->template('migration.yml.twig');
    }
}
