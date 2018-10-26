<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects the used cache bins and cache CIDs.
 */
class CacheDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  const WEBPROFILER_CACHE_HIT = 'bin_cids_hit';
  const WEBPROFILER_CACHE_MISS = 'bin_cids_miss';

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
  }

  /**
   *
   */
  public function __construct() {
    $this->data['total'][CacheDataCollector::WEBPROFILER_CACHE_HIT] = 0;
    $this->data['total'][CacheDataCollector::WEBPROFILER_CACHE_MISS] = 0;
    $this->data['cache'] = [];
  }

  /**
   * Registers a cache get on a specific cache bin.
   *
   * @param $cache
   */
  public function registerCacheHit($bin, $cache) {
    $current = isset($this->data['cache'][$bin][$cache->cid]) ? $this->data['cache'][$bin][$cache->cid] : NULL;

    if (!$current) {
      $current = $cache;
      $current->{CacheDataCollector::WEBPROFILER_CACHE_HIT} = 0;
      $current->{CacheDataCollector::WEBPROFILER_CACHE_MISS} = 0;
      $this->data['cache'][$bin][$cache->cid] = $current;
    }

    $current->{CacheDataCollector::WEBPROFILER_CACHE_HIT}++;
    $this->data['total'][CacheDataCollector::WEBPROFILER_CACHE_HIT]++;
  }

  /**
   * Registers a cache get on a specific cache bin.
   *
   * @param $bin
   * @param $cid
   */
  public function registerCacheMiss($bin, $cid) {
    $current = isset($this->data['cache'][$bin][$cid]) ?
      $this->data['cache'][$bin][$cid] : NULL;

    if (!$current) {
      $current = new \StdClass();
      $current->{CacheDataCollector::WEBPROFILER_CACHE_HIT} = 0;
      $current->{CacheDataCollector::WEBPROFILER_CACHE_MISS} = 0;
      $this->data['cache'][$bin][$cid] = $current;
    }

    $current->{CacheDataCollector::WEBPROFILER_CACHE_MISS}++;
    $this->data['total'][CacheDataCollector::WEBPROFILER_CACHE_MISS]++;
  }

  /**
   * Callback to return the total amount of requested cache CIDS.
   *
   * @param string $type
   *
   * @return int
   */
  public function getCacheCidsCount($type) {
    return $this->data['total'][$type];
  }

  /**
   * Callback to return the total amount of hit cache CIDS.
   *
   * @return int
   */
  public function getCacheHitsCount() {
    return $this->getCacheCidsCount(CacheDataCollector::WEBPROFILER_CACHE_HIT);
  }

  /**
   * Callback to return the total amount of miss cache CIDS.
   *
   * @return int
   */
  public function getCacheMissesCount() {
    return $this->getCacheCidsCount(CacheDataCollector::WEBPROFILER_CACHE_MISS);
  }

  /**
   * Callback to return the total amount of hit cache CIDs keyed by bin.
   *
   * @param $type
   *
   * @return array
   */
  public function cacheCids($type) {
    $hits = [];
    foreach ($this->data['cache'] as $bin => $caches) {
      $hits[$bin] = 0;
      foreach ($caches as $cid => $cache) {
        $hits[$bin] += $cache->{$type};
      }
    }

    return $hits;
  }

  /**
   * Callback to return hit cache CIDs keyed by bin.
   *
   * @return array
   */
  public function getCacheHits() {
    return $this->cacheCids(CacheDataCollector::WEBPROFILER_CACHE_HIT);
  }

  /**
   * Callback to return miss cache CIDs keyed by bin.
   *
   * @return array
   */
  public function getCacheMisses() {
    return $this->cacheCids(CacheDataCollector::WEBPROFILER_CACHE_MISS);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'cache';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Cache');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t('Hit: @cache_hit, miss: @cache_miss', [
      '@cache_hit' => $this->getCacheCidsCount(CacheDataCollector::WEBPROFILER_CACHE_HIT),
      '@cache_miss' => $this->getCacheCidsCount(CacheDataCollector::WEBPROFILER_CACHE_MISS),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAcCAYAAABh2p9gAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYxIDY0LjE0MDk0OSwgMjAxMC8xMi8wNy0xMDo1NzowMSAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENTNS4xIE1hY2ludG9zaCIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2Njc3QTVEQTkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2Njc3QTVEQjkxNkMxMUUzQjA3OUEzQTNEMUVGMjVDOCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjRGQTVBQzYxOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjRGQTVBQzYyOTE2QzExRTNCMDc5QTNBM0QxRUYyNUM4Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+BsBwAAAAAJtJREFUeNpi/P//PwM1ARMDlcEIMdDBweEZjM0IihSgwEx8Gg4cOJCOrhGHOimqe5kF2QVYvDITl0vQvQwTo4oLkS1gQrPpPwiTEBkY6pnwKJ5JyOskJRvkcMUVxjgjhRhDsUUGSQZu3rwZb1j6+voyjhYOI9VAFmKTBTC3oMsTbyAx+RndAqxejo2NJdmL6HoYR6vRwWcgQIABAOn0PsqqgQzcAAAAAElFTkSuQmCC';
  }
}
