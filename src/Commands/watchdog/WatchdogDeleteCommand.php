<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Delete watchdog log records.',
    aliases: ['wd-del', 'wd-delete', 'wd', 'watchdog-delete'],
    usages: [
        'watchdog:delete all',
        'watchdog:delete 64',
        'watchdog:delete "cron run succesful"',
        'watchdog:delete --severity=Notice',
        'watchdog:delete --type=cron',
    ],
)]
final class WatchdogDeleteCommand
{
    use AutowireTrait;
    use WatchdogTrait;

    public const string NAME = 'watchdog:delete';

    public function __construct(
        protected Connection $connection,
        protected ModuleHandlerInterface $moduleHandler,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('Delete all log records with this text in the messages, the record with this ID, or every record when "all".')]
        string $substring = '',
        #[Option('Delete messages of a given severity level.', suggestedValues: [self::class, 'suggestSeverities'])]
        ?string $severity = null,
        #[Option('Delete messages of a given type.', suggestedValues: [self::class, 'suggestTypes'])]
        ?string $type = null,
    ): int {
        $this->requireDblog();
        $io = new SymfonyStyle($input, $output);

        if ($substring === 'all') {
            $output->writeln((string) $this->t('All watchdog messages will be deleted.'));
            if (!$io->confirm((string) $this->t('Do you really want to continue?'))) {
                throw new UserAbortException();
            }
            $this->connection->truncate('watchdog')->execute();
            $io->success((string) $this->t('All watchdog messages have been deleted.'));
            return Command::SUCCESS;
        }

        if (is_numeric($substring)) {
            $output->writeln((string) $this->t('Watchdog message #@id will be deleted.', ['@id' => $substring]));
            if (!$io->confirm((string) $this->t('Do you want to continue?'))) {
                throw new UserAbortException();
            }
            $affected_rows = $this->connection->delete('watchdog')->condition('wid', (int) $substring)->execute();
            if ($affected_rows != 1) {
                throw new \InvalidArgumentException((string) $this->t('Watchdog message #@id does not exist.', ['@id' => $substring]));
            }
            $io->success((string) $this->t('Watchdog message #@id has been deleted.', ['@id' => $substring]));
            return Command::SUCCESS;
        }

        if ($substring === '' && $type === null && $severity === null) {
            throw new \InvalidArgumentException((string) $this->t('No options provided.'));
        }
        $where = $this->where($type, $severity, $substring, 'OR');
        $criteria = [];
        if ($type !== null) {
            $criteria[] = (string) $this->t('type @type', ['@type' => $type]);
        }
        if ($severity !== null) {
            $criteria[] = (string) $this->t('severity @severity', ['@severity' => $severity]);
        }
        if ($substring !== '') {
            $criteria[] = (string) $this->t('message body containing @substring', ['@substring' => $substring]);
        }
        $output->writeln((string) $this->t('All messages with @criteria will be deleted.', ['@criteria' => implode(' OR ', $criteria)]));
        if (!$io->confirm((string) $this->t('Do you want to continue?'))) {
            throw new UserAbortException();
        }
        $affected_rows = $this->connection->delete('watchdog')
            ->where($where['where'], $where['args'])
            ->execute();
        $io->success((string) $this->formatPlural($affected_rows, '1 watchdog message has been deleted.', '@count watchdog messages have been deleted.'));

        return Command::SUCCESS;
    }
}
