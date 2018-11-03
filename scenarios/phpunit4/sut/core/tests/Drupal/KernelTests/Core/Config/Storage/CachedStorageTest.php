<?php

namespace Drupal\KernelTests\Core\Config\Storage;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests CachedStorage operations.
 *
 * @group config
 */
class CachedStorageTest extends ConfigStorageTestBase {

  /**
   * The cache backend the cached storage is using.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The file storage the cached storage is using.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $fileStorage;

  protected function setUp() {
    parent::setUp();
    // Create a directory.
    $dir = PublicStream::basePath() . '/config';
    $this->fileStorage = new FileStorage($dir);
    $this->storage = new CachedStorage($this->fileStorage, \Drupal::service('cache.config'));
    $this->cache = \Drupal::service('cache_factory')->get('config');
    // ::listAll() verifications require other configuration data to exist.
    $this->storage->write('system.performance', []);
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidStorage() {
    // No-op as this test does not make sense.
  }

  /**
   * {@inheritdoc}
   */
  protected function read($name) {
    $data = $this->cache->get($name);
    // Cache misses fall through to the underlying storage.
    return $data ? $data->data : $this->fileStorage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  protected function insert($name, $data) {
    $this->fileStorage->write($name, $data);
    $this->cache->set($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  protected function update($name, $data) {
    $this->fileStorage->write($name, $data);
    $this->cache->set($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  protected function delete($name) {
    $this->cache->delete($name);
    unlink($this->fileStorage->getFilePath($name));
  }

  /**
   * {@inheritdoc}
   */
  public function containerBuild(ContainerBuilder $container) {
    parent::containerBuild($container);
    // Use the regular database cache backend to aid testing.
    $container->register('cache_factory', 'Drupal\Core\Cache\DatabaseBackendFactory')
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('cache_tags.invalidator.checksum'));
  }

}
