<?php

namespace Drush\Generate;

use DrupalCodeGenerator\Commands\BaseGenerator;

class DrushCommand extends BaseGenerator
{
    protected $name = 'gen-command';
    protected $description = 'Generates a Drush commandfile';

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output) {

      $questions = [
        'name' => ['Foo name', [$this, 'defaultName']],
        'machine_name' => ['Foo machine name', [$this, 'defaultMachineName']],
      ];

      $vars = $this->collectVars($input, $output, $questions);

      $this->files['Foo.php'] = $this->render('DrushCommand.twig', $vars);
      // $this->files['drush.services.yml'] = $this->render('DrushCommand.twig', $vars);
    }
}