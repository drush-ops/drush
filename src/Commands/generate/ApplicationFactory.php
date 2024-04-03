<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Event\GeneratorInfoAlter;
use Drush\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Commands\generate\Generators\Drush\DrushCommandFile;
use Drush\Commands\generate\Generators\Drush\DrushGeneratorFile;
use Drush\Runtime\ServiceManager;
use Psr\Container\ContainerInterface as DrushContainer;
use Psr\Log\LoggerInterface;

class ApplicationFactory
{
    private readonly ServiceManager $serviceManager;
    private readonly \Symfony\Component\DependencyInjection\ContainerInterface $container;

    public function __construct(
        private readonly DrushContainer $drush_container,
        private readonly LoggerInterface $logger
    ) {
        $this->serviceManager = $this->drush_container->get('service.manager');
        $this->container = $this->drush_container->get('service_container');
    }

    /**
     * Creates Drush generate application.
     */
    public function create(): Application
    {
        $this->container->get('event_dispatcher')
            ->addListener(GeneratorInfoAlter::class, [self::class, 'alterGenerators']);
        $application = Application::create($this->container);
        $application->setAutoExit(false);

        $generators = $this->discover();
        $application->addCommands($generators);
        // Hide default Symfony console commands.
        foreach (['help', 'list', 'completion', '_complete'] as $name) {
            $application->get($name)->setHidden(true);
        }
        return $application;
    }

    public function discover(): array
    {
        $module_generator_classes = [];
        foreach ($this->container->get('module_handler')->getModuleList() as $moduleId => $extension) {
            $path = DRUPAL_ROOT . '/' . $extension->getPath() . '/src/Drush/';
            $module_generator_classes = array_merge(
                $module_generator_classes,
                $this->serviceManager->discoverModuleGenerators([$path], "\\Drupal\\" . $moduleId . "\\Drush")
            );
        }
        $module_generators = $this->serviceManager->instantiateServices($module_generator_classes, $this->drush_container, $this->container);

        $global_generator_classes = $this->serviceManager->discoverPsr4Generators();
        $global_generator_classes = $this->filterClassExists($global_generator_classes);
        $global_generators = $this->serviceManager->instantiateServices($global_generator_classes, $this->drush_container, $this->container);
        return [
            new DrushCommandFile(),
            new DrushAliasFile(),
            new DrushGeneratorFile(),
            ...$global_generators,
            ...$module_generators,
        ];
    }

    /**
     * Check each class for existence.
     */
    public function filterClassExists(array $classes): array
    {
        $exists = [];
        foreach ($classes as $class) {
            try {
                // DCG v1+v2 generators extend a non-existent class, so this check is needed.
                if (class_exists($class)) {
                    $exists[] = $class;
                }
            } catch (\Throwable $e) {
                $this->logger()->notice($e->getMessage());
            }
        }
        return $exists;
    }

    /**
     * Implements hook GeneratorInfoAlter.
     */
    public static function alterGenerators(GeneratorInfoAlter $event): void
    {
        $event->generators['theme-settings']->setName('theme:settings');
        $event->generators['plugin-manager']->setName('plugin:manager');
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }
}
