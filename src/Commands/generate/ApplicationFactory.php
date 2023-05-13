<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use Composer\Autoload\ClassLoader;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Event\GeneratorInfoAlter;
use Drush\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Commands\generate\Generators\Drush\DrushCommandFile;
use Drush\Drupal\DrushServiceModifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApplicationFactory
{
    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger
    ) {
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
        $module_generators = [];
        $serviceManager = \Drush\Drush::service('service.manager');
        $module_generators = $serviceManager->getGenerators();

        $global_generator_classes = $serviceManager->discoverPsr4Generators();
        $global_generator_classes = $this->filterCLassExists($global_generator_classes);
        $global_generators = $this->getGenerators($global_generator_classes);

        $generators = [
            new DrushCommandFile(),
            new DrushAliasFile(),
            ...$global_generators,
            ...$module_generators,
        ];
        return $generators;
    }

    /**
     * Check each class for existence.
     *
     * @param array $classes
     * @return array
     */
    public function filterCLassExists(array $classes): array
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
     * Validate and instantiate generator classes.
     *
     * @return BaseGenerator[]
     * @throws \ReflectionException
     */
    public function getGenerators(array $classes): array
    {
        return array_map(
            function (string $class): BaseGenerator {
                return new $class();
            },
            $classes
        );
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
