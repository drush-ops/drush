<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drush\Commands\AutowireTrait;
use stdClass;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Run a specific queue by name.',
    aliases: ['queue-run'],
)]
class QueueRunCommand
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:run';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The name of the queue to run.', suggestedValues: [self::class, 'suggestQueueNames'])]
        string $name,
        #[Option('The maximum number of seconds allowed to run the queue.')]
        int $timeLimit = 0,
        #[Option('The maximum number of items allowed to run the queue.')]
        int $itemsLimit = 0,
        #[Option('The maximum number of seconds that an item remains claimed.')]
        ?int $leaseTime = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->validateQueueName($name);
        $start = microtime(true);
        $worker = $this->workerManager->createInstance($name);
        $info = $this->workerManager->getDefinition($name);
        $end = time() + $timeLimit;
        $queue = $this->queueService->get($name);
        $count = 0;
        $remaining = $timeLimit;
        $leaseTime ??= $info['cron']['time'] ?? 30;

        if ($queue instanceof QueueGarbageCollectionInterface) {
            $queue->garbageCollection();
        }

        while ((!$timeLimit || $remaining > 0) && (!$itemsLimit || $count < $itemsLimit) && ($item = $queue->claimItem($leaseTime))) {
            try {
                assert($item instanceof stdClass);
                $io->note((string) $this->t('Processing item @id from @name queue.', ['@id' => $item->item_id ?? $item->qid, '@name' => $name]));
                $worker->processItem($item->data);
                $queue->deleteItem($item);
                $count++;
            } catch (RequeueException) {
                $queue->releaseItem($item);
            } catch (SuspendQueueException $e) {
                // The worker asked to skip the rest of this queue for this run.
                // Like core's cron runner, release the item and stop; this is
                // not a failure. See https://www.drupal.org/i/2867001.
                $queue->releaseItem($item);
                $io->getErrorStyle()->warning((string) $this->t('Suspended the @name queue: @message', ['@name' => $name, '@message' => $e->getMessage()]));
                break;
            } catch (DelayedRequeueException $e) {
                if ($queue instanceof DelayableQueueInterface) {
                    $queue->delayItem($item, $e->getDelay());
                }
            } catch (\Exception $e) {
                $io->error($e->getMessage());
            }
            $remaining = $end - time();
        }
        $elapsed = microtime(true) - $start;
        $io->success((string) $this->formatPlural(
            $count,
            'Processed 1 item from the @name queue in @elapsed sec.',
            'Processed @count items from the @name queue in @elapsed sec.',
            ['@name' => $name, '@elapsed' => round($elapsed, 2)],
        ));

        return Command::SUCCESS;
    }
}
