<?php

declare(strict_types=1);

namespace Drush\Drupal\Commands\generate;

use Composer\Autoload\ClassLoader;
use DrupalCodeGenerator\Application;
use DrupalCodeGenerator\Command\BaseGenerator;
use Drush\Drupal\Commands\generate\Generators\Drush\DrushAliasFile;
use Drush\Drupal\Commands\generate\Generators\Drush\DrushCommandFile;
use Drush\Drupal\DrushServiceModifier;
use Psr\Log\LoggerInterface;
use Robo\ClassDiscovery\RelativeNamespaceDiscovery;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ApplicationFactory
{
    public function __construct(
        private ContainerInterface $container,
        private ClassLoader $autoloader,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Creates Drush generate application.
     */
    public function create(): Application
    {
        $application = Application::create($this->container);
        $application->setAutoExit(false);

        $generators = $this->discover();
        $application->addCommands($generators);
        return $application;
    }

    public function discover(): array
    {
        $global_generators = $this->discoverPsr4Generators();

        $module_generators = [];
        if ($this->container->has(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)) {
            $module_generators = $this->container->get(DrushServiceModifier::DRUSH_GENERATOR_SERVICES)->getCommandList(
            );
        }

        $generators = [
            new DrushCommandFile(),
            new DrushAliasFile(),
            ...$global_generators,
            ...$module_generators,
        ];
        return $generators;
    }

    public function discoverPsr4Generators(): array
    {
        $classes = (new RelativeNamespaceDiscovery($this->autoloader))
            ->setRelativeNamespace('Drush\Generators')
            ->setSearchPattern('/.*Generator\.php$/')->getClasses();
        $classes = $this->filterExists($classes);
        return $this->getGenerators($classes);
    }

    /**
     * Check each class for existence.
     *
     * @param array $classes
     * @return array
     */
    public function filterExists(array $classes): array
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
            array_filter($classes, function (string $class): bool {
                $reflectionClass = new \ReflectionClass($class);
                return $reflectionClass->isSubclassOf(BaseGenerator::class)
                    && !$reflectionClass->isAbstract()
                    && !$reflectionClass->isInterface()
                    && !$reflectionClass->isTrait();
            })
        );
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }
}
