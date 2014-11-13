<?php

namespace Drush\Queue;

abstract class QueueBase implements QueueInterface {

  /**
   * Keep track of queue definitions.
   *
   * @var array
   */
  protected $queues;

  /**
   * Validate the given command.
   *
   * @param string $command
   *   The command to validate.
   */
  public function validate($command = '') {
    // No default validation.
  }

  /**
   * Lists all available queues.
   */
  public function listQueues() {
    $result = array();
    foreach (array_keys($this->getQueues()) as $name) {
      $q = $this->getQueue($name);
      $result[$name] = array(
        'queue' => $name,
        'items' => $q->numberOfItems(),
        'class' => get_class($q),
      );
    }
    return $result;
  }

  /**
   * Prepare the environment for running a queue.
   */
  protected function prepareEnvironment() {
    // Try to increase the maximum execution time if it is too low.
    $max_execution_time = ini_get('max_execution_time');
    if ($max_execution_time > 0 && $max_execution_time < 240 && !ini_get('safe_mode')) {
      set_time_limit(240);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo($name) {
    $this->getQueues();
    if (!isset($this->queues[$name])) {
      throw new QueueException(dt('Could not find the !name queue.', array('!name' => $name)));
    }
    return $this->queues[$name];
  }

  /**
   * Runs a given queue.
   *
   * @param string $name
   *   The name of the queue to run.
   */
  public function run($name) {
    $this->prepareEnvironment();
    $info = $this->getInfo($name);
    $function = $info['worker callback'];
    $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
    $queue = $this->getQueue($name);
    while (time() < $end && ($item = $queue->claimItem())) {
      $function($item->data);
      $queue->deleteItem($item);
    }
  }

}
