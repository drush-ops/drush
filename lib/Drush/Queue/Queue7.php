<?php

namespace Drush\Queue;

use \DrupalQueue;

class Queue7 extends QueueBase {

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset($this->queues)) {
      $this->queues = module_invoke_all('cron_queue_info');
      drupal_alter('cron_queue_info', $this->queues);
      foreach($this->queues as $name => $queue) {
        $this->queues[$name]['worker callback'] = $queue['worker callback'];
        if (isset($queue['time'])) {
          $this->queues[$name]['cron']['time'] = $queue['time'];
        }
      }
      // Merge in queues from modules that implement hook_queue_info.
      // Currently only defined by the queue_ui module.
      $info_queues = module_invoke_all('queue_info');
      foreach($info_queues as $name => $queue) {
        $this->queues[$name]['worker callback'] = $queue['cron']['callback'];
        if (isset($queue['cron']['time'])) {
          $this->queues[$name]['cron']['time'] = $queue['cron']['time'];
        }
      }
    }
    return $this->queues;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue($name) {
    return DrupalQueue::get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function run($name) {
    $info = $this->getInfo($name);
    $function = $info['worker callback'];
    $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
    $queue = $this->getQueue($name);
    while (time() < $end && ($item = $queue->claimItem())) {
      $function($item->data);
      $queue->deleteItem($item);
    }
  }

}
