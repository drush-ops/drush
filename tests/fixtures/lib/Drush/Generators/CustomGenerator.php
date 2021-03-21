<?php

namespace Custom\Library\Drush\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomGenerator extends BaseGenerator
{

    protected $name = 'custom-testing-generator';
    protected $description = 'Custom testing generator';

    public function interact(InputInterface $input, OutputInterface $output)
    {
        $this->addFile('drush/foo.bar');
    }

}
