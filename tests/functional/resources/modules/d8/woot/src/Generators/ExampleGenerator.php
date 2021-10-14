<?php

namespace Drupal\woot\Generators;

use Drupal\Core\Extension\ModuleHandlerInterface;
use DrupalCodeGenerator\Command\ModuleGenerator;

class ExampleGenerator extends ModuleGenerator
{
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
