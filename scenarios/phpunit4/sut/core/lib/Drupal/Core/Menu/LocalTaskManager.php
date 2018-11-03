<?php

namespace Drupal\Core\Menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Provides the default local task manager using YML as primary definition.
 */
class LocalTaskManager extends DefaultPluginManager implements LocalTaskManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    // (required) The name of the route this task links to.
    'route_name' => '',
    // Parameters for route variables when generating a link.
    'route_parameters' => [],
    // The static title for the local task.
    'title' => '',
    // The route name where the root tab appears.
    'base_route' => '',
    // The plugin ID of the parent tab (or NULL for the top-level tab).
    'parent_id' => NULL,
    // The weight of the tab.
    'weight' => NULL,
    // The default link options.
    'options' => [],
    // Default class for local task implementations.
    'class' => 'Drupal\Core\Menu\LocalTaskDefault',
    // The plugin id. Set by the plugin system based on the top-level YAML key.
    'id' => '',
  ];

  /**
   * An argument resolver object.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

  /**
   * A controller resolver object.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface
   *
   * @deprecated
   *   Using the 'controller_resolver' service as the first argument is
   *   deprecated, use the 'http_kernel.controller.argument_resolver' instead.
   *   If your subclass requires the 'controller_resolver' service add it as an
   *   additional argument.
   *
   * @see https://www.drupal.org/node/2959408
   */
  protected $controllerResolver;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The plugin instances.
   *
   * @var array
   */
  protected $instances = [];

  /**
   * The local task render arrays for the current route.
   *
   * @var array
   */
  protected $taskData;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Constructs a \Drupal\Core\Menu\LocalTaskManager object.
   *
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   *   An object to use in resolving route arguments.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request object to use for building titles and paths for plugin instances.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(ArgumentResolverInterface $argument_resolver, RequestStack $request_stack, RouteMatchInterface $route_match, RouteProviderInterface $route_provider, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, AccountInterface $account) {
    $this->factory = new ContainerFactory($this, '\Drupal\Core\Menu\LocalTaskInterface');
    $this->argumentResolver = $argument_resolver;
    if ($argument_resolver instanceof ControllerResolverInterface) {
      @trigger_error("Using the 'controller_resolver' service as the first argument is deprecated, use the 'http_kernel.controller.argument_resolver' instead. If your subclass requires the 'controller_resolver' service add it as an additional argument. See https://www.drupal.org/node/2959408.", E_USER_DEPRECATED);
      $this->controllerResolver = $argument_resolver;
    }
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
    $this->routeProvider = $route_provider;
    $this->accessManager = $access_manager;
    $this->account = $account;
    $this->moduleHandler = $module_handler;
    $this->alterInfo('local_tasks');
    $this->setCacheBackend($cache, 'local_task_plugins:' . $language_manager->getCurrentLanguage()->getId(), ['local_task']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $yaml_discovery = new YamlDiscovery('links.task', $this->moduleHandler->getModuleDirectories());
      $yaml_discovery->addTranslatableProperty('title', 'title_context');
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($yaml_discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);
    // If there is no route name, this is a broken definition.
    if (empty($definition['route_name'])) {
      throw new PluginException(sprintf('Plugin (%s) definition must include "route_name"', $plugin_id));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(LocalTaskInterface $local_task) {
    $controller = [$local_task, 'getTitle'];
    $request = $this->requestStack->getCurrentRequest();
    $arguments = $this->argumentResolver->getArguments($request, $controller);
    return call_user_func_array($controller, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();

    $count = 0;
    foreach ($definitions as &$definition) {
      if (isset($definition['weight'])) {
        // Add some micro weight.
        $definition['weight'] += ($count++) * 1e-6;
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalTasksForRoute($route_name) {
    if (!isset($this->instances[$route_name])) {
      $this->instances[$route_name] = [];
      if ($cache = $this->cacheBackend->get($this->cacheKey . ':' . $route_name)) {
        $base_routes = $cache->data['base_routes'];
        $parents = $cache->data['parents'];
        $children = $cache->data['children'];
      }
      else {
        $definitions = $this->getDefinitions();
        // We build the hierarchy by finding all tabs that should
        // appear on the current route.
        $base_routes = [];
        $parents = [];
        $children = [];
        foreach ($definitions as $plugin_id => $task_info) {
          // Fill in the base_route from the parent to insure consistency.
          if (!empty($task_info['parent_id']) && !empty($definitions[$task_info['parent_id']])) {
            $task_info['base_route'] = $definitions[$task_info['parent_id']]['base_route'];
            // Populate the definitions we use in the next loop. Using a
            // reference like &$task_info causes bugs.
            $definitions[$plugin_id]['base_route'] = $definitions[$task_info['parent_id']]['base_route'];
          }
          if ($route_name == $task_info['route_name']) {
            if (!empty($task_info['base_route'])) {
              $base_routes[$task_info['base_route']] = $task_info['base_route'];
            }
            // Tabs that link to the current route are viable parents
            // and their parent and children should be visible also.
            // @todo - this only works for 2 levels of tabs.
            // instead need to iterate up.
            $parents[$plugin_id] = TRUE;
            if (!empty($task_info['parent_id'])) {
              $parents[$task_info['parent_id']] = TRUE;
            }
          }
        }
        if ($base_routes) {
          // Find all the plugins with the same root and that are at the top
          // level or that have a visible parent.
          foreach ($definitions as $plugin_id => $task_info) {
            if (!empty($base_routes[$task_info['base_route']]) && (empty($task_info['parent_id']) || !empty($parents[$task_info['parent_id']]))) {
              // Concat '> ' with root ID for the parent of top-level tabs.
              $parent = empty($task_info['parent_id']) ? '> ' . $task_info['base_route'] : $task_info['parent_id'];
              $children[$parent][$plugin_id] = $task_info;
            }
          }
        }
        $data = [
          'base_routes' => $base_routes,
          'parents' => $parents,
          'children' => $children,
        ];
        $this->cacheBackend->set($this->cacheKey . ':' . $route_name, $data, Cache::PERMANENT, $this->cacheTags);
      }
      // Create a plugin instance for each element of the hierarchy.
      foreach ($base_routes as $base_route) {
        // Convert the tree keyed by plugin IDs into a simple one with
        // integer depth.  Create instances for each plugin along the way.
        $level = 0;
        // We used this above as the top-level parent array key.
        $next_parent = '> ' . $base_route;
        do {
          $parent = $next_parent;
          $next_parent = FALSE;
          foreach ($children[$parent] as $plugin_id => $task_info) {
            $plugin = $this->createInstance($plugin_id);
            $this->instances[$route_name][$level][$plugin_id] = $plugin;
            // Normally, the link generator compares the href of every link with
            // the current path and sets the active class accordingly. But the
            // parents of the current local task may be on a different route in
            // which case we have to set the class manually by flagging it
            // active.
            if (!empty($parents[$plugin_id]) && $route_name != $task_info['route_name']) {
              $plugin->setActive();
            }
            if (isset($children[$plugin_id])) {
              // This tab has visible children.
              $next_parent = $plugin_id;
            }
          }
          $level++;
        } while ($next_parent);
      }

    }
    return $this->instances[$route_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getTasksBuild($current_route_name, RefinableCacheableDependencyInterface &$cacheability) {
    $tree = $this->getLocalTasksForRoute($current_route_name);
    $build = [];

    // Collect all route names.
    $route_names = [];
    foreach ($tree as $instances) {
      foreach ($instances as $child) {
        $route_names[] = $child->getRouteName();
      }
    }
    // Pre-fetch all routes involved in the tree. This reduces the number
    // of SQL queries that would otherwise be triggered by the access manager.
    if ($route_names) {
      $this->routeProvider->getRoutesByNames($route_names);
    }

    foreach ($tree as $level => $instances) {
      /** @var $instances \Drupal\Core\Menu\LocalTaskInterface[] */
      foreach ($instances as $plugin_id => $child) {
        $route_name = $child->getRouteName();
        $route_parameters = $child->getRouteParameters($this->routeMatch);

        // Given that the active flag depends on the route we have to add the
        // route cache context.
        $cacheability->addCacheContexts(['route']);
        $active = $this->isRouteActive($current_route_name, $route_name, $route_parameters);

        // The plugin may have been set active in getLocalTasksForRoute() if
        // one of its child tabs is the active tab.
        $active = $active || $child->getActive();
        // @todo It might make sense to use link render elements instead.

        $link = [
          'title' => $this->getTitle($child),
          'url' => Url::fromRoute($route_name, $route_parameters),
          'localized_options' => $child->getOptions($this->routeMatch),
        ];
        $access = $this->accessManager->checkNamedRoute($route_name, $route_parameters, $this->account, TRUE);
        $build[$level][$plugin_id] = [
          '#theme' => 'menu_local_task',
          '#link' => $link,
          '#active' => $active,
          '#weight' => $child->getWeight(),
          '#access' => $access,
        ];
        $cacheability->addCacheableDependency($access)->addCacheableDependency($child);
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalTasks($route_name, $level = 0) {
    if (!isset($this->taskData[$route_name])) {
      $cacheability = new CacheableMetadata();
      $cacheability->addCacheContexts(['route']);
      // Look for route-based tabs.
      $this->taskData[$route_name] = [
        'tabs' => [],
        'cacheability' => $cacheability,
      ];

      if (!$this->requestStack->getCurrentRequest()->attributes->has('exception')) {
        // Safe to build tasks only when no exceptions raised.
        $data = [];
        $local_tasks = $this->getTasksBuild($route_name, $cacheability);
        foreach ($local_tasks as $tab_level => $items) {
          $data[$tab_level] = empty($data[$tab_level]) ? $items : array_merge($data[$tab_level], $items);
        }
        $this->taskData[$route_name]['tabs'] = $data;
        // Allow modules to alter local tasks.
        $this->moduleHandler->alter('menu_local_tasks', $this->taskData[$route_name], $route_name, $cacheability);
        $this->taskData[$route_name]['cacheability'] = $cacheability;
      }
    }

    if (isset($this->taskData[$route_name]['tabs'][$level])) {
      return [
        'tabs' => $this->taskData[$route_name]['tabs'][$level],
        'route_name' => $route_name,
        'cacheability' => $this->taskData[$route_name]['cacheability'],
      ];
    }

    return [
      'tabs' => [],
      'route_name' => $route_name,
      'cacheability' => $this->taskData[$route_name]['cacheability'],
    ];
  }

  /**
   * Determines whether the route of a certain local task is currently active.
   *
   * @param string $current_route_name
   *   The route name of the current main request.
   * @param string $route_name
   *   The route name of the local task to determine the active status.
   * @param array $route_parameters
   *
   * @return bool
   *   Returns TRUE if the passed route_name and route_parameters is considered
   *   as the same as the one from the request, otherwise FALSE.
   */
  protected function isRouteActive($current_route_name, $route_name, $route_parameters) {
    // Flag the list element as active if this tab's route and parameters match
    // the current request's route and route variables.
    $active = $current_route_name == $route_name;
    if ($active) {
      // The request is injected, so we need to verify that we have the expected
      // _raw_variables attribute.
      $raw_variables_bag = $this->routeMatch->getRawParameters();
      // If we don't have _raw_variables, we assume the attributes are still the
      // original values.
      $raw_variables = $raw_variables_bag ? $raw_variables_bag->all() : $this->routeMatch->getParameters()->all();
      $active = array_intersect_assoc($route_parameters, $raw_variables) == $route_parameters;
    }
    return $active;
  }

}
