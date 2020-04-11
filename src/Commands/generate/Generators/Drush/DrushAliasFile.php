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
    protected function generate(): void
    {
        $vars = &$this->vars;
        $vars['prefix'] = $this->ask('File prefix (one word)', 'self');
        $vars['root'] = $this->ask('Path to Drupal root', Drush::bootstrapManager()->getRoot());
        $vars['uri'] = $this->ask('Drupal uri', Drush::bootstrapManager()->getUri());
        $vars['host'] = $this->ask('Remote host');

        if ($vars['host']) {
            $vars['user'] = $this->ask('Remote user', Drush::config()->user());
        }

        $this->addFile('drush/{prefix}.site.yml')
            ->template('drush-alias-file.yml');
    }

}
