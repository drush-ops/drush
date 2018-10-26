<?php

namespace Drupal\webprofiler\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraph;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Class ServicePass
 */
class ServicePass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    if (FALSE === $container->hasDefinition('webprofiler.services')) {
      return;
    }

    $definition = $container->getDefinition('webprofiler.services');
    $graph = $container->getCompiler()->getServiceReferenceGraph();

    $definition->addMethodCall('setServices', [$this->extractData($container, $graph)]);
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   * @param \Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraph $graph
   *
   * @return array
   */
  private function extractData(ContainerBuilder $container, ServiceReferenceGraph $graph) {
    $data = [];

    foreach ($container->getDefinitions() as $id => $definition) {
      $inEdges = [];
      $outEdges = [];

      if ($graph->hasNode($id)) {
        $node = $graph->getNode($id);

        /** @var \Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraphEdge $edge */
        foreach ($node->getInEdges() as $edge) {
          /** @var \Symfony\Component\DependencyInjection\Reference $edgeValue */
          $edgeValue = $edge->getValue();

          $inEdges[] = [
            'id' => $edge->getSourceNode()->getId(),
            'invalidBehavior' => $edgeValue ? $edgeValue->getInvalidBehavior() : NULL,
          ];
        }


        /** @var \Symfony\Component\DependencyInjection\Compiler\ServiceReferenceGraphEdge $edge */
        foreach ($node->getOutEdges() as $edge) {
          /** @var \Symfony\Component\DependencyInjection\Reference $edgeValue */
          $edgeValue = $edge->getValue();

          $outEdges[] = [
            'id' => $edge->getDestNode()->getId(),
            'invalidBehavior' => $edgeValue ? $edgeValue->getInvalidBehavior() : NULL,
          ];
        }
      }

      if ($definition instanceof Definition) {
        $class = $definition->getClass();

        try {
          $reflectedClass = new \ReflectionClass($class);
          $file = $reflectedClass->getFileName();
        } catch (\ReflectionException $e) {
          $file = NULL;
        }

        $tags = $definition->getTags();
        $public = $definition->isPublic();
        $synthetic = $definition->isSynthetic();
      }
      else {
        $id = $definition->__toString();
        $class = NULL;
        $file = NULL;
        $tags = [];
        $public = NULL;
        $synthetic = NULL;
      }

      $data[$id] = [
        'inEdges' => $inEdges,
        'outEdges' => $outEdges,
        'value' => [
          'class' => $class,
          'file' => $file,
          'id' => $id,
          'tags' => $tags,
          'public' => $public,
          'synthetic' => $synthetic,
        ],
      ];
    }

    return $data;
  }
}
