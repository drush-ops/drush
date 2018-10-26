<?php

namespace Drupal\devel_generate;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin type manager for DevelGenerate plugins.
 */
Class DevelGeneratePluginManager extends DefaultPluginManager {

  /**
   * Constructs a DevelGeneratePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DevelGenerate', $namespaces, $module_handler, NULL, 'Drupal\devel_generate\Annotation\DevelGenerate');
    $this->alterInfo('devel_generate_info');
    $this->setCacheBackend($cache_backend, 'devel_generate_plugins');
  }

}
