<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Confirms only. The sanitizing is done by event subscribers, which also
 * contribute this command's options.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Sanitize the database by removing or obfuscating user data.',
    aliases: ['sqlsan', 'sql-sanitize'],
    usages: [
        'sql:sanitize --sanitize-password=no',
        'sql:sanitize --allowlist-fields=field_biography,field_phone_number',
    ],
    help: <<<'HELP'
Modules may add their own operations with an event subscriber (a service tagged <info>event_subscriber</info>) that listens to:
  - <info>Drush\Event\SanitizeConfirmsEvent</info>: add a line to the summary shown before confirmation.
  - <info>ConsoleEvents::TERMINATE</info>: run queries or call APIs to sanitize, when the command is sql:sanitize and its exit code is 0.
  - <info>ConsoleEvents::COMMAND</info>: optionally, add options to this command.
See https://www.drush.org/latest/subscribers/. Working subscribers are at https://github.com/drush-ops/drush/tree/15.x/src/EventSubscriber/sanitize
HELP,
)]
final class SanitizeCommand
{
    use StringTranslationTrait;

    public const string NAME = 'sql:sanitize';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Collect every operation first so there is a single prompt.
        $event = new SanitizeConfirmsEvent($input);
        $this->eventDispatcher->dispatch($event);
        if ($messages = $event->getMessages()) {
            $io->writeln((string) $this->t('The following operations will be performed:'));
            $io->listing($messages);
        }
        if (!$io->confirm((string) $this->t('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }

        // Subscribers do their work on ConsoleEvents::TERMINATE.
        return Command::SUCCESS;
    }
}
