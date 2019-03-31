<?php
namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Commands\DrushCommands;

class QueueCommands extends DrushCommands
{

    /**
     * @var \Drupal\Core\Queue\QueueWorkerManager
     */
    protected $workerManager;

    protected $queueService;

    public function __construct(QueueWorkerManagerInterface $workerManager, QueueFactory $queueService)
    {
        $this->workerManager = $workerManager;
        $this->queueService = $queueService;
    }

    /**
     * @return \Drupal\Core\Queue\QueueWorkerManager
     */
    public function getWorkerManager()
    {
        return $this->workerManager;
    }

    /**
     * @return \Drupal\Core\Queue\QueueFactory
     */
    public function getQueueService()
    {
        return $this->queueService;
    }

    /**
     * Keep track of queue definitions.
     *
     * @var array
     */
    protected static $queues;

    /**
     * Run a specific queue by name.
     *
     * @command queue:run
     * @aliases queue-run
     * @param string $name The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.
     * @validate-queue name
     * @option time-limit The maximum number of seconds allowed to run the queue
     */
    public function run($name, $options = ['time-limit' => self::REQ])
    {
        $time_limit = (int) $options['time-limit'];
        $start = microtime(true);
        $worker = $this->getWorkerManager()->createInstance($name);
        $end = time() + $time_limit;
        $queue = $this->getQueue($name);
        $count = 0;

        while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
            try {
                $this->logger()->info(dt('Processing item @id from @name queue.', ['@name' => $name, '@id' => $item->item_id]));
                $worker->processItem($item->data);
                $queue->deleteItem($item);
                $count++;
            } catch (RequeueException $e) {
                // The worker requested the task to be immediately requeued.
                $queue->releaseItem($item);
            } catch (SuspendQueueException $e) {
                // If the worker indicates there is a problem with the whole queue,
                // release the item.
                $queue->releaseItem($item);
                throw new \Exception($e->getMessage());
            }
        }
        $elapsed = microtime(true) - $start;
        $this->logger()->success(dt('Processed @count items from the @name queue in @elapsed sec.', ['@count' => $count, '@name' => $name, '@elapsed' => round($elapsed, 2)]));
    }

    /**
     * Returns a list of all defined queues.
     *
     * @command queue:list
     * @aliases queue-list
     * @field-labels
     *   queue: Queue
     *   items: Items
     *   class: Class
     *
     * @filter-default-field queue
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function qList($options = ['format' => 'table'])
    {
        $result = [];
        foreach (array_keys($this->getQueues()) as $name) {
            $q = $this->getQueue($name);
            $result[$name] = [
            'queue' => $name,
            'items' => $q->numberOfItems(),
            'class' => get_class($q),
            ];
        }
        return new RowsOfFields($result);
    }

    /**
     * Delete all items in a specific queue.
     *
     * @command queue:delete
     * @aliases queue-delete
     * @param $name The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.
     * @validate-queue name
     */
    public function delete($name)
    {
        $queue = $this->getQueue($name);
        $queue->deleteQueue();
        $this->logger()->success(dt('All items in @name queue deleted.', ['@name' => $name]));
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
    public function validateQueueName(CommandData $commandData)
    {
        $arg_name = $commandData->annotationData()->get('validate-queue', null);
        $name = $commandData->input()->getArgument($arg_name);
        $all = array_keys(self::getQueues());
        if (!in_array($name, $all)) {
            $msg = dt('Queue not found: !name', ['!name' => $name]);
            return new CommandError($msg);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getQueues()
    {
        if (!isset(static::$queues)) {
            static::$queues = [];
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
    public function getQueue($name)
    {
        return $this->getQueueService()->get($name);
    }
}
