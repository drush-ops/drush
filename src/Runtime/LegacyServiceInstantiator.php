<?php

declare(strict_types=1);

namespace Drush\Runtime;

use Drush\Log\Logger;
use League\Container\Container;
use Drush\Drush;
use Symfony\Component\Console\Application;
use Composer\Autoload\ClassLoader;
use Drush\Command\DrushCommandInfoAlterer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Robo\Robo;

/**
 * Use the Symfony Dependency Injection Container to instantiate services.
 *
 * This factory class is used solely for backwards compatability with
 * Drupal modules that still use drush.services.ymls to define Drush
 * Commands, Generators & etc.; this mechanism is deprecated, though.
 * Modules should instead use the static factory `create` mechanism.
 */
class LegacyServiceInstantiator
{
    protected array $drushServicesContainer = [];
    protected array $tags = [];

    public function __construct(protected ContainerInterface $container)
    {}

    public function loadServiceFiles(array $serviceFiles)
    {
        foreach ($serviceFiles as $serviceFile) {
            $serviceFileContents = '';
            $serviceFileData = [];

            if (file_exists($serviceFile)) {
                $serviceFileContents = file_get_contents($serviceFile);
            }
            if (!empty($serviceFileContents)) {
                $serviceFileData = Yaml::parse($serviceFileContents);
            }

            if (isset($serviceFileData['services'])) {
                $this->instantiateServices($serviceFileData['services']);
            }
        }
    }

    /**
     * Given a drush.services.yml file (parsed into an array),
     * instantiate all of the services referenced therein, and
     * cache them in our dynamic service container.
     */
    public function instantiateServices(array $services)
    {
        foreach ($services as $serviceName => $info) {
            $service = $this->create(
                $info['class'],
                $info['arguments'] ?? [],
                $info['calls'] ?? []
            );

            $this->drushServicesContainer[$serviceName] = $service;

            // If `tags` to contains an item with `name: drush.command`,
            // then we should do something special with it

            if (isset($info['tags'])) {
                foreach ($info['tags'] as $tag) {
                    if (isset($tag['name'])) {
                        $this->tags[$tag['name']][$serviceName] = $service;
                    }
                }
            }
        }
    }

    public function taggedServices($tagName)
    {
        return $this->tags[$tagName] ?? [];
    }

    /**
     * Create one named service.
     */
    public function create($class, array $arguments, array $calls)
    {
        $instance = $this->instantiateObject($class, $arguments);
        foreach ($calls as $callInfo) {
            $this->call($instance, $callInfo[0], $callInfo[1]);
        }
        return $instance;
    }

    /**
     * Instantiate an object with the given arguments.
     * Arguments are first looked up from the Drupal container
     * or from our dynamic service container if they begin
     * with an `@`. Items from the Drush container may be
     * retreived by prepending the Drush service name with `*`.
     */
    public function instantiateObject($class, array $arguments)
    {
        $refl = new \ReflectionClass($class);
        return $refl->newInstanceArgs($this->resolveArguments($arguments));
    }

    /**
     * Call a method of an object with the provided arguments.
     * Arguments are resolved against the container first.
     */
    public function call($object, $method, array $arguments)
    {
        $resolved = $this->resolveArguments($arguments);
        if ($this->atLeastOneValue($resolved)) {
            call_user_func_array([$object, $method], $resolved);
        }
    }

    /**
     * Resolve arguments against our containers. Arguments that
     * do not map to one of our containers are returned unchanged.
     */
    protected function resolveArguments(array $arguments)
    {
        return array_map([$this, 'resolveArgument'], $arguments);
    }

    /**
     * Look up one argument in the appropriate container, or
     * return it as-is.
     */
    protected function resolveArgument($arg)
    {
        if (!is_string($arg)) {
            return $arg;
        }

        if ($arg[0] == '@') {
            // Check to see if a previous drush.services.yml instantiated
            // this service; return any service found.
            $drushServiceName = ltrim(substr($arg, 1), '?');
            if (isset($this->drushServicesContainer[$drushServiceName])) {
                return $this->drushServicesContainer[$drushServiceName];
            }

            // If the service is not found in the dynamic container
            return $this->resolveFromContainer($this->container, substr($arg, 1));
        }

        return $arg;
    }

    /**
     * Look up in the provided container; throw an exception if
     * not found, unless the service name begins with `?` (e.g.
     * `@?drupal.service` or `*?drush.service`).
     */
    protected function resolveFromContainer($container, string $arg)
    {
        [$required, $arg] = $this->isRequired($arg);

        // Exit early if the container does not have the service
        if (!$container->has($arg)) {
            if ($required) {
                throw new \Exception("Big badda boom! This should be the same thing that the Drupal / Symfony DI container throws.");
            }

            return null;
        }

        return $container->get($arg);
    }

    /**
     * Check to see if the provided argument begins with a `?`;
     * those that do not are required.
     */
    protected function isRequired(string $arg)
    {
        if ($arg[0] == '?') {
            return [false, substr($arg, 1)];
        }

        return [true, $arg];
    }

    protected function atLeastOneValue($args)
    {
        foreach ($args as $arg) {
            if ($arg) {
                return true;
            }
        }
        return false;
    }
}
