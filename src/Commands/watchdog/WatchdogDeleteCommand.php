<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Drush\Style\DrushStyle;
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
    description: 'Delete watchdog log records.',
    aliases: ['wd-del', 'wd-delete', 'wd', 'watchdog-delete'],
)]
#[CLI\ValidateModulesEnabled(modules: ['dblog'])]
final class WatchdogDeleteCommand extends Command
{
    use AutowireTrait;
    use WatchdogTrait;

    const string NAME = 'watchdog:delete';

    public function __construct(
        protected Connection $connection,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('substring', InputArgument::OPTIONAL, 'Delete all log records with this text in the messages.', '')
            ->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Delete messages of a given severity level.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Delete messages of a given type.')
            ->addUsage('watchdog:delete all')
            ->addUsage('watchdog:delete 64')
            ->addUsage('watchdog:delete "cron run succesful"')
            ->addUsage('watchdog:delete --severity=Notice')
            ->addUsage('watchdog:delete --type=cron');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $substring = $input->getArgument('substring');
        $severity = $input->getOption('severity');
        $type = $input->getOption('type');

        if ($substring == 'all') {
            $output->writeln('All watchdog messages will be deleted.');
            if (!$io->confirm('Do you really want to continue?')) {
                throw new UserAbortException();
            }
            $ret = $this->connection->truncate('watchdog')->execute();
            $io->success('All watchdog messages have been deleted.');
        } elseif (is_numeric($substring)) {
            $output->writeln(sprintf('Watchdog message #%s will be deleted.', $substring));
            if (!$io->confirm('Do you want to continue?')) {
                throw new UserAbortException();
            }
            $affected_rows = $this->connection->delete('watchdog')->condition('wid', $substring)->execute();
            if ($affected_rows == 1) {
                $io->success(sprintf('Watchdog message #%s has been deleted.', $substring));
            } else {
                throw new \Exception(sprintf('Watchdog message #%s does not exist.', $substring));
            }
        } else {
            if ((empty($substring)) && (!isset($type)) && (!isset($severity))) {
                throw new \Exception('No options provided.');
            }
            $where = $this->where($type, $severity, $substring, 'OR');
            $output->writeln(sprintf('All messages with %s will be deleted.', preg_replace("/message LIKE %$substring%/", "message body containing '$substring'", strtr($where['where'], $where['args']))));
            if (!$io->confirm('Do you want to continue?')) {
                throw new UserAbortException();
            }
            $affected_rows = $this->connection->delete('watchdog')
                ->where($where['where'], $where['args'])
                ->execute();
            $io->success(sprintf('%s watchdog messages have been deleted.', $affected_rows));
        }

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('severity')) {
            $suggestions->suggestValues(array_keys(\Drupal\Core\Logger\RfcLogLevel::getLevels()));
        }
        if ($input->mustSuggestOptionValuesFor('type')) {
            $suggestions->suggestValues(self::messageTypes());
        }
    }
}
