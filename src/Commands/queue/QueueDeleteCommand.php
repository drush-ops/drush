<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Delete all items in a specific queue.',
    aliases: ['queue-delete']
)]
#[CLI\ValidateQueueName(argumentName: 'name')]
final class QueueDeleteCommand extends Command
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:delete';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the queue to delete.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $name = $input->getArgument('name');
        $queue = $this->queueService->get($name);
        $queue->deleteQueue();
        $io->success(sprintf('All items in %s queue deleted.', $name));
        return self::SUCCESS;
    }
}
