<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Symfony\Component\Finder\Finder;
use Composer\Autoload\ClassLoader;

/**
 * Discovers classes in a relative namespace using the Composer ClassLoader.
 */
class RelativeNamespaceDiscovery
{
    /**
     * @var string
     */
    protected $searchPattern = '*.php';

    /**
     * @var string
     */
    protected $relativeNamespace = '';

    public function __construct(protected ClassLoader $classLoader)
    {
    }

    /**
     * @return $this
     */
    public function setSearchPattern(string $searchPattern): static
    {
        $this->searchPattern = $searchPattern;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRelativeNamespace(string $relativeNamespace): static
    {
        $this->relativeNamespace = $relativeNamespace;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getClasses(): array
    {
        $classes = [];
        $relativePath = $this->convertNamespaceToPath($this->relativeNamespace);

        foreach ($this->classLoader->getPrefixesPsr4() as $baseNamespace => $directories) {
            $directories = array_filter(array_map(function ($directory) use ($relativePath) {
                return $directory . $relativePath;
            }, $directories), 'is_dir');

            if ($directories) {
                foreach ($this->search($directories, $this->searchPattern) as $file) {
                    $relativePathName = $file->getRelativePathname();
                    $classes[] = $baseNamespace . $this->convertPathToNamespace($relativePath . '/' . $relativePathName);
                }
            }
        }

        return $classes;
    }

    /**
     * @param string|array $directories
     * @param string $pattern
     *
     * @return Finder
     */
    protected function search($directories, $pattern): Finder
    {
        $finder = new Finder();
        $finder->files()
          ->name($pattern)
          ->in($directories);

        return $finder;
    }

    protected function convertPathToNamespace(string $path): string
    {
        return str_replace(['/', '.php'], ['\\', ''], trim($path, '/'));
    }

    public function convertNamespaceToPath(string $namespace): string
    {
        return '/' . str_replace("\\", '/', trim($namespace, '\\'));
    }
}
