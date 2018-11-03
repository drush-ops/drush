<?php

namespace Drupal\Core\Cache;

use Drupal\Core\Site\Settings;

class ApcuBackendFactory implements CacheFactoryInterface {

  /**
   * The site prefix string.
   *
   * @var string
   */
  protected $sitePrefix;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The APCU backend class to use.
   *
   * @var string
   */
  protected $backendClass;

  /**
   * Constructs an ApcuBackendFactory object.
   *
   * @param string $root
   *   The app root.
   * @param string $site_path
   *   The site path.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   *   The cache tags checksum provider.
   */
  public function __construct($root, $site_path, CacheTagsChecksumInterface $checksum_provider) {
    $this->sitePrefix = Settings::getApcuPrefix('apcu_backend', $root, $site_path);
    $this->checksumProvider = $checksum_provider;
    if (version_compare(phpversion('apcu'), '5.0.0', '>=')) {
      $this->backendClass = 'Drupal\Core\Cache\ApcuBackend';
    }
    else {
      $this->backendClass = 'Drupal\Core\Cache\Apcu4Backend';
    }
  }

  /**
   * Gets ApcuBackend for the specified cache bin.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\Core\Cache\ApcuBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new $this->backendClass($bin, $this->sitePrefix, $this->checksumProvider);
  }

}
