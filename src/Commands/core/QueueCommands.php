<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

final class QueueCommands extends DrushCommands
{
    use AutowireTrait;

    const RUN = 'queue:run';
    const RUN_ALL = 'queue:run-all';
    const LIST = 'queue:list';
    const DELETE = 'queue:delete';

    // Keep track of queue definitions.
    protected static array $queues;

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService
    ) {
        parent::__construct();
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
     * Run a specific queue by name.
     */
    #[CLI\Command(name: self::RUN, aliases: ['queue-run'])]
    #[CLI\Argument(name: 'name', description: 'The name of the queue to run.')]
    #[CLI\Option(name: 'time-limit', description: 'The maximum number of seconds allowed to run the queue.')]
    #[CLI\Option(name: 'items-limit', description: 'The maximum number of items allowed to run the queue.')]
    #[CLI\Option(name: 'lease-time', description: 'The maximum number of seconds that an item remains claimed.')]
    #[CLI\Option(name: 'daemon', description: 'Keep the command running indefinitely, or until one of the chosen limits has been reached.')]
    #[CLI\ValidateQueueName(argumentName: 'name')]
    #[CLI\Complete(method_name_or_callable: 'queueComplete')]
    public function run(string $name, $options = ['time-limit' => self::REQ, 'items-limit' => self::REQ, 'lease-time' => self::REQ, 'daemon' => false]): void
    {
        $worker = $this->getWorkerManager()->createInstance($name);
        $info = $this->getWorkerManager()->getDefinition($name);
        $queue = $this->getQueue($name);

        $time_limit = (int) $options['time-limit'];
        $start = microtime(true);
        $end = time() + $time_limit;
        $time_remaining = $time_limit;
        $lease_time = $options['lease-time'] ?? $info['cron']['time'] ?? 30;
        $items_count = 0;

        if ($queue instanceof QueueGarbageCollectionInterface) {
            $queue->garbageCollection();
        }

        do {
            while (!$this->hasReachedLimit($options, $items_count, $time_remaining) && ($item = $queue->claimItem($lease_time))) {
                if ($this->processItem($queue, $worker, $name, $item)) {
                    $items_count++;
                }
                $time_remaining = $end - time();
            }
            if ($options['daemon']) {
                sleep(1);
            }
        } while ($options['daemon'] && !$this->hasReachedLimit($options, $items_count, $time_remaining));

        $elapsed = microtime(true) - $start;
        $this->logger()->success(dt('Processed @count items from the @name queue in @elapsed sec.', ['@count' => $items_count, '@name' => $name, '@elapsed' => round($elapsed, 2)]));
    }

    /**
     * Run all available queues.
     */
    #[CLI\Command(name: self::RUN_ALL, aliases: ['queue-run-all'])]
    #[CLI\Option(name: 'time-limit', description: 'The maximum number of seconds allowed to run the queue.')]
    #[CLI\Option(name: 'items-limit', description: 'The maximum number of items allowed to run the queue.')]
    #[CLI\Option(name: 'lease-time', description: 'The maximum number of seconds that an item remains claimed.')]
    #[CLI\Option(name: 'daemon', description: 'Keep the command running indefinitely, or until one of the chosen limits has been reached.')]
    public function runAll($options = ['time-limit' => self::REQ, 'items-limit' => self::REQ, 'lease-time' => self::REQ, 'daemon' => false]): void
    {
        $time_limit = (int) $options['time-limit'];
        $start = microtime(true);
        $end = time() + $time_limit;
        $time_remaining = $time_limit;
        $items_count = 0;
        $queues = $this->getQueues();

        do {
            foreach ($queues as $name => $info) {
                $queue = $this->getQueue($name);
                $worker = $this->getWorkerManager()->createInstance($name);
                $lease_time = $options['lease-time'] ?? $info['cron']['time'] ?? 30;
                $queue_starting = true;
                $queue_start = microtime(true);
                $queue_items_count = 0;

                if ($queue instanceof QueueGarbageCollectionInterface) {
                    $queue->garbageCollection();
                }

                while (!$this->hasReachedLimit($options, $items_count, $time_remaining) && $item = $queue->claimItem($lease_time)) {
                    if ($queue_starting) {
                        $this->logger()->notice('Processing queue ' . $name);
                    }
                    if ($this->processItem($queue, $worker, $name, $item)) {
                        $queue_items_count++;
                    }
                    $time_remaining = $end - time();
                    $queue_starting = false;
                }

                if ($queue_items_count > 0) {
                    $items_count += $queue_items_count;
                    $elapsed = microtime(true) - $queue_start;
                    $this->logger()->success(dt('Processed @count items from the @name queue in @elapsed sec.', ['@count' => $queue_items_count, '@name' => $name, '@elapsed' => round($elapsed, 2)]));
                }
            }
            if ($options['daemon']) {
                sleep(1);
            }
        } while ($options['daemon'] && !$this->hasReachedLimit($options, $items_count, $time_remaining));

        $elapsed = microtime(true) - $start;
        $this->logger()->success(dt('Processed @count items in @elapsed sec.', ['@count' => $items_count, '@elapsed' => round($elapsed, 2)]));
    }

    protected function hasReachedLimit(array $options, int $items_count, int $time_remaining): bool
    {
        $time_limit = (int) $options['time-limit'];
        $items_limit = (int) $options['items-limit'];

        return ($time_limit && $time_remaining <= 0)
            || ($items_limit && $items_count >= $items_limit);
    }

    /**
     * Process an item from the queue.
     *
     * @return bool
     *   TRUE if the item was processed, FALSE otherwise.
     */
    protected function processItem(QueueInterface $queue, QueueWorkerInterface $worker, string $name, object $item): bool
    {
        try {
            // @phpstan-ignore-next-line
            $this->logger()->info(dt('Processing item @id from @name queue.', ['@name' => $name, '@id' => $item->item_id ?? $item->qid]));
            // @phpstan-ignore-next-line
            $worker->processItem($item->data);
            $queue->deleteItem($item);
            return true;
        } catch (RequeueException) {
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

        return false;
    }

    /**
     * Returns a list of all defined queues.
     */
    #[CLI\Command(name: self::LIST, aliases: ['queue-list'])]
    #[CLI\FieldLabels(labels: ['queue' => 'Queue', 'items' => 'Items', 'class' => 'Class'])]
    #[CLI\FilterDefaultField(field: 'queue')]
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
     */
    #[CLI\Command(name: self::DELETE, aliases: ['queue-delete'])]
    #[CLI\Argument(name: 'name', description: 'The name of the queue to delete.')]
    #[CLI\ValidateQueueName(argumentName: 'name')]
    #[CLI\Complete(method_name_or_callable: 'queueComplete')]
    public function delete($name): void
    {
        $queue = $this->getQueue($name);
        $queue->deleteQueue();
        $this->logger()->success(dt('All items in @name queue deleted.', ['@name' => $name]));
    }

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

    public function getQueue($name): QueueInterface
    {
        return $this->getQueueService()->get($name);
    }

    public function queueComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys(self::getQueues()));
        }
    }
}
