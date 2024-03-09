<?php

declare(strict_types=1);

namespace Drupal\woot\Drush\Generators;

use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Drush\Commands\AutowireTrait;

#[Generator(
    name: 'woot:example',
    description: 'Generates a woot.',
    aliases: ['wootex'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class ExampleGenerator extends BaseGenerator
{
    use AutowireTrait;

    /**
     * Illustrates how to inject a dependency into a Generator.
     */
    public function __construct(
        protected ModuleHandlerInterface $moduleHandler,
    ) {
        parent::__construct();
    }

    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['machine_name'] = $ir->askMachineName();
        $vars['class'] = '{machine_name|camelize}Commands';
        $vars['color'] = $ir->ask('Favorite color', 'blue');
        $assets->addFile('Commands/{class}.php', 'example-generator.twig');
    }
}
