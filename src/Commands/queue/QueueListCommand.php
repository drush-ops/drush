<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Returns a list of all defined queues.',
    aliases: ['queue-list']
)]
final class QueueListCommand extends Command
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:list';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table or json.', 'table', ['table', 'json']);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach (array_keys($this->getQueues()) as $name) {
            $queue = $this->queueService->get($name);
            $rows[$name] = [
                'queue' => $name,
                'items' => $queue->numberOfItems(),
                'class' => $queue::class,
            ];
        }

        $format = $input->getOption('format');
        if ($format === 'json') {
            $output->writeln(json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
            return self::SUCCESS;
        }
        if ($format !== 'table') {
            throw new \InvalidArgumentException(sprintf('Unknown format: %s. Use table or json.', $format));
        }

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Items', 'Class']);
        $table->setRows(array_map(array_values(...), $rows));
        $table->render();
        return self::SUCCESS;
    }
}
