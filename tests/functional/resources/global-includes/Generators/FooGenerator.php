<?php

namespace Drush\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FooGenerator extends BaseGenerator
{
    protected $name = 'foo-example';
    protected $description = 'Generates a foo.';
    protected $alias = 'foo';
    protected $templatePath = __DIR__;

    /**
     * {@inheritDoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->addFile()
            ->path('foo.php')
            ->template('foo.twig');
    }
}
