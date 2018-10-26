<?php

namespace Drupal\webprofiler\Compiler;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class StoragePass
 */
class StoragePass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    if (FALSE === $container->hasDefinition('profiler.storage_manager')) {
      return;
    }

    $definition = $container->getDefinition('profiler.storage_manager');

    foreach ($container->findTaggedServiceIds('webprofiler_storage') as $id => $attributes) {
      $definition->addMethodCall('addStorage', [
        $id,
        $attributes[0]['title'],
        new Reference($id)
      ]);
    }
  }
}
