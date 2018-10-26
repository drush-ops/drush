<?php

namespace Drupal\webprofiler\DependencyInjection;

use Drupal\Component\Utility\Timer;
use Drupal\Core\DependencyInjection\Container;

/**
 * Extends the Drupal container class to trace service instantiations.
 */
class TraceableContainer extends Container {

  /**
   * @var array
   */
  protected $tracedData;

  /**
   * @var \Symfony\Component\Stopwatch\Stopwatch
   */
  private $stopwatch = NULL;

  /**
   * @var bool
   */
  private $hasStopwatch = FALSE;

  /**
   * @param string $id
   * @param int $invalidBehavior
   *
   * @return object
   */
  public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE) {
    if (!$this->stopwatch && $this->has('stopwatch')) {
      $this->stopwatch = parent::get('stopwatch');
      $this->stopwatch->openSection();
      $this->hasStopwatch = TRUE;
    }

    if ('stopwatch' === $id) {
      return $this->stopwatch;
    }

    Timer::start($id);
    if ($this->hasStopwatch) {
      $e = $this->stopwatch->start($id, 'service');
    }

    $service = parent::get($id, $invalidBehavior);

    $this->tracedData[$id] = Timer::stop($id);
    if ($this->hasStopwatch && $e->isStarted()) {
      $e->stop();
    }

    return $service;
  }

  /**
   * @return array
   */
  public function getTracedData() {
    return $this->tracedData;
  }
}
