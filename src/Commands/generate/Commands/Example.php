<?php

namespace Drush\Commands\generate\Commands;

use DrupalCodeGenerator\Commands\BaseGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements example command.
 */
class Example extends BaseGenerator {

  protected $name = 'example';
  protected $description = 'Example generator provided by Drush';
  protected $templatePath = __DIR__;

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $questions = [
      'value' => ['Value', 'test'],
    ];
    $vars = $this->collectVars($input, $output, $questions);
    $this->files['example.txt'] = $this->render('example.twig', $vars);
  }

}
