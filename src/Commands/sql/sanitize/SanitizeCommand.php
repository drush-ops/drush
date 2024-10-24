<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\core\DocsCommands;
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
    aliases: ['sqlsan','sql-sanitize']
)]
// @todo Deal with topics on classes.
#[CLI\Topics(topics: [DocsCommands::HOOKS])]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
final class SanitizeCommand extends Command implements CustomEventAwareInterface
{
    use AutowireTrait;
    use CustomEventAwareTrait;

    const NAME = 'sql:sanitize';

    public function __construct(protected EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setDescription('Sanitize the database by removing or obfuscating user data.')
            ->addUsage('drush sql:sanitize --sanitize-password=no')
            ->addUsage('drush sql:sanitize --allowlist-fields=field_biography,field_phone_number');
    }

    /**
     * Commandfiles may add custom operations by implementing a Listener that subscribes to two events:
     *
     *     - `\Drush\Events\SanitizeConfirmsEvent`. Display summary to user before confirmation.
     *     - `\Symfony\Component\Console\Event\ConsoleTerminateEvent`. Run queries or call APIs to perform sanitizing
     *
     * Several working Listeners may be found at https://github.com/drush-ops/drush/tree/13.x/src/Drush/Listeners/sanitize
     */

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        /**
         * In order to present only one prompt, collect all confirmations up front.
         */
        $event = new SanitizeConfirmsEvent($input);
        $this->eventDispatcher->dispatch($event, SanitizeConfirmsEvent::class);
        $messages = $event->getMessages();

        // Also collect from legacy commandfiles.
        $handlers = $this->getCustomEventHandlers(SanitizeCommands::CONFIRMS);
        foreach ($handlers as $handler) {
            $handler($messages, $input);
        }
        // @phpstan-ignore if.alwaysFalse
        if ($messages) {
            $output->writeln(dt('The following operations will be performed:'));
            $io->listing($messages);
        }
        if (!$io->confirm(dt('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }
        // All sanitize operations happen during the built-in console.terminate event.

        return self::SUCCESS;
    }
}
