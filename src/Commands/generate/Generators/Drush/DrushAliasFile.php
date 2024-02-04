<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Drush;

#[Generator(
    name: 'drush:alias-file',
    description: 'Generates a Drush site alias file.',
    aliases: ['daf'],
    templatePath: __DIR__,
    type: GeneratorType::OTHER,
)]
class DrushAliasFile extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);

        $vars['prefix'] = $ir->ask('File prefix (one word)', 'self');
        $vars['root'] = $ir->ask('Path to Drupal root', Drush::bootstrapManager()->getRoot());
        $vars['uri'] = $ir->ask('Drupal uri', Drush::bootstrapManager()->getUri() ?: null);
        $vars['host'] = $ir->ask('Remote host');

        if ($vars['host']) {
            $vars['user'] = $ir->ask('Remote user', Drush::config()->user());
        }

        $assets->addFile('drush/{prefix}.site.yml', 'drush-alias-file.yml.twig');
    }
}
