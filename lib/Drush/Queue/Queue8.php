<?php

namespace Drush\Queue;

class Queue8 extends QueueBase {

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset($this->queues)) {
      $this->queues = \Drupal::moduleHandler()->invokeAll('queue_info');
      \Drupal::moduleHandler()->alter('queue_info', $this->queues);
    }
    return $this->queues;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue($name) {
    return \Drupal::queue($name);
  }

}
