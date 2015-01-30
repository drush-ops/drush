<?php

namespace Drush\Queue;

class Queue8 extends QueueBase {

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $workerManager;

  /**
   * Set the queue worker manager.
   */
  public function __construct(QueueWorkerManager $manager = NULL) {
    $this->workerManager = $manager ?: \Drupal::service('plugin.manager.queue_worker');
  }

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset($this->queues)) {
      foreach ($this->workerManager->getDefinitions() as $name => $info) {
        $this->queues[$name] = $info;
      }
    }
    return $this->queues;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue($name) {
    return \Drupal::queue($name);
  }

  /**
   * {@inheritdoc}
   */
  public function run($name) {
    $info = $this->getInfo($name);
    $worker = $this->workerManager->createInstance($name);
    $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
    $queue = $this->getQueue($name);
    while (time() < $end && ($item = $queue->claimItem())) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }

  }

}
