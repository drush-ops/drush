<?php

declare(strict_types=1);

namespace Drush\Command\queue;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Delete all items in a specific queue.',
    aliases: ['queue-delete'],
)]
class QueueDeleteCommand
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:delete';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The name of the queue to delete.', suggestedValues: [self::class, 'suggestQueueNames'])]
        string $name,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $this->validateQueueName($name);
        $this->queueService->get($name)->deleteQueue();
        $io->success((string) $this->t('All items in @name queue deleted.', ['@name' => $name]));
        return Command::SUCCESS;
    }
}
