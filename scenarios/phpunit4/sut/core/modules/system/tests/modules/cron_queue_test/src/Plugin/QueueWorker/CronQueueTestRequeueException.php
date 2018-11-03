<?php

namespace Drupal\cron_queue_test\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;

/**
 * @QueueWorker(
 *   id = "cron_queue_test_requeue_exception",
 *   title = @Translation("RequeueException test"),
 *   cron = {"time" = 60}
 * )
 */
class CronQueueTestRequeueException extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $state = \Drupal::state();
    if (!$state->get('cron_queue_test_requeue_exception')) {
      $state->set('cron_queue_test_requeue_exception', 1);
      throw new RequeueException('I am not done yet!');
    }
    else {
      $state->set('cron_queue_test_requeue_exception', 2);
    }
  }

}
