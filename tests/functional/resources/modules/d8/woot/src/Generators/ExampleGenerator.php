<?php

namespace Drupal\woot\Generators;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;
use Psr\Container\ContainerInterface;

class ExampleGenerator extends ModuleGenerator implements ContainerInjectionInterface
{
    public const API = 2;

    protected string $name = 'woot:example';
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
     * An optional factory method. Useful for injecting Drupal services. Remember to implement ContainerAwareInterface.
     *
     * @param ContainerInterface $container
     *   The Drupal container.
     * @return static
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('module_handler'),
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
