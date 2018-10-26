<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\Frontend\PerformanceTimingData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects data about frontend performance.
 */
class PerformanceTimingDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {

  }

  /**
   * @param $data
   */
  public function setData($data) {
    $this->data['performance'] = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'performance_timing';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Performance Timing');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    if (isset($this->data['performance'])) {
      $performanceData = new PerformanceTimingData($this->data['performance']);
      return $this->t('TTFB: @ttfb', ['@ttfb' => sprintf('%.0f ms', $performanceData->getTtfbTiming())]);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAQJJREFUeNpi/P//PwO1ARMDDcCooUPEUEYi1AgAcQIQ+0P5H4B4IxAvwKaYmCSqAMT7gbgBajhMrB8qLkCsoQFQQ0CuOw/EBjgsLIAajmEouvdBhukD8UQgdkASwwXuA7EhNEhwuvQ8iXHSj2Q53FBY7BtADVxIoqEfoQYnYJPEF3bEROZ6WDDBvO+ALcCxJCsBAmpA4SuA7P2PBDQUEOGTDTA1TNCYs6dCRgIlxQswQ0GMB0A8nwgv4gqa+VCXgpMWC1QiEerF9WgaDmJJp/OhkUNIHUHQgJ4ecQHkiMKXXALQIowqpdR8pJi/AA0qvC4lFsyHYqK8zzhaRQ8NQwECDABNaU12xhTp2QAAAABJRU5ErkJggg==';
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = $this->data;

    if (isset($this->data['performance'])) {
      $performanceData = new PerformanceTimingData($this->data['performance']);
      $data['performance']['computed']['DNS lookup time'] = $performanceData->getDNSTiming();
      $data['performance']['computed']['TCP handshake time'] = $performanceData->getTCPTiming();
      $data['performance']['computed']['Time to first byte'] = $performanceData->getTtfbTiming();
      $data['performance']['computed']['Data download time'] = $performanceData->getDataTiming();
      $data['performance']['computed']['DOM building time'] = $performanceData->getDomTiming();
    }

    return $data;
  }
}
