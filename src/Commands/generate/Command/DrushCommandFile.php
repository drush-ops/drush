<?php

namespace Drush\Commands\generate\Command;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;

class DrushCommandFile extends BaseGenerator
{
  protected $name = 'drush-commandfile';
  protected $description = 'Generates a Drush commandfile.';
  protected $alias = 'dcf';
  protected $templatePath = __DIR__;

  /**
   * {@inheritdoc}
   */
  protected function interact($input, $output) {

    $questions = Utils::defaultQuestions();

    $vars = $this->collectVars($input, $output, $questions);
    $vars['class'] = Utils::camelize($vars['machine_name'] . 'Commands');
    // $directoryBaseName = basename($this->destination);
    $this->files['src/Commands/' . $vars['class'] . '.php'] = $this->render('drushcommandfile.twig', $vars);
    // @todo Can only generate a file named [module].services.yml right now.
    // $this->files['drush.services.yml'] = $this->render('drush.services.twig', $vars);
  }
}
