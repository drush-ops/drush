<?php

declare(strict_types=1);

namespace Drush\Commands\watchdog;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
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
    description: 'Interactively filter the watchdog message listing.',
    aliases: ['wd-list', 'watchdog-list'],
    usages: ['watchdog:list'],
)]
final class WatchdogListCommand
{
    use AutowireTrait;
    use WatchdogTrait;

    public const string NAME = 'watchdog:list';

    public function __construct(
        protected Connection $connection,
        protected ModuleHandlerInterface $moduleHandler,
        protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    }

    /**
     * Offers the message types and severity levels when no filter was given.
     */
    #[Interact]
    public function askForFilter(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('type') !== null || $input->getOption('severity') !== null) {
            return;
        }
        $this->requireDblog();
        $types = $this->messageTypes();
        $choice = (new SymfonyStyle($input, $output))->choice(
            (string) $this->t('Select a message type or severity level'),
            array_merge($types, array_values($this->severityLabels())),
        );
        if (in_array($choice, $types, true)) {
            $input->setOption('type', $choice);
        } else {
            $input->setOption('severity', $choice);
        }
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('A substring to look search in error messages.')]
        string $substring = '',
        #[Option('The number of messages to show.')]
        int $count = 10,
        #[Option('Restrict to messages of a given severity level (numeric or string).', suggestedValues: [self::class, 'suggestSeverities'])]
        ?string $severity = null,
        #[Option('Restrict to messages of a given severity level and higher.', suggestedValues: [self::class, 'suggestSeverities'])]
        ?string $severityMin = null,
        #[Option('Restrict to messages of a given type.', suggestedValues: [self::class, 'suggestTypes'])]
        ?string $type = null,
        #[Option('Return extended information about each message.')]
        bool $extended = false,
        #[Option('A comma delimited list of fields to show. Use * for all. Defaults to wid,date,type,severity,message for the table and to all fields for json and yaml.')]
        ?string $fields = null,
        #[Option('An alternative output format such as json or yaml. Defaults to table.')]
        ?OutputFormat $format = null,
    ): int {
        $this->requireDblog();

        $records = $this->fetchRecords($this->where($type, $severity, $substring, 'AND', $severityMin), $count, $extended);
        if ($records === []) {
            (new SymfonyStyle($input, $output))->getErrorStyle()->note((string) $this->t('No log messages available.'));
            if ($format === null) {
                return Command::SUCCESS;
            }
        }
        $output->writeln($this->renderRecords($records, $fields, $format), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
