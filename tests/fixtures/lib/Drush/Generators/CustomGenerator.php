<?php

namespace Custom\Library\Drush\Generators;

use DrupalCodeGenerator\Command\Generator;

class CustomGenerator extends Generator
{
    protected string $name = 'custom-testing-generator';
    protected string $description = 'Custom testing generator';

    public function generate(&$vars): void
    {
        $this->addFile('drush/foo.bar');
    }
}
