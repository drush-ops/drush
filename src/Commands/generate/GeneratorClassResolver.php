<?php

namespace Drush\Commands\generate;

use DrupalCodeGenerator\ClassResolver\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface as DrupalClassResolverInterface;

/**
 * A simple adapter to make Drupal class resolver compatible with DCG class resolver.
 */
class GeneratorClassResolver implements ClassResolverInterface
{
    /**
     * The decorated class resolver.
     */
    protected DrupalClassResolverInterface $drupalClassResolver;

    /**
     * Constructs the object.
     */
    public function __construct(DrupalClassResolverInterface $drupalClassResolver)
    {
        $this->drupalClassResolver = $drupalClassResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(string $class): object
    {
        return $this->drupalClassResolver->getInstanceFromDefinition($class);
    }
}
