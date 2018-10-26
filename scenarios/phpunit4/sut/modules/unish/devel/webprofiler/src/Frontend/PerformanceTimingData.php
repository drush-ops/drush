<?php

namespace Drupal\webprofiler\Frontend;

/**
 * Class PerformanceTimingData
 */
class PerformanceTimingData {

  /**
   * @var array
   */
  private $data;

  /**
   * @param array $data
   */
  public function __construct($data) {
    $this->data = $data;
  }

  /**
   * @return int
   */
  public function getDNSTiming() {
    if (isset($this->data['domainLookupEnd']) && isset($this->data['domainLookupStart'])) {
      return $this->data['domainLookupEnd'] - $this->data['domainLookupStart'];
    }
    else {
      return 0;
    }
  }

  /**
   * @return int
   */
  public function getTCPTiming() {
    if (isset($this->data['connectEnd']) && isset($this->data['connectStart'])) {
      return $this->data['connectEnd'] - $this->data['connectStart'];
    }
    else {
      return 0;
    }
  }

  /**
   * @return int
   */
  public function getTtfbTiming() {
    if (isset($this->data['responseStart']) && isset($this->data['connectEnd'])) {
      return $this->data['responseStart'] - $this->data['connectEnd'];
    }
    else {
      return 0;
    }
  }

  /**
   * @return int
   */
  public function getDataTiming() {
    if (isset($this->data['responseEnd']) && isset($this->data['responseStart'])) {
      return $this->data['responseEnd'] - $this->data['responseStart'];
    }
    else {
      return 0;
    }
  }

  /**
   * @return int
   */
  public function getDomTiming() {
    if (isset($this->data['loadEventStart']) && isset($this->data['responseEnd'])) {
      return $this->data['loadEventStart'] - $this->data['responseEnd'];
    }
    else {
      return 0;
    }
  }
}
