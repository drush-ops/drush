<?php

declare(strict_types=1);

namespace Drupal\woot\Generators;

use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Asset\Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Command\ModuleGenerator;
use DrupalCodeGenerator\GeneratorType;

#[Generator(
    name: 'woot:example',
    description: 'Generates a woot.',
    aliases: ['wootex'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class ExampleGenerator extends BaseGenerator
{

    /**
     * Illustrates how to inject a dependency into a Generator.
     */
    protected ModuleHandlerInterface $moduleHandler;

    public function __construct(ModuleHandlerInterface $moduleHandler = null)
    {
        parent::__construct();
        $this->moduleHandler = $moduleHandler;
    }

    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['class'] = '{machine_name|camelize}Commands';
        $vars['color'] = $ir->ask('Favorite color', 'blue');
        $assets->addFile('Commands/{class}.php', 'example-generator.twig');
    }
}
