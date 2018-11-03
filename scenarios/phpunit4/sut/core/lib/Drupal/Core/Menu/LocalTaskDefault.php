<?php

namespace Drupal\Core\Menu;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default object used for LocalTaskPlugins.
 */
class LocalTaskDefault extends PluginBase implements LocalTaskInterface, CacheableDependencyInterface {

  use DependencySerializationTrait;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * TRUE if this plugin is forced active for options attributes.
   *
   * @var bool
   */
  protected $active = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return $this->pluginDefinition['route_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = isset($this->pluginDefinition['route_parameters']) ? $this->pluginDefinition['route_parameters'] : [];
    $route = $this->routeProvider()->getRouteByName($this->getRouteName());
    $variables = $route->compile()->getVariables();

    // Normally the \Drupal\Core\ParamConverter\ParamConverterManager has
    // processed the Request attributes, and in that case the _raw_variables
    // attribute holds the original path strings keyed to the corresponding
    // slugs in the path patterns. For example, if the route's path pattern is
    // /filter/tips/{filter_format} and the path is /filter/tips/plain_text then
    // $raw_variables->get('filter_format') == 'plain_text'.

    $raw_variables = $route_match->getRawParameters();

    foreach ($variables as $name) {
      if (isset($parameters[$name])) {
        continue;
      }

      if ($raw_variables && $raw_variables->has($name)) {
        $parameters[$name] = $raw_variables->get($name);
      }
      elseif ($value = $route_match->getRawParameter($name)) {
        $parameters[$name] = $value;
      }
    }
    // The UrlGenerator will throw an exception if expected parameters are
    // missing. This method should be overridden if that is possible.
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    // The title from YAML file discovery may be a TranslatableMarkup object.
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * Returns the weight of the local task.
   *
   * @return int
   *   The weight of the task. If not defined in the annotation returns 0 by
   *   default or -10 for the root tab.
   */
  public function getWeight() {
    // By default the weight is 0, or -10 for the root tab.
    if (!isset($this->pluginDefinition['weight'])) {
      if ($this->pluginDefinition['base_route'] == $this->pluginDefinition['route_name']) {
        $this->pluginDefinition['weight'] = -10;
      }
      else {
        $this->pluginDefinition['weight'] = 0;
      }
    }
    return (int) $this->pluginDefinition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = $this->pluginDefinition['options'];
    if ($this->active) {
      if (empty($options['attributes']['class']) || !in_array('is-active', $options['attributes']['class'])) {
        $options['attributes']['class'][] = 'is-active';
      }
    }
    return (array) $options;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active = TRUE) {
    $this->active = $active;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActive() {
    return $this->active;
  }

  /**
   * Returns the route provider.
   *
   * @return \Drupal\Core\Routing\RouteProviderInterface
   *   The route provider.
   */
  protected function routeProvider() {
    if (!$this->routeProvider) {
      $this->routeProvider = \Drupal::service('router.route_provider');
    }
    return $this->routeProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!isset($this->pluginDefinition['cache_tags'])) {
      return [];
    }
    return $this->pluginDefinition['cache_tags'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if (!isset($this->pluginDefinition['cache_contexts'])) {
      return [];
    }
    return $this->pluginDefinition['cache_contexts'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    if (!isset($this->pluginDefinition['cache_max_age'])) {
      return Cache::PERMANENT;
    }
    return $this->pluginDefinition['cache_max_age'];
  }

}
