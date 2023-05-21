<?php

declare(strict_types=1);

namespace Drupal\woot\Drush\Generators;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    public function __construct(
        protected ModuleHandlerInterface $moduleHandler,
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('module_handler'),
        );

        return $commandHandler;
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
