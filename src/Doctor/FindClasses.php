<?php

declare(strict_types=1);

namespace Drush\Doctor;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\Finder;

/**
 * Discovers command classes in Drush's own source tree.
 */
class FindClasses
{
    private const string ROOT_NAMESPACE = 'Drush\\';

    /**
     * Finds instantiable #[AsCommand] classes in a Drush namespace.
     *
     * The namespace is mapped onto Drush's src/ directory, so only Drush's own
     * files are searched, never other packages sharing the namespace.
     *
     * @param string $namespace
     *   A fully-qualified namespace below `Drush\`, e.g. `Drush\Commands\user`.
     *
     * @return string[]
     *   The discovered class names, sorted.
     */
    public static function findClasses(string $namespace): array
    {
        $namespace = trim($namespace, '\\') . '\\';
        if (!str_starts_with($namespace, self::ROOT_NAMESPACE)) {
            throw new \InvalidArgumentException("Namespace must be below " . self::ROOT_NAMESPACE . ", got $namespace");
        }

        $relativePath = str_replace('\\', '/', substr($namespace, strlen(self::ROOT_NAMESPACE)));
        $directory = dirname(__DIR__) . '/' . $relativePath;
        if (!is_dir($directory)) {
            return [];
        }

        $discovered = [];
        foreach (Finder::create()->files()->name('*Command.php')->in($directory) as $file) {
            $discovered[] = $namespace . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        }

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
}
