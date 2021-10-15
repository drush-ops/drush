<?php

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Command\Generator;
use Drush\Drush;

/**
 * Implements drush-alias-file command.
 */
class DrushAliasFile extends Generator
{
    protected string $name = 'drush:alias-file';
    protected string $description = 'Generates a Drush site alias file.';
    protected string $alias = 'daf';
    protected string $templatePath = __DIR__;

    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars): void
    {
        $vars['prefix'] = $this->ask('File prefix (one word)', 'self');
        $vars['root'] = $this->ask('Path to Drupal root', Drush::bootstrapManager()->getRoot());
        $vars['uri'] = $this->ask('Drupal uri', Drush::bootstrapManager()->getUri());
        $vars['host'] = $this->ask('Remote host');

        if ($vars['host']) {
            $vars['user'] = $this->ask('Remote user', Drush::config()->user());
        }

        $this->addFile('drush/{prefix}.site.yml', 'drush-alias-file.yml.twig');
    }
}
