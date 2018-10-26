<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Provides a data collector which shows all available routes.
 */
class RoutingDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * The route profiler.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs a new RoutingDataCollector.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   */
  public function __construct(RouteProviderInterface $routeProvider) {
    $this->routeProvider = $routeProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $this->data['routing'] = [];
    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      // @TODO Find a better visual representation.
      $this->data['routing'][] = [
        'name' => $route_name,
        'path' => $route->getPath(),
      ];
    }
  }

  /**
   * @return int
   */
  public function getRoutesCount() {
    return count($this->routing());
  }

  /**
   * Twig callback for displaying the routes.
   */
  public function routing() {
    return $this->data['routing'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Routing');
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'routing';
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Defined routes: @route', ['@route' => $this->getRoutesCount()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAPxJREFUeNrkVsERREAQ7D0koXwlIAISkIGXvwAkoJSfHITg5SMUVR4eMuCxZ1xxV+5uuTL30lV8bPX09PSuFVJKcOOGP+DipLrrusoFdV1Lz/NgmiaKosC0XrAopYT0fc/f/jAMa41Pj23bkrpi9bRpGmRZ9lRKFSzLkl9UHMI4jijLcuaaSZMkQdd1fNMn5r0EHIFhGEjTlDenYRjCcZyHUnqRwXEcz77sYepM+Z1yTOEXZEFVVYcUba2iItsNcbr9IAiw5HMd1CJZtU1VpG3bIooi3gPF933keY43pb9gb1Bskdrap58luMjvRNO04wcK18RfIa59mbgLMAASuWsKAyoEhgAAAABJRU5ErkJggg==';
  }
}
