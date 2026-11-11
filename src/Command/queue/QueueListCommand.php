<?php

declare(strict_types=1);

namespace Drush\Command\queue;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Returns a list of all defined queues.',
    aliases: ['queue-list'],
    usages: ['queue:list --format=json'],
)]
class QueueListCommand
{
    use AutowireTrait;
    use QueueTrait;

    const string NAME = 'queue:list';

    public function __construct(
        protected QueueWorkerManagerInterface $workerManager,
        protected QueueFactory $queueService,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $rows = [];
        foreach (array_keys($this->getQueues()) as $name) {
            $queue = $this->queueService->get($name);
            $rows[$name] = [
                'queue' => $name,
                'items' => $queue->numberOfItems(),
                'class' => $queue::class,
            ];
        }

        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => Yaml::encode($rows),
            default => $this->renderTable($rows),
        };
        $output->writeln($result, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    protected function renderTable(array $rows): string
    {
        $buffer = new BufferedOutput();
        $table = new Table($buffer);
        $table
            ->setHeaders([(string) $this->t('Queue'), (string) $this->t('Items'), (string) $this->t('Class')])
            ->setRows($rows)
            ->render();
        return $buffer->fetch();
    }
}
