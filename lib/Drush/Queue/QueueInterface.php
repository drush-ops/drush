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
   */
  public function run($name);

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
