<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Tail watchdog messages.',
    aliases: ['wd-tail', 'wt', 'watchdog-tail'],
    usages: [
        'watchdog:tail "cron run successful"',
        'watchdog:tail --severity=Notice',
        'watchdog:tail --severity-min=Warning',
        'watchdog:tail --type=php',
    ],
)]
final class WatchdogTailCommand
{
    use AutowireTrait;
    use WatchdogTrait;

    public const string NAME = 'watchdog:tail';

    public function __construct(
        protected Connection $connection,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('A substring to look search in error messages.')]
        string $substring = '',
        #[Option('Restrict to messages of a given severity level (numeric or string).', suggestedValues: [self::class, 'suggestSeverities'])]
        ?string $severity = null,
        #[Option('Restrict to messages of a given severity level and higher.', suggestedValues: [self::class, 'suggestSeverities'])]
        ?string $severityMin = null,
        #[Option('Restrict to messages of a given type.', suggestedValues: [self::class, 'suggestTypes'])]
        ?string $type = null,
        #[Option('Return extended information about each message.')]
        bool $extended = false,
    ): int {
        $this->requireDblog();

        $where = $this->where($type, $severity, $substring, 'AND', $severityMin);
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

            foreach ($query->execute() as $result) {
                if ($result->wid > $last_seen_wid) {
                    $last_seen_wid = (int) $result->wid;
                }
                $row = $this->formatResult($result, $extended);
                $output->writeln("{$row['wid']}\t{$row['date']}\t{$row['type']}\t{$row['severity']}\t{$row['message']}");
            }
            sleep(2);
        }
    }
}
