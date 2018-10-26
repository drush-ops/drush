<?php

namespace Drupal\webprofiler\Command;

/**
 * Class BenchmarkData
 */
class BenchmarkData {

  /**
   * @var
   */
  private $token;

  /**
   * @var
   */
  private $memory;

  /**
   * @var
   */
  private $time;

  /**
   * @param $token
   * @param $memory
   * @param $time
   */
  public function __construct($token, $memory, $time) {
    $this->token = $token;
    $this->memory = $memory;
    $this->time = $time;
  }

  /**
   * @return mixed
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * @return mixed
   */
  public function getMemory() {
    return $this->memory;
  }

  /**
   * @return mixed
   */
  public function getTime() {
    return $this->time;
  }

}
