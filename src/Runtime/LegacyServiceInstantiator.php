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
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Robo\Robo;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Use the Symfony Dependency Injection Container to instantiate services.
 *
 * This factory class is used solely for backwards compatability with
 * Drupal modules that still use drush.services.ymls to define Drush
 * Commands, Generators & etc.; this mechanism is deprecated, though.
 * Modules should instead use the static factory `create` mechanism.
 *
 * This object is only in scope during bootstrap; see the DrupalBoot8 class.
 * After it has been used there, it is not referenced by any other code.
 */
class LegacyServiceInstantiator
{
    protected array $instantiatedDrushServices = [];
    protected array $tags = [];

    public function __construct(protected ContainerInterface $container, protected LoggerInterface $logger)
    {
    }

    /**
     * Instantiate all of the objects declared by drush.services.yml
     * files, and store them internally in this class for later retreival
     * by type.
     *
     * @param array $serviceFiles List of drush.services.yml files
     */
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

            if ($this->isValidServiceData($serviceFile, $serviceFileData)) {
                $this->instantiateServices($serviceFileData['services']);
            }
        }
    }

    /**
     * Validate service data before using it.
     *
     * @param string $serviceFile Path to service file being checked
     * @param array $serviceFileData Parsed data from drush.services.yml
     */
    protected function isValidServiceData(string $serviceFile, array $serviceFileData): bool
    {
        // If there are no services, then silently skip this service file.
        if (!isset($serviceFileData['services'])) {
            return false;
        }

        // We don't support auto-wiring
        if (!empty($serviceFileData['services']['_defaults']['autowire'])) {
            $this->logger->info(dt('Autowire not supported; skipping @file', ['@file' => $serviceFile]));
            return false;
        }

        // Every entry in services must have a 'class' entry
        if (!$this->allServicesHaveClassElement($serviceFile, $serviceFileData['services'])) {
            return false;
        }

        // If we didn't find anything wrong, then assume it's probably okay
        return true;
    }

    /**
     * Check all elements for required "class" elements.
     *
     * @param string $serviceFile Path to service file being checked
     * @param array $services List of data from 'services' element from drush.services.yml
     */
    protected function allServicesHaveClassElement(string $serviceFile, array $services): bool
    {
        foreach ($services as $service => $data) {
            if (!isset($data['class'])) {
                $this->logger->info(dt('Service @service does not have a class element; skipping @file', ['@service' => $service, '@file' => $serviceFile]));
                return false;
            }
        }

        return true;
    }

    /**
     * Given a drush.services.yml file (parsed into an array),
     * instantiate all of the services referenced therein.
     * The services created may be retrieved via the `taggedServices()`
     * method.
     *
     * @param array $serviceFiles List of drush.services.yml files
     */
    public function instantiateServices(array $services)
    {
        foreach ($services as $serviceName => $info) {
            // Skip legacy generators.
            $tag_names = \array_column($info['tags'] ?? [], 'name');
            if (\in_array('drush.generator', $tag_names) || \in_array('drush.generator.v2', $tag_names)) {
                continue;
            }

            $service = $this->create(
                $info['class'],
                $info['arguments'] ?? [],
                $info['calls'] ?? []
            );

            $this->instantiatedDrushServices[$serviceName] = $service;

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

    /**
     * After `instantiateServices()` runs, the resulting instantiated
     * service objects can be retrieved via this method.
     *
     * @param string $tagName Name of service (e.g. 'drush.command') to retrieve
     *
     * @return object[] Command handlers with the specified tag
     */
    public function taggedServices($tagName)
    {
        return $this->tags[$tagName] ?? [];
    }

    /**
     * Create one named service.
     *
     * @param string $class Class containing implementation
     * @param string[] $arguments Parameters to class constructor
     * @param array Method names and arguments to call after object is instantiated
     *
     * @return object
     *   Instantiated command handler from the service file
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
     * with an `@`.
     *
     * @param string $class Class containing implementation
     * @param string[] $arguments Parameters to class constructor
     */
    public function instantiateObject($class, array $arguments)
    {
        $refl = new \ReflectionClass($class);
        return $refl->newInstanceArgs($this->resolveArguments($arguments));
    }

    /**
     * Call a method of an object with the provided arguments.
     * Arguments are resolved against the container first.
     *
     * @param object $object Command handler to initialize
     * @param string $method Name of method to call
     * @param array $arguments Arguments to pass to class method
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
     *
     * @param array $arguments Arguments to resolve
     *
     * @return array
     *   Arguments after they have been resolved by DI container
     */
    protected function resolveArguments(array $arguments)
    {
        return array_map([$this, 'resolveArgument'], $arguments);
    }

    /**
     * Look up one argument in the appropriate container, or
     * return it as-is.
     *
     * @param array $argument Argument to resolve
     *
     * @return object
     *   Argument after it has been resolved by DI container
     */
    protected function resolveArgument($arg)
    {
        if (!is_string($arg)) {
            return $arg;
        }

        // Instantiate references to services, either in the
        // Drupal container, or other services created earlier by
        // some drush.services.yml file.
        if ($arg[0] == '@') {
            // Check to see if a previous drush.services.yml instantiated
            // this service; return any service found.
            $drushServiceName = ltrim(substr($arg, 1), '?');
            if (isset($this->instantiatedDrushServices[$drushServiceName])) {
                return $this->instantiatedDrushServices[$drushServiceName];
            }

            // If the service is not found in the dynamic container
            return $this->resolveFromContainer($this->container, substr($arg, 1));
        }

        // Look up references to service parameters
        if (preg_match('#^%.*%$#', $arg)) {
            $serviceParameterName = trim($arg, '%');
            return $this->container->getParameter($serviceParameterName);
        }

        return $arg;
    }

    /**
     * Look up in the provided container; throw an exception if
     * not found, unless the service name begins with `?` (e.g.
     * `@?drupal.service` or `*?drush.service`).
     *
     * @param Container $container Drupal DI container
     * @param string $arg Argument to resolve
     *
     * @param object Resolved object from DI container
     */
    protected function resolveFromContainer($container, string $arg)
    {
        [$required, $arg] = $this->isRequired($arg);

        // Exit early if the container does not have the service
        if (!$container->has($arg)) {
            if ($required) {
                throw new ParameterNotFoundException($arg);
            }

            return null;
        }

        return $container->get($arg);
    }

    /**
     * Check to see if the provided argument begins with a `?`;
     * those that do not are required.
     *
     * @param string $arg
     *
     * @return bool, string
     *   Boolean indicating whether the object is required to be in the container,
     *   and a string with the name of the object to look up (passed input with
     *   any leading ? removed).
     */
    protected function isRequired(string $arg)
    {
        if ($arg[0] == '?') {
            return [false, substr($arg, 1)];
        }

        return [true, $arg];
    }

    /**
     * Helper function to determine whether or not any of the arguments
     * resolved. `set` methods with non-required DI container references
     * are not called at all if the optional references are not in the container.
     *
     * @param string $arg Name of reference
     *
     * @return bool
     *   True if at least one argument is not empty
     */
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
