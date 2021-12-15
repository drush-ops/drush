<?php

namespace Custom\Library\Drush\Generators;

use DrupalCodeGenerator\Command\Generator;

class CustomGenerator extends Generator
{
    protected string $name = 'drush:testing-generator';
    protected string $description = 'An internal generator used for tests';

    public function generate(&$vars): void
    {
        $this->addFile('drush/foo.bar');
    }
}
