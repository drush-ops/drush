<?php

namespace Drupal\devel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\devel\Annotation\DevelDumper;

/**
 * Plugin type manager for Devel Dumper plugins.
 *
 * @see \Drupal\devel\Annotation\DevelDumper
 * @see \Drupal\devel\DevelDumperInterface
 * @see \Drupal\devel\DevelDumperBase
 * @see plugin_api
 */
class DevelDumperPluginManager extends DefaultPluginManager implements DevelDumperPluginManagerInterface {

  /**
   * Constructs a DevelDumperPluginManager object.
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
    parent::__construct('Plugin/Devel/Dumper', $namespaces, $module_handler, DevelDumperInterface::class, DevelDumper::class);
    $this->setCacheBackend($cache_backend, 'devel_dumper_plugins');
    $this->alterInfo('devel_dumper_info');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    $definition['supported'] = (bool) call_user_func([$definition['class'], 'checkRequirements']);
  }

  /**
   * {@inheritdoc}
   */
  public function isPluginSupported($plugin_id) {
    $definition = $this->getDefinition($plugin_id, FALSE);
    return $definition && $definition['supported'];
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if (!$this->isPluginSupported($plugin_id)) {
      $plugin_id = $this->getFallbackPluginId($plugin_id);
    }
    return parent::createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []) {
    return 'default';
  }

}
