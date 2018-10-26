<?php

namespace Drupal\devel_generate\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides dynamic routes for devel_generate.
 */
class DevelGenerateRoutes implements ContainerInjectionInterface {

  /**
   * Constructs a new devel_generate route subscriber.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $devel_generate_manager
   *   The DevelGeneratePluginManager.
   */
  public function __construct(PluginManagerInterface $devel_generate_manager) {
    $this->DevelGenerateManager = $devel_generate_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.develgenerate')
    );
  }

  public function routes() {
    $devel_generate_plugins = $this->DevelGenerateManager->getDefinitions();

    $routes = array();
    foreach ($devel_generate_plugins as $id => $plugin) {
      $label = $plugin['label'];
      $type_url_str = str_replace('_', '-', $plugin['url']);
      $routes["devel_generate.$id"] = new Route(
        "admin/config/development/generate/$type_url_str",
        array(
          '_form' => '\Drupal\devel_generate\Form\DevelGenerateForm',
          '_title' => "Generate $label",
          '_plugin_id' => $id,
        ),
        array(
          '_permission' => $plugin['permission'],
        )
      );
    }

    return $routes;
  }

}
