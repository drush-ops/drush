<?php

declare(strict_types=1);

namespace Drush\Commands\queue;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Returns a list of all defined queues.',
    aliases: ['queue-list']
)]
#[CLI\FieldLabels(labels: ['queue' => 'Queue', 'items' => 'Items', 'class' => 'Class'])]
#[CLI\FilterDefaultField(field: 'queue')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class QueueListCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use QueueTrait;

    const NAME = 'queue:list';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $result = [];
        foreach (array_keys($this->getQueues()) as $name) {
            $q = $this->queueService->get($name);
            $result[$name] = [
                'queue' => $name,
                'items' => $q->numberOfItems(),
                'class' => get_class($q),
            ];
        }
        return new RowsOfFields($result);
    }
}
