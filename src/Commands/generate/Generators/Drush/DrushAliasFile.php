<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\Generator;
use Drush\Drush;

/**
 * Implements drush-alias-file command.
 */
class DrushAliasFile extends Generator
{

    protected $name = 'drush-alias-file';
    protected $description = 'Generates a Drush site alias file.';
    protected $alias = 'daf';
    protected $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars): void
    {
        // @todo Update this.
    }

}
