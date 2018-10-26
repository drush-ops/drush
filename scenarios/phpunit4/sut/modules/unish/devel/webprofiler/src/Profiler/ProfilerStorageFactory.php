<?php

namespace Drupal\webprofiler\Profiler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProfilerStorageFactory
 */
class ProfilerStorageFactory {

  /**
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface
   */
  final public static function getProfilerStorage(ConfigFactoryInterface $config, ContainerInterface $container) {
    $storage = $config->get('webprofiler.config')
      ->get('storage') ?: 'profiler.database_storage';

    return $container->get($storage);
  }

}
