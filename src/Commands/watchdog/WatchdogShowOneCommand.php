<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Show one log record by ID.',
    aliases: ['wd-one', 'watchdog-show-one'],
)]
#[CLI\ValidateModulesEnabled(modules: ['dblog'])]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'table')]
final class WatchdogShowOneCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use WatchdogTrait;

    const string NAME = 'watchdog:show-one';

    public function __construct(
        protected Connection $connection,
        protected FormatterManager $formatterManager,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Watchdog Id');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $id = $input->getArgument('id');

        $rsc = $this->connection->select('watchdog', 'w')
            ->fields('w')
            ->condition('wid', (int)$id)
            ->range(0, 1)
            ->execute();
        $result = $rsc->fetchObject();

        if (!$result) {
            throw new \Exception(sprintf('Watchdog message #%s not found.', $id));
        }

        $formatted_result = $this->formatResult($result, true);
        return new PropertyList($formatted_result);
    }
}
