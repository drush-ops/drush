<?php

namespace Drush\Commands;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;

/**
 * A copy of \Drupal\Core\DependencyInjection\AutowireTrait with first params' type hint changed.
 *
 * Defines a trait for automatically wiring dependencies from the container.
 *
 * This trait uses reflection and may cause performance issues with classes
 * that will be instantiated multiple times.
 */
trait AutowireTrait
{
    /**
     * Limit to service and param or plain value.
     *
     * @see \Symfony\Component\DependencyInjection\Attribute\Autowire::__construct
     */
    private const ACCEPTED_AUTOWIRE_ARGUMENTS = [
        0 => 'value',
        1 => 'service',
        4 => 'param',
    ];

    /**
     * Instantiates a new instance of the implementing class using autowiring.
     *
     * @param ContainerInterface $container
     *   The service container this instance should use.
     *
     * @return static
     */
    public static function create(ContainerInterface $container)
    {
        $args = [];

        if (method_exists(static::class, '__construct')) {
            $constructor = new \ReflectionMethod(static::class, '__construct');
            foreach ($constructor->getParameters() as $parameter) {
                if (!$attributes = $parameter->getAttributes(Autowire::class)) {
                    // No #[Autowire()] attribute.
                    $service = ltrim((string) $parameter->getType(), '?');
                    if (!$container->has($service)) {
                        throw new AutowiringFailedException($service, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::_construct()", you should configure its value explicitly.', $service, $parameter->getName(), static::class));
                    }
                    $args[] = $container->get($service);
                    continue;
                }

                // This parameter has an #[Autowire()] attribute.
                [$attribute] = $attributes;
                $value = null;
                foreach ($attribute->getArguments() as $key => $argument) {
                    // Resolve the name when arguments are passed as list.
                    if (is_int($key)) {
                        if ($argument === null || !isset(self::ACCEPTED_AUTOWIRE_ARGUMENTS[$key])) {
                            continue;
                        }
                        $key = self::ACCEPTED_AUTOWIRE_ARGUMENTS[$key];
                    }

                    if (!in_array($key, self::ACCEPTED_AUTOWIRE_ARGUMENTS, true)) {
                        continue;
                    }

                    $value = $attribute->newInstance()->value;
                    $valueAsString = (string) $value;
                    $value = match ($key) {
                        'service' => $container->has($valueAsString) ? $container->get($valueAsString) : throw new AutowiringFailedException($valueAsString, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::_construct()", you should configure its value explicitly.', $valueAsString, $parameter->getName(), static::class)),
                        // Container param comes as %foo.bar.param%.
                        'param' => $container instanceof SymfonyContainerInterface ? $container->getParameter(trim($valueAsString, '%')) : $valueAsString,
                        default => $value,
                    };
                    // Done as Autowire::__construct() only needs one argument.
                    break;
                }
                if ($value !== null) {
                    $args[] = $value;
                }
            }
        }

        return new self(...$args);
    }
}
