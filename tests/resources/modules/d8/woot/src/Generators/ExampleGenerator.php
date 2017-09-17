<?php

namespace Drupal\woot\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleGenerator extends BaseGenerator
{
    protected $name = 'woot-example';
    protected $description = 'Generates a woot.';
    protected $alias = 'wootex';
    protected $templatePath = __DIR__;

    // We don't actually use this service. This illustrates how to inject a dependency into a Generator.
    protected $moduleHandler;

    public function __construct($moduleHandler = null, $name = null)
    {
        parent::__construct($name);
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output) {

        $questions = Utils::defaultQuestions();

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize('Example_' . $vars['machine_name'] . '_Commands');
        $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('example-generator.twig', $vars);
    }
}
