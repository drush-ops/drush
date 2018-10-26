<?php

namespace Drupal\webprofiler\Compiler;

use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Class ProfilerPass
 */
class ProfilerPass implements CompilerPassInterface {

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *
   * @throws \InvalidArgumentException
   */
  public function process(ContainerBuilder $container) {
    // configure the profiler service
    if (FALSE === $container->hasDefinition('profiler')) {
      return;
    }

    $definition = $container->getDefinition('profiler');

    $collectors = new \SplPriorityQueue();
    $order = PHP_INT_MAX;
    foreach ($container->findTaggedServiceIds('data_collector') as $id => $attributes) {
      $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
      $template = NULL;

      if (isset($attributes[0]['template'])) {
        if (!isset($attributes[0]['id'])) {
          throw new \InvalidArgumentException(sprintf('Data collector service "%s" must have an id attribute in order to specify a template', $id));
        }
        if (!isset($attributes[0]['title'])) {
          throw new \InvalidArgumentException(sprintf('Data collector service "%s" must have a title attribute', $id));
        }

        $template = [
          $attributes[0]['id'],
          $attributes[0]['template'],
          $attributes[0]['title']
        ];
      }

      $collectors->insert([$id, $template], [-$priority, --$order]);
    }

    $templates = [];
    foreach ($collectors as $collector) {
      $definition->addMethodCall('add', [new Reference($collector[0])]);
      $templates[$collector[0]] = $collector[1];
    }

    $container->setParameter('data_collector.templates', $templates);

    // set parameter to store the public folder path
    $path = 'file:' . DRUPAL_ROOT . '/' . PublicStream::basePath() . '/profiler';
    $container->setParameter('data_collector.storage', $path);
  }
}
