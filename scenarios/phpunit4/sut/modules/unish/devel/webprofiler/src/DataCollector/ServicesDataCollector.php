<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\DependencyInjection\TraceableContainer;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Class ServicesDataCollector
 */
class ServicesDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   *   $container
   */
  private $container;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    if ($this->getServicesCount()) {

      $tracedData = [];
      if ($this->container instanceof TraceableContainer) {
        $tracedData = $this->container->getTracedData();
      }

      foreach (array_keys($this->getServices()) as $id) {
        $this->data['services'][$id]['initialized'] = ($this->container->initialized($id)) ? TRUE : FALSE;
        $this->data['services'][$id]['time'] = isset($tracedData[$id]) ? $tracedData[$id] : NULL;
      }
    }
  }

  /**
   * @param $services
   */
  public function setServices($services) {
    $this->data['services'] = $services;
  }

  /**
   * @return array
   */
  public function getServices() {
    return $this->data['services'];
  }

  /**
   * @return int
   */
  public function getServicesCount() {
    return count($this->getServices());
  }

  /**
   * @return array
   */
  public function getInitializedServices() {
    return array_filter($this->getServices(), function($item) {
      return $item['initialized'];
    });
  }

  /**
   * @return int
   */
  public function getInitializedServicesCount() {
    return count($this->getInitializedServices());
  }

  /**
   * @return array
   */
  public function getInitializedServicesWithoutWebprofiler() {
    return array_filter($this->getInitializedServices(), function($item) {
      return strpos($item['value']['id'], 'webprofiler') !== 0;
    });
  }

  /**
   * @return int
   */
  public function getInitializedServicesWithoutWebprofilerCount() {
    return count($this->getInitializedServicesWithoutWebprofiler());
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'services';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Services');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Initialized: @count', [
      '@count' => $this->getInitializedServicesCount(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAQVJREFUeNrkVe0NgjAQBeMAdYO6AWxQNtANGEFHcALZANyADegGsIFsIBvgu6Q/LtWmxdTEjyYvd6Hw8t5de6TzPCex1yp5w/pz0rVrQymVIXSAACqt9TGG0p0hpHWIZb9lebWENOXn1FgWbL8GJHACNHs+ohyjlxSEZPEcKGYC6SbEvljgUHzEOR3IXiiB6YOTlLqdo1Y54tZHDLIauCHtETtn962P6EUVqhhi0gelIJEEk1MjMg9Py9xol/0SuBqFva/DULY3ZSqQF767v8TyZKv83tFXWVaEufsUG+DCr2nwQLGOlGQNizZPy3fMU16K5uV5+qQEpFTC+hCN9Pd/0XcBBgBxwVqjDkAznAAAAABJRU5ErkJggg==';
  }

  /**
   * @return array
   */
  public function getData() {
    $data = $this->data;

    $http_middleware = array_filter($data['services'], function($service) {
      return isset($service['value']['tags']['http_middleware']);
    });

    foreach ($http_middleware as &$service) {
      $service['value']['handle_method'] = $this->getMethodData($service['value']['class'], 'handle');
    }

    uasort($http_middleware, function ($a, $b) {
      $va = $a['value']['tags']['http_middleware'][0]['priority'];
      $vb = $b['value']['tags']['http_middleware'][0]['priority'];

      if ($va == $vb) {
        return 0;
      }
      return ($va > $vb) ? -1 : 1;
    });

    $data['http_middleware'] = $http_middleware;

    return $data;
  }
}
