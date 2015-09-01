<?php

namespace Drush\Queue;

class Queue6 extends Queue7 {

  public function __construct() {
    // Drupal 6 has no core queue capabilities, and thus requires contrib.
    if (!module_exists('drupal_queue')) {
      throw new QueueException(dt('The drupal_queue module need to be installed/enabled.'));
    }
    else {
      drupal_queue_include();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset(static::$queues)) {
      static::$queues = module_invoke_all('cron_queue_info');
      drupal_alter('cron_queue_info', static::$queues);
    }
    return static::$queues;
  }

}
