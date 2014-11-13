<?php

namespace Drush\Queue;

class Queue6 extends Queue7 {

  /**
   * {@inheritdoc}
   */
  public function validate($command) {
    // Drupal 6 has no core queue capabilities, and thus requires contrib.
    if (!module_exists('drupal_queue')) {
      $args = array('!command' => $command, '!dependencies' => 'drupal_queue');
      throw new QueueException(dt('Command !command needs the following modules installed/enabled to run: !dependencies.', $args));
    }
    else {
      drupal_queue_include();
    }
  }

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
    }
    return $this->queues;
  }

}
