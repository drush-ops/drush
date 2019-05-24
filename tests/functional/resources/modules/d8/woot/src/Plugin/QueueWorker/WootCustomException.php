<?php

namespace Drupal\woot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Queue worker used to test RequeueException.
 *
 * @QueueWorker(
 *   id = "woot_custom_exception",
 *   title = @Translation("CustomException queue test"),
 *   cron = {"time" = 60}
 * )
 */
class WootCustomException extends QueueWorkerBase
{

  /**
   * {@inheritdoc}
   */
    public function processItem($data)
    {
        $state = \Drupal::state();
        if (!$state->get('woot_custom_exception')) {
            $state->set('woot_custom_exception', 1);
            throw new CustomException('Something went wrong, skip me!');
        } else {
            $state->set('woot_custom_exception', 2);
        }
    }
}

/**
 * CustomException for test purposes.
 */
class CustomException extends \Exception {};
