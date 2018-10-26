<?php

namespace Drupal\devel\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\devel\DevelDumperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Provides route responses for the route info pages.
 */
class RouteInfoController extends ControllerBase {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The router service.
   *
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * The dumper service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * RouterInfoController constructor.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $provider
   *   The route provider.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   The router service.
   * @param \Drupal\devel\DevelDumperManagerInterface $dumper
   *   The dumper service.
   */
  public function __construct(RouteProviderInterface $provider, RouterInterface $router, DevelDumperManagerInterface $dumper) {
    $this->routeProvider = $provider;
    $this->router = $router;
    $this->dumper = $dumper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('router.no_access_checks'),
      $container->get('devel.dumper')
    );
  }

  /**
   * Builds the routes overview page.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function routeList() {
    $headers = [
      $this->t('Route Name'),
      $this->t('Path'),
      $this->t('Allowed Methods'),
      $this->t('Operations'),
    ];

    $rows = [];

    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      $row['name'] = [
        'data' => $route_name,
        'class' => 'table-filter-text-source',
      ];
      $row['path'] = [
        'data' => $route->getPath(),
        'class' => 'table-filter-text-source',
      ];
      $row['methods']['data'] = [
        '#theme' => 'item_list',
        '#items' => $route->getMethods(),
        '#empty' => $this->t('ANY'),
        '#context' => ['list_style' => 'comma-list'],
      ];

      // We cannot resolve routes with dynamic parameters from route path. For
      // these routes we pass the route name.
      // @see ::routeItem()
      if (strpos($route->getPath(), '{') !== FALSE) {
        $parameters = ['query' => ['route_name' => $route_name]];
      }
      else {
        $parameters = ['query' => ['path' => $route->getPath()]];
      }

      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => [
          'devel' => [
            'title' => $this->t('Devel'),
            'url' => Url::fromRoute('devel.route_info.item', [], $parameters),
          ],
        ],
      ];

      $rows[] = $row;
    }

    $output['#attached']['library'][] = 'system/drupal.system.modules';

    $output['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];
    $output['filters']['name'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter route name or path'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '.devel-filter-text',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the route name or path to filter by.'),
      ],
    ];
    $output['routes'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No routes found.'),
      '#sticky' => TRUE,
      '#attributes' => [
        'class' => ['devel-route-list', 'devel-filter-text'],
      ],
    ];

    return $output;
  }

  /**
   * Returns a render array representation of the route object.
   *
   * The method tries to resolve the route from the 'path' or the 'route_name'
   * query string value if available. If no route is retrieved from the query
   * string parameters it fallbacks to the current route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function routeDetail(Request $request, RouteMatchInterface $route_match) {
    $route = NULL;

    // Get the route object from the path query string if available.
    if ($path = $request->query->get('path')) {
      try {
        $route = $this->router->match($path);
      }
      catch (\Exception $e) {
        drupal_set_message($this->t("Unable to load route for url '%url'", ['%url' => $path]), 'warning');
      }
    }

    // Get the route object from the route name query string if available and
    // the route is not retrieved by path.
    if ($route === NULL && $route_name = $request->query->get('route_name')) {
      try {
        $route = $this->routeProvider->getRouteByName($route_name);
      }
      catch (\Exception $e) {
        drupal_set_message($this->t("Unable to load route '%name'", ['%name' => $route_name]), 'warning');
      }
    }

    // No route retrieved from path or name specified, get the current route.
    if ($route === NULL) {
      $route = $route_match->getRouteObject();
    }

    return $this->dumper->exportAsRenderable($route);
  }

}
