<?php

namespace Drush\Generate\Commands;

use DrupalCodeGenerator\Commands\BaseGenerator;
use DrupalCodeGenerator\Utils;

class DrushCommandFile extends BaseGenerator
{
    protected $name = 'gen-drush-command';
    protected $description = 'Generates a Drush commandfile.';

    /**
     * {@inheritdoc}
     */
    protected function interact($input, $output) {

      $questions = Utils::defaultQuestions();

      $vars = $this->collectVars($input, $output, $questions);
      $vars['class'] = Utils::human2class($vars['machine_name'] . 'Commands');
      $directoryBaseName = basename($this->destination);
      $this->files['src/Commands/' . ucwords($directoryBaseName) . 'Commands.php'] = $this->render('drushcommand.twig', $vars);
      // @todo Can only generate a file named [module].services.yml right now.
      // $this->files['drush.services.yml'] = $this->render('drushservices.twig', $vars);
    }
}