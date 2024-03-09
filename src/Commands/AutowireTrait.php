<?php

namespace Drush\Commands;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   */
    public static function create(\Psr\Container\ContainerInterface $container): self
    {
        $args = [];

        if (method_exists(static::class, '__construct')) {
            $constructor = new \ReflectionMethod(static::class, '__construct');
            foreach ($constructor->getParameters() as $parameter) {
                $service = ltrim((string) $parameter->getType(), '?');
                foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
                    $service = (string) $attribute->newInstance()->value;
                }

                if (!$container->has($service)) {
                    throw new AutowiringFailedException($service, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::_construct()", you should configure its value explicitly.', $service, $parameter->getName(), static::class));
                }

                $args[] = $container->get($service);
            }
        }

        return new static(...$args);
    }
}
