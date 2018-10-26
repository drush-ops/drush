<?php

namespace Drupal\webprofiler\State;

use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\State\StateInterface;
use Drupal\webprofiler\DataCollector\StateDataCollector;

/**
 * Class StateWrapper.
 */
class StateWrapper extends CacheCollector implements StateInterface {

  /**
   * The system state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The state data collector.
   *
   * @var \Drupal\webprofiler\DataCollector\StateDataCollector
   */
  private $dataCollector;

  /**
   * StateWrapper constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The system state.
   * @param \Drupal\webprofiler\DataCollector\StateDataCollector $dataCollector
   *   The state data collector.
   */
  public function __construct(StateInterface $state, StateDataCollector $dataCollector) {
    $this->state = $state;
    $this->dataCollector = $dataCollector;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    $this->dataCollector->addState($key);

    return $this->state->get($key, $default);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    foreach ($keys as $key) {
      $this->dataCollector->addState($key);
    }

    return $this->state->getMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->state->set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data) {
    $this->state->setMultiple($data);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $this->state->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys) {
    $this->state->deleteMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    $this->state->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveCacheMiss($key) {
    return $this->state->resolveCacheMiss($key);
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    $this->updateCache();
  }

  /**
   * Passes through all non-tracked calls onto the decorated object.
   *
   * @param string $method
   *   The called method.
   * @param mixed $args
   *   The passed in arguments.
   *
   * @return mixed
   *   The return argument of the call.
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->state, $method], $args);
  }

}
