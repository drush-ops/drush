<?php

namespace Drupal\woot\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;

class ExampleGenerator extends BaseGenerator
{
    protected $name = 'woot-example';
    protected $description = 'Generates a woot.';
    protected $alias = 'wootex';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function interact($input, $output) {

        $questions = Utils::defaultQuestions();

        $vars = $this->collectVars($input, $output, $questions);
        $vars['class'] = Utils::camelize('Example_' . $vars['machine_name'] . '_Commands');
        $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('example-generator.twig', $vars);
    }
}