<?php

declare(strict_types=1);

namespace Drupal\woot\Plugin\QueueWorker;

use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Queue worker used to test DelayedRequeueException.
 *
 * @QueueWorker(
 *   id = "woot_delayed_requeue_exception",
 *   title = @Translation("DelayedRequeueException test"),
 *   cron = {"time" = 60}
 * )
 */
class WootDelayedRequeueException extends QueueWorkerBase
{
  /**
   * {@inheritdoc}
   */
    public function processItem($data)
    {
        throw new DelayedRequeueException(3600, 'Come back later.');
    }
}
