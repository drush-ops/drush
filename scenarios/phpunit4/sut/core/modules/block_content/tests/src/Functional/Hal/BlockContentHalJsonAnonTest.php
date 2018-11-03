<?php

namespace Drupal\Tests\block_content\Functional\Hal;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\block_content\Functional\Rest\BlockContentResourceTestBase;
use Drupal\Tests\hal\Functional\EntityResource\HalEntityNormalizationTrait;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * @group hal
 */
class BlockContentHalJsonAnonTest extends BlockContentResourceTestBase {

  use HalEntityNormalizationTrait;
  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['hal'];

  /**
   * {@inheritdoc}
   */
  protected static $format = 'hal_json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/hal+json';

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $default_normalization = parent::getExpectedNormalizedEntity();

    $normalization = $this->applyHalFieldNormalization($default_normalization);

    return $normalization + [
      '_links' => [
        'self' => [
          'href' => $this->baseUrl . '/block/1?_format=hal_json',
        ],
        'type' => [
          'href' => $this->baseUrl . '/rest/type/block_content/basic',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return parent::getNormalizedPostEntity() + [
      '_links' => [
        'type' => [
          'href' => $this->baseUrl . '/rest/type/block_content/basic',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    // The 'url.site' cache context is added for '_links' in the response.
    return Cache::mergeTags(parent::getExpectedCacheContexts(), ['url.site']);
  }

}
