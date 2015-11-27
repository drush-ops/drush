<?php

namespace Drush\Queue;

use Drush\Log\LogLevel;
use DrupalQueue;

class Queue7 extends QueueBase {

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset(static::$queues)) {
      static::$queues = module_invoke_all('cron_queue_info');
      drupal_alter('cron_queue_info', static::$queues);
      // Merge in queues from modules that implement hook_queue_info.
      // Currently only defined by the queue_ui module.
      $info_queues = module_invoke_all('queue_info');
      foreach ($info_queues as $name => $queue) {
        static::$queues[$name]['worker callback'] = $queue['cron']['callback'];
        if (isset($queue['cron']['time'])) {
          static::$queues[$name]['time'] = $queue['cron']['time'];
        }
      }
    }
    return static::$queues;
  }

  /**
   * {@inheritdoc}
   *
   * @return \DrupalQueueInterface
   */
  public function getQueue($name) {
    return DrupalQueue::get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function run($name, $time_limit = 0) {
    $info = $this->getInfo($name);
    $function = $info['worker callback'];
    $end = time() + $time_limit;
    $queue = $this->getQueue($name);
    $count = 0;

    while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
      try {
        drush_log(dt('Processing item @id from @name queue.', array('@name' => $name, 'id' => $item->item_id)), LogLevel::INFO);
        $function($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (\Exception $e) {
        // In case of exception log it and leave the item in the queue
        // to be processed again later.
        drush_set_error('DRUSH_QUEUE_EXCEPTION', $e->getMessage());
      }
    }

    return $count;
  }

}
