<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
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
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class QueueCommands extends DrushCommands
{
    #[Deprecated('Use CLI/ValidateQueueName Attribute instead')]
    const VALIDATE_QUEUE = 'validate-queue';
    const RUN = 'queue:run';
    const LIST = 'queue:list';
    const DELETE = 'queue:delete';

    /**
     * @var QueueWorkerManagerInterface
     */
    protected QueueWorkerManagerInterface $workerManager;

    protected $queueService;

    public function __construct(QueueWorkerManagerInterface $workerManager, QueueFactory $queueService)
    {
        $this->workerManager = $workerManager;
        $this->queueService = $queueService;
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('plugin.manager.queue_worker'),
            $container->get('queue')
        );

        return $commandHandler;
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
     */
    #[CLI\Command(name: self::RUN, aliases: ['queue-run'])]
    #[CLI\Argument(name: 'name', description: 'The name of the queue to run, as defined in QueueWorker annotation class.')]
    #[CLI\Option(name: 'time-limit', description: 'The maximum number of seconds allowed to run the queue.')]
    #[CLI\Option(name: 'items-limit', description: 'The maximum number of items allowed to run the queue.')]
    #[CLI\Option(name: 'lease-time', description: 'The maximum number of seconds that an item remains claimed.')]
    #[CLI\ValidateQueueName(argumentName: 'name')]
    #[CLI\Complete(method_name_or_callable: 'queueComplete')]
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

        if ($queue instanceof QueueGarbageCollectionInterface) {
            $queue->garbageCollection();
        }

        while ((!$time_limit || $remaining > 0) && (!$items_limit || $count < $items_limit) && ($item = $queue->claimItem($lease_time))) {
            try {
                $this->logger()->info(dt('Processing item @id from @name queue.', ['@name' => $name, '@id' => $item->item_id ?? $item->qid]));
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
    #[CLI\Argument(name: 'name', description: 'The name of the queue to delete, as defined in either hook_queue_info or hook_cron_queue_info.')]
    #[CLI\ValidateQueueName(argumentName: 'name')]
    #[CLI\Complete(method_name_or_callable: 'queueComplete')]
    public function delete($name): void
    {
        $queue = $this->getQueue($name);
        $queue->deleteQueue();
        $this->logger()->success(dt('All items in @name queue deleted.', ['@name' => $name]));
    }

    /**
     * Validate that a queue exists.
     */
    #[Deprecated('Use CLI/ValidateQueueName Attribute instead')]
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: self::VALIDATE_QUEUE)]
    public function validateQueueName(CommandData $commandData)
    {
        $arg_name = $commandData->annotationData()->get(self::VALIDATE_QUEUE, null);
        $name = $commandData->input()->getArgument($arg_name);
        if (!array_key_exists($name, self::getQueues())) {
            $msg = dt('Queue not found: !name', ['!name' => $name]);
            return new CommandError($msg);
        }
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
