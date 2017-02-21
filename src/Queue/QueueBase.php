<?php

namespace Drush\Queue;

abstract class QueueBase implements QueueInterface {

  /**
   * Keep track of queue definitions.
   *
   * @var array
   */
  protected static $queues;

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
   * {@inheritdoc}
   */
  public function getInfo($name) {
    $queues = $this->getQueues();
    if (!isset($queues[$name])) {
      throw new QueueException(dt('Could not find the !name queue.', array('!name' => $name)));
    }
    return $queues[$name];
  }

}
