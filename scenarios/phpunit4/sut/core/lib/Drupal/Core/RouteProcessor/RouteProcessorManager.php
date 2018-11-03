<?php

namespace Drupal\Core\RouteProcessor;

use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\Routing\Route;

/**
 * Route processor manager.
 *
 * Holds an array of route processor objects and uses them to sequentially
 * process an outbound route, in order of processor priority.
 */
class RouteProcessorManager implements OutboundRouteProcessorInterface {

  /**
   * Holds the array of outbound processors to cycle through.
   *
   * @var array
   *   An array whose keys are priorities and whose values are arrays of path
   *   processor objects.
   */
  protected $outboundProcessors = [];

  /**
   * Holds the array of outbound processors, sorted by priority.
   *
   * @var array
   *   An array of path processor objects.
   */
  protected $sortedOutbound = [];

  /**
   * Adds an outbound processor object to the $outboundProcessors property.
   *
   * @param \Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface $processor
   *   The processor object to add.
   * @param int $priority
   *   The priority of the processor being added.
   */
  public function addOutbound(OutboundRouteProcessorInterface $processor, $priority = 0) {
    $this->outboundProcessors[$priority][] = $processor;
    $this->sortedOutbound = [];
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, BubbleableMetadata $bubbleable_metadata = NULL) {
    $processors = $this->getOutbound();
    foreach ($processors as $processor) {
      $processor->processOutbound($route_name, $route, $parameters, $bubbleable_metadata);
    }
  }

  /**
   * Returns the sorted array of outbound processors.
   *
   * @return array
   *   An array of processor objects.
   */
  protected function getOutbound() {
    if (empty($this->sortedOutbound)) {
      $this->sortedOutbound = $this->sortProcessors();
    }

    return $this->sortedOutbound;
  }

  /**
   * Sorts the processors according to priority.
   */
  protected function sortProcessors() {
    $sorted = [];
    krsort($this->outboundProcessors);

    foreach ($this->outboundProcessors as $processors) {
      $sorted = array_merge($sorted, $processors);
    }
    return $sorted;
  }

}
