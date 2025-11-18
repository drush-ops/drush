<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Logger\RfcLogLevel;
use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Show watchdog messages.',
    aliases: ['wd-show', 'ws', 'watchdog-show'],
)]
#[CLI\ValidateModulesEnabled(modules: ['dblog'])]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
#[CLI\FieldLabels(labels: [
    'wid' => 'ID',
    'type' => 'Type',
    'message' => 'Message',
    'severity' => 'Severity',
    'location' => 'Location',
    'hostname' => 'Hostname',
    'date' => 'Date',
    'username' => 'Username',
    'uid' => ' Uid',
])]
#[CLI\FilterDefaultField(field: 'message')]
#[CLI\DefaultTableFields(fields: ['wid', 'date', 'type', 'severity', 'message'])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class WatchdogShowCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use WatchdogTrait;

    const string NAME = 'watchdog:show';

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
            ->addArgument('substring', InputArgument::OPTIONAL, 'A substring to look search in error messages.', '')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'The number of messages to show.', 10)
            ->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given severity level (numeric or string).')
            ->addOption('severity-min', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given severity level and higher.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given type.')
            ->addOption('extended', null, InputOption::VALUE_NONE, 'Return extended information about each message.')
            ->addUsage('watchdog:show "cron run successful"')
            ->addUsage('watchdog:show --count=46')
            ->addUsage('watchdog:show --severity=Notice')
            ->addUsage('watchdog:show --severity-min=Warning')
            ->addUsage('watchdog:show --type=php')
            ->addUsage('watchdog:show --filter="type!=locale"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $substring = $input->getArgument('substring');
        $count = (int)$input->getOption('count');
        $severity = $input->getOption('severity');
        $severity_min = $input->getOption('severity-min');
        $type = $input->getOption('type');
        $extended = $input->getOption('extended');

        $where = $this->where($type, $severity, $substring, 'AND', $severity_min);
        $query = $this->connection->select('watchdog', 'w')
            ->range(0, $count)
            ->fields('w')
            ->orderBy('wid', 'DESC');
        if (!empty($where['where'])) {
            $query->where($where['where'], $where['args']);
        }
        $rsc = $query->execute();

        $table = [];
        while ($result = $rsc->fetchObject()) {
            $row = $this->formatResult($result, $extended);
            $table[$row->wid] = (array)$row;
        }

        if ($table === []) {
            $this->logger->notice('No log messages available.');
        }

        return new RowsOfFields($table);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('severity') || $input->mustSuggestOptionValuesFor('severity-min')) {
            $suggestions->suggestValues(array_keys(RfcLogLevel::getLevels()));
        }
        if ($input->mustSuggestOptionValuesFor('type')) {
            $suggestions->suggestValues(self::messageTypes());
        }
    }
}
