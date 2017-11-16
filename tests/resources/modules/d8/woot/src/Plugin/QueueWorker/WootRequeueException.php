<?php

namespace Drupal\woot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;

/**
 * Queue worker used to test RequeueException.
 *
 * @QueueWorker(
 *   id = "woot_requeue_exception",
 *   title = @Translation("RequeueException test"),
 *   cron = {"time" = 60}
 * )
 */
class WootRequeueException extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
    public function processItem($data)
    {
        $state = \Drupal::state();
        if (!$state->get('woot_requeue_exception')) {
            $state->set('woot_requeue_exception', 1);
            throw new RequeueException('I am not done yet!');
        } else {
            $state->set('woot_requeue_exception', 2);
        }
    }

}
