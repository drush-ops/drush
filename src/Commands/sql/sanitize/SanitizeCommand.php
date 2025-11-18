<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Exceptions\UserAbortException;
use Drush\Style\DrushStyle;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Sanitize the database by removing or obfuscating user data.',
    aliases: ['sqlsan','sql-sanitize'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Events])]
final class SanitizeCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'sql:sanitize';
    const string CONFIRMS = 'sql-sanitize-confirms';

    public function __construct(protected EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $help = <<<HELP
Commandfiles may add custom operations by implementing a Listener that subscribes to two events:
  - `\Drush\Events\SanitizeConfirmsEvent`. Display a summary to the user before confirmation.
  - `\Symfony\Component\Console\Event\ConsoleTerminateEvent`. Run queries or call APIs to perform sanitizing
Several working Listeners may be found at https://github.com/drush-ops/drush/tree/14.x/src/Drush/Listeners/sanitize
HELP;
        $this
            ->addUsage('sql:sanitize --sanitize-password=no')
            ->addUsage('sql:sanitize --allowlist-fields=field_biography,field_phone_number')
            ->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // To present only one prompt, collect all confirmations first.
        // These are the "new" event listeners.
        $event = new SanitizeConfirmsEvent($input);
        $this->eventDispatcher->dispatch($event);
        $messages = $event->getMessages();

        $io = new DrushStyle($input, $output);
        if ($messages) {
            $output->writeln(dt('The following operations will be performed:'));
            $io->listing($messages);
        }
        if (!$io->confirm(dt('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }

        // Sanitize listeners do their work during the built-in console.terminate event.

        return Command::SUCCESS;
    }
}
