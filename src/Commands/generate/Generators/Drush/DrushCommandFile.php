<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Drush;

#[Generator(
    name: 'drush:command-file',
    description: 'Generates a Drush command file.',
    aliases: ['dcf'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class DrushCommandFile extends BaseGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();

        $vars['class'] = $ir->askClass(default: '{machine_name|camelize}Commands');
        $vars['services'] = $ir->askServices(false, ['token']);

        $assets->addFile('src/Drush/Commands/{class}.php', 'drush-command-file.php.twig');
    }
}
