<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Commands\DrushCommands;


class QueueCommands extends DrushCommands {

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $workerManager;

  /**
   * Keep track of queue definitions.
   *
   * @var array
   */
  protected static $queues;

  /**
   * Run a specific queue by name.
   *
   * @command queue-run
   * @param string $name The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.
   * @validate-queue name
   * @option time-limit The maximum number of seconds allowed to run the queue
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function run($name, $options = ['time-limit' => NULL]) {
    $time_limit = (int) $options['time-limit'];
    $start = microtime(TRUE);
    $worker = $this->getWorkerManager()->createInstance($name);
    $end = time() + $time_limit;
    $queue = $this->getQueue($name);
    $count = 0;

    while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
      try {
        $this->logger()->info(dt('Processing item @id from @name queue.', array('@name' => $name, '@id' => $item->item_id)));
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);
        throw new \Exception($e->getMessage());
      }
    }
    $elapsed = microtime(TRUE) - $start;
    $this->logger()->success(dt('Processed @count items from the @name queue in @elapsed sec.', array('@count' => $count, '@name' => $name, '@elapsed' => round($elapsed, 2))));
  }

  /**
   * Returns a list of all defined queues.
   *
   * @command queue-list
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @field-labels
   *   queue: Queue
   *   items: Items
   *   class: Class
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function qList($options = ['format' => 'table']) {
    $result = array();
    foreach (array_keys($this->getQueues()) as $name) {
      $q = $this->getQueue($name);
      $result[$name] = array(
        'queue' => $name,
        'items' => $q->numberOfItems(),
        'class' => get_class($q),
      );
    }
    return new RowsOfFields($result);
  }

  /**
   * Delete all items in a specific queue.
   *
   * @command queue-delete
   * @param $name The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.
   * @validate-queue name
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function delete($name) {
    $queue = $this->getQueue($name);
    $queue->deleteQueue();
    $this->logger()->success(dt('All items in @name queue deleted.', array('@name' => $name)));
  }

  /**
   * Validate that queue permission exists.
   *
   * Annotation value should be the name of the argument/option containing the name.
   *
   * @hook validate @validate-queue
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   * @return \Consolidation\AnnotatedCommand\CommandError|null
   */
  public function validateQueueName(CommandData $commandData) {
    $arg_name = $commandData->annotationData()->get('validate-queue', NULL);
    $name = $commandData->input()->getArgument($arg_name);
    $all = array_keys(self::getQueues());
    if (!in_array($name, $all)) {
      $msg = dt('Queue not found: !name', ['!name' => $name]);
      return new CommandError($msg);
    }
  }

  /**
   * @return \Drupal\Core\Queue\QueueWorkerManager
   */
  public function getWorkerManager() {
    if (!isset($this->workerManager)) {
      $this->workerManager = \Drupal::service('plugin.manager.queue_worker');
    }
    return $this->workerManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueues() {
    if (!isset(static::$queues)) {
      static::$queues = array();
      foreach ($this->getWorkerManager()->getDefinitions() as $name => $info) {
        static::$queues[$name] = $info;
      }
    }
    return static::$queues;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Queue\QueueInterface
   */
  public function getQueue($name) {
    return \Drupal::queue($name);
  }
}