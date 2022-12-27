<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Queue\QueueInterface;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Commands\DrushCommands;

class QueueCommands extends DrushCommands
{
    /**
     * @var QueueWorkerManagerInterface
     */
    protected $workerManager;

    protected $queueService;

    public function __construct(QueueWorkerManagerInterface $workerManager, QueueFactory $queueService)
    {
        $this->workerManager = $workerManager;
        $this->queueService = $queueService;
    }

    public function getWorkerManager(): QueueWorkerManagerInterface
    {
        return $this->workerManager;
    }

    public function getQueueService(): QueueFactory
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
     * @option time-limit The maximum number of seconds allowed to run the queue.
     * @option items-limit The maximum number of items allowed to run the queue.
     * @option lease-time The maximum number of seconds that an item remains claimed.
     */
    public function run(string $name, $options = ['time-limit' => self::REQ, 'items-limit' => self::REQ, 'lease-time' => self::REQ]): void
    {
        $time_limit = (int) $options['time-limit'];
        $items_limit = (int) $options['items-limit'];
        $start = microtime(true);
        $worker = $this->getWorkerManager()->createInstance($name);
        $info = $this->getWorkerManager()->getDefinition($name);
        $end = time() + $time_limit;
        $queue = $this->getQueue($name);
        $count = 0;
        $remaining = $time_limit;
        $lease_time = $options['lease-time'] ?? $info['cron']['time'] ?? 30;

        while ((!$time_limit || $remaining > 0) && (!$items_limit || $count < $items_limit) && ($item = $queue->claimItem($lease_time))) {
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
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            } catch (DelayedRequeueException $e) {
                // The worker requested the task not be immediately re-queued.
                // - If the queue doesn't support ::delayItem(), we should leave the
                // item's current expiry time alone.
                // - If the queue does support ::delayItem(), we should allow the
                // queue to update the item's expiry using the requested delay.
                if ($queue instanceof DelayableQueueInterface) {
                    // This queue can handle a custom delay; use the duration provided
                    // by the exception.
                    $queue->delayItem($item, $e->getDelay());
                }
            } catch (\Exception $e) {
                // In case of any other kind of exception, log it and leave the
                // item in the queue to be processed again later.
                $this->logger()->error($e->getMessage());
            }
            $remaining = $end - time();
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
     */
    public function qList($options = ['format' => 'table']): RowsOfFields
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
    public function delete($name): void
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
     * @param CommandData $commandData
     * @return CommandError|null
     */
    public function validateQueueName(CommandData $commandData)
    {
        $arg_name = $commandData->annotationData()->get('validate-queue', null);
        $name = $commandData->input()->getArgument($arg_name);
        if (!array_key_exists($name, self::getQueues())) {
            $msg = dt('Queue not found: !name', ['!name' => $name]);
            return new CommandError($msg);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getQueues(): array
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
     */
    public function getQueue($name): QueueInterface
    {
        return $this->getQueueService()->get($name);
    }
}
