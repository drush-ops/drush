<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
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
    description: 'Tail watchdog messages.',
    aliases: ['wd-tail', 'wt', 'watchdog-tail'],
)]
#[CLI\ValidateModulesEnabled(modules: ['dblog'])]
#[CLI\Version(version: '10.6')]
final class WatchdogTailCommand extends Command
{
    use AutowireTrait;
    use WatchdogTrait;

    const string NAME = 'watchdog:tail';

    public function __construct(
        protected Connection $connection,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('substring', InputArgument::OPTIONAL, 'A substring to look search in error messages.', '')
            ->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given severity level (numeric or string).')
            ->addOption('severity-min', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given severity level and higher.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Restrict to messages of a given type.')
            ->addOption('extended', null, InputOption::VALUE_NONE, 'Return extended information about each message.')
            ->addUsage('watchdog:tail "cron run successful"')
            ->addUsage('watchdog:tail --severity=Notice')
            ->addUsage('watchdog:tail --severity-min=Warning')
            ->addUsage('watchdog:tail --type=php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $substring = $input->getArgument('substring');
        $severity = $input->getOption('severity');
        $severity_min = $input->getOption('severity-min');
        $type = $input->getOption('type');
        $extended = $input->getOption('extended');

        $where = $this->where($type, $severity, $substring, 'AND', $severity_min);
        if (empty($where['where'])) {
            $where = [
              'where' => 'wid > :wid',
              'args' => [],
            ];
        } else {
            $where['where'] .= " AND wid > :wid";
        }

        $last_seen_wid = 0;
        while (true) {
            $where['args'][':wid'] = $last_seen_wid;
            $query = $this->connection->select('watchdog', 'w')
                ->fields('w')
                ->orderBy('wid', 'DESC');
            if ($last_seen_wid === 0) {
                $query->range(0, 10);
            }
            $query->where($where['where'], $where['args']);

            $rsc = $query->execute();
            while ($result = $rsc->fetchObject()) {
                if ($result->wid > $last_seen_wid) {
                    $last_seen_wid = $result->wid;
                }
                $row = $this->formatResult($result, $extended);
                $msg = "$row->wid\t$row->date\t$row->type\t$row->severity\t$row->message";
                $output->writeln($msg);
            }
            sleep(2);
        }
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
