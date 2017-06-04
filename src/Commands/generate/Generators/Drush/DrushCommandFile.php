<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements drush-command-file command.
 */
class DrushCommandFile extends BaseGenerator
{

    protected $name = 'drush-command-file';
    protected $description = 'Generates a Drush command file.';
    protected $alias = 'dcf';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questions = Utils::defaultQuestions();

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize($vars['machine_name'] . 'Commands');

        $this->setFile('src/Commands/' . $vars['class'] . '.php', 'drush-command-file.twig', $vars);
        $this->setServicesFile('drush.services.yml', 'drush.services.twig', $vars);
    }
}
