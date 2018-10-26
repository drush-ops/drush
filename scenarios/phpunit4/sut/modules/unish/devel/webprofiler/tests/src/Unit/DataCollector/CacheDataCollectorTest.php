<?php

namespace Drupal\Tests\webprofiler\Unit\DataCollector;

use Drupal\webprofiler\Cache\CacheBackendWrapper;
use Drupal\webprofiler\DataCollector\CacheDataCollector;

/**
 * @coversDefaultClass \Drupal\webprofiler\DataCollector\CacheDataCollector
 *
 * @group webprofiler
 */
class CacheDataCollectorTest extends DataCollectorBaseTest {

  /**
   * @var \Drupal\webprofiler\DataCollector\CacheDataCollector
   */
  private $cacheDataCollector;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  private $cacheBackendInterface;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->cacheDataCollector = new CacheDataCollector();
    $this->cacheBackendInterface = $this->getMock('Drupal\Core\Cache\CacheBackendInterface');
  }

  /**
   * Tests the collection of a cache miss.
   */
  public function testCacheCollectorMiss() {
    $this->cacheBackendInterface->expects($this->once())
      ->method('get')
      ->will($this->returnValue(FALSE));

    $cacheBackendWrapper = new CacheBackendWrapper($this->cacheDataCollector, $this->cacheBackendInterface, 'default');
    $cache = $cacheBackendWrapper->get('cache_id');

    $this->assertFalse($cache);

    $this->assertEquals(1, $this->cacheDataCollector->getCacheMissesCount());
  }

  /**
   * Tests the collection of a cache hit.
   */
  public function testCacheCollectorHit() {
    $cache = new \stdClass();
    $cache->cid = 'cache_id';
    $cache->expire = 1;
    $cache->tags = ['tag1', 'tag2'];
    $this->cacheBackendInterface->expects($this->once())
      ->method('get')
      ->will($this->returnValue($cache));

    $cacheBackendWrapper = new CacheBackendWrapper($this->cacheDataCollector, $this->cacheBackendInterface, 'default');
    $cache2 = $cacheBackendWrapper->get('cache_id');

    $this->assertNotNull($cache2);

    $this->assertEquals(1, $this->cacheDataCollector->getCacheHitsCount());
  }

}
