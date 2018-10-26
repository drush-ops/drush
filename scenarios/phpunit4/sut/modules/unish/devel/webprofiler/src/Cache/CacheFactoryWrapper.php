<?php

namespace Drupal\webprofiler\Cache;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\webprofiler\DataCollector\CacheDataCollector;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Wraps a cache factory to register all calls to the cache system.
 */
class CacheFactoryWrapper implements CacheFactoryInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * The cache data collector.
   *
   * @var \Drupal\webprofiler\DataCollector\CacheDataCollector
   */
  protected $cacheDataCollector;

  /**
   * All wrapped cache backends.
   *
   * @var \Drupal\webprofiler\Cache\CacheBackendWrapper[]
   */
  protected $cacheBackends = [];

  /**
   * Creates a new CacheFactoryWrapper instance.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\webprofiler\DataCollector\CacheDataCollector $cacheDataCollector
   *   The cache data collector.
   */
  public function __construct(CacheFactoryInterface $cache_factory, CacheDataCollector $cacheDataCollector) {
    $this->cacheFactory = $cache_factory;
    $this->cacheDataCollector = $cacheDataCollector;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset($this->cacheBackends[$bin])) {
      $cache_backend = $this->cacheFactory->get($bin);
      $this->cacheBackends[$bin] = new CacheBackendWrapper($this->cacheDataCollector, $cache_backend, $bin);
    }
    return $this->cacheBackends[$bin];
  }

}
