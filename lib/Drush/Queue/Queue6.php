<?php

namespace Drush\Queue;

class Queue6 extends Queue7 {

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    // Drupal 6 has no core queue capabilities, and thus requires contrib.
    if (!module_exists('drupal_queue')) {
      throw new QueueException(dt('The drupal_queue module need to be installed/enabled.'));
    }
    else {
      drupal_queue_include();
    }

    if (!isset($this->queues)) {
      $this->queues = module_invoke_all('cron_queue_info');
      drupal_alter('cron_queue_info', $this->queues);
      foreach($this->queues as $name => $queue) {
        $this->queues[$name]['worker callback'] = $queue['worker callback'];
        if (isset($queue['time'])) {
          $this->queues[$name]['cron']['time'] = $queue['time'];
        }
      }
    }
    return $this->queues;
  }

}
