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

        $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('drush-command-file.twig', $vars);
        $this->services[$vars['machine_name'] . '.commands'] = [
            'class' => '\Drupal\\' . $vars['machine_name'] . '\Commands\\' . $vars['class'],
            'tags' => [
                [
                    'name' => 'drush.command',
                ],
            ],
        ];
    }
}
