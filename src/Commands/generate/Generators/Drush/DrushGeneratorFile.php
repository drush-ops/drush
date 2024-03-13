<?php

declare(strict_types=1);

namespace Drush\Commands\generate\Generators\Drush;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Validator\RegExp;
use DrupalCodeGenerator\Validator\Required;

#[Generator(
    name: 'drush:generator',
    description: 'Generates a Drush generator.',
    aliases: ['dg'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class DrushGeneratorFile extends BaseGenerator
{
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();
        $generator_name_validator = new RegExp('/^[a-z][a-z0-9-_:]*[a-z0-9]$/', 'The value is not correct generator name.');
        $vars['generator']['name'] = $ir->ask('Generator name', '{machine_name}:example', $generator_name_validator);
        $vars['generator']['description'] = $ir->ask('Generator description', validator: new Required());

        $sub_names = \explode(':', $vars['generator']['name']);
        $short_name = \array_pop($sub_names);

        $vars['class'] = $ir->askClass(default: '{machine_name|camelize}Generators');
        $vars['template_name'] = $short_name;

        $assets->addFile('src/Drush/Generators/{class}.php', 'drush-generator.php.twig');
        $assets->addFile('src/Drush/Generators/{template_name}.twig', 'template.twig');
    }
}
