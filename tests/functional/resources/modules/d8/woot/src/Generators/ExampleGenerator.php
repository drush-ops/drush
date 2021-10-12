<?php

namespace Drupal\woot\Generators;

use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;
use Psr\Container\ContainerInterface;

class ExampleGenerator extends ModuleGenerator
{
    protected string $name = 'woot-example';
    protected string $description = 'Generates a woot.';
    protected string $alias = 'wootex';
    protected string $templatePath = __DIR__;

    /**
     * Illustrates how to inject a dependency into a Generator.
     *
     * @var ModuleHandlerInterface
     */
    protected $moduleHandler;

    public function __construct(ModuleHandlerInterface $moduleHandler = null)
    {
        parent::__construct($this->name);
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * An optional factory method. Useful in order to inject dependencies from the Drush or Drupal containers.
     *
     * @param ContainerInterface $combinedContainer
     *   A super-container which checks Drush and then Drupal containers for services.
     * @return static
     */
    public static function create(ContainerInterface $combinedContainer)
    {
        return new static(
            $combinedContainer->get('module_handler'),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function generate(&$vars): void
    {
        $this->collectDefault($vars);
        $vars['class'] = '{machine_name|camelize}Commands';
        $vars['color'] = $this->ask('Favorite color', 'blue');
        $this->addFile('Commands/{class}.php', 'example-generator.twig');
    }
}
