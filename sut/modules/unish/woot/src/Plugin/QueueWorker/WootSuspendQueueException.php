<?php

declare(strict_types=1);

namespace Drupal\woot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * Queue worker used to test SuspendQueueException.
 *
 * @QueueWorker(
 *   id = "woot_suspend_queue_exception",
 *   title = @Translation("SuspendQueueException test"),
 *   cron = {"time" = 60}
 * )
 */
class WootSuspendQueueException extends QueueWorkerBase
{
  /**
   * {@inheritdoc}
   */
    public function processItem($data)
    {
        throw new SuspendQueueException('Remote service unavailable, try again later.');
    }
}
