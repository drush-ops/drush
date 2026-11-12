<?php

declare(strict_types=1);

namespace Drush\Doctor;

use Composer\Autoload\ClassLoader;
use Drush\Runtime\RelativeNamespaceDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Discovers command classes.
 *
 * The provider runs inside Drupal's container rather than Drush's, so it
 * cannot have the Composer ClassLoader injected. The loader is therefore
 * resolved from ClassLoader::getRegisteredLoaders() when one is not supplied.
 */
class FindClasses
{
    /**
     * Finds instantiable #[AsCommand] classes in a namespace below `Drush\`.
     *
     * @param string $relativeNamespace
     *   A namespace relative to the `Drush\` PSR-4 root, e.g. `Commands\user`.
     * @param ClassLoader|null $classLoader
     *   The loader to search, or null to resolve the registered one.
     *
     * @return string[]
     *   The discovered class names, sorted.
     */
    public static function findClasses(string $relativeNamespace, ?ClassLoader $classLoader = null): array
    {
        $classLoader ??= self::classLoader();
        if (!$classLoader instanceof ClassLoader) {
            return [];
        }

        $discovered = new RelativeNamespaceDiscovery($classLoader)
            ->setRelativeNamespace($relativeNamespace)
            ->setSearchPattern('*Command.php')
            ->getClasses();

        $classes = array_values(array_filter($discovered, self::isCommand(...)));
        sort($classes);

        return $classes;
    }

    /**
     * Whether a discovered class is a command that can be instantiated.
     */
    protected static function isCommand(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new \ReflectionClass($class);

        return !$reflection->isAbstract()
            && $reflection->getAttributes(AsCommand::class) !== [];
    }

    /**
     * The registered Composer loader that provides the `Drush\` namespace.
     */
    protected static function classLoader(): ?ClassLoader
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if (isset($loader->getPrefixesPsr4()['Drush\\'])) {
                return $loader;
            }
        }

        return null;
    }
}
