<?php

namespace Drush\Queue;

use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\SuspendQueueException;

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
    if (!isset(static::$queues)) {
      static::$queues = array();
      foreach ($this->workerManager->getDefinitions() as $name => $info) {
        static::$queues[$name] = $info;
      }
    }
    return static::$queues;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Queue\QueueInterface
   */
  public function getQueue($name) {
    return \Drupal::queue($name);
  }

  /**
   * {@inheritdoc}
   */
  public function run($name, $time_limit = 0) {
    $worker = $this->workerManager->createInstance($name);
    $end = time() + $time_limit;
    $queue = $this->getQueue($name);
    $count = 0;

    while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
      try {
        drush_log(dt('Processing item @id from @name queue.', array('@name' => $name, 'id' => $item->item_id)), 'info');
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);
        drush_set_error('DRUSH_SUSPEND_QUEUE_EXCEPTION', $e->getMessage());
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        drush_set_error('DRUSH_QUEUE_EXCEPTION', $e->getMessage());
      }
    }

    return $count;
  }

}
