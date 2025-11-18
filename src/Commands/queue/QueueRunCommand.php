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
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Run a specific queue by name.',
    aliases: ['queue-run']
)]
#[CLI\ValidateQueueName(argumentName: 'name')]
final class QueueRunCommand extends Command
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:run';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the queue to run.')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'The maximum number of seconds allowed to run the queue.')
            ->addOption('items-limit', null, InputOption::VALUE_REQUIRED, 'The maximum number of items allowed to run the queue.')
            ->addOption('lease-time', null, InputOption::VALUE_REQUIRED, 'The maximum number of seconds that an item remains claimed.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $name = $input->getArgument('name');
        $timeLimit = (int) $input->getOption('time-limit');
        $itemsLimit = (int) $input->getOption('items-limit');
        $start = microtime(true);
        $worker = $this->workerManager->createInstance($name);
        $info = $this->workerManager->getDefinition($name);
        $end = time() + $timeLimit;
        $queue = $this->queueService->get($name);
        $count = 0;
        $remaining = $timeLimit;
        $leaseTime = $input->getOption('lease-time') ?? $info['cron']['time'] ?? 30;

        if ($queue instanceof QueueGarbageCollectionInterface) {
            $queue->garbageCollection();
        }

        while ((!$timeLimit || $remaining > 0) && (!$itemsLimit || $count < $itemsLimit) && ($item = $queue->claimItem($leaseTime))) {
            try {
                assert($item instanceof stdClass);
                $io->note(sprintf('Processing item %s from %s queue.', $name, $item->item_id ?? $item->qid));
                $worker->processItem($item->data);
                $queue->deleteItem($item);
                $count++;
            } catch (RequeueException) {
                $queue->releaseItem($item);
            } catch (SuspendQueueException $e) {
                $queue->releaseItem($item);
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
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
        $io->success(sprintf('Processed %s items from the %s queue in %s sec.', $count, $name, round($elapsed, 2)));

        return self::SUCCESS;
    }
}
