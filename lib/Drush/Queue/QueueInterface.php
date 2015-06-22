<?php

namespace Drush\Queue;

/**
 * Defines an interface for interacting with queues.
 */
interface QueueInterface {

  /**
   * Returns all queues.
   */
  public function getQueues();

  /**
   * Runs a given queue.
   *
   * @param string $name
   *   The name of the queue to run.
   * @param int $time_limit
   *   The maximum number of seconds that the queue can run. By default the
   *   queue will be run as long as possible.
   *
   * @return int
   *   The number of items successfully processed from the queue.
   */
  public function run($name, $time_limit = 0);

  /**
   * Returns a given queue definition.
   *
   * @param string $name
   *   The name of the queue to run.
   */
  public function getQueue($name);

  /**
   * Returns a given queue definition.
   *
   * @param string $name
   *   The name of the queue to run.
   */
  public function getInfo($name);

}
