<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Event\SanitizeConfirmsEvent;
use Drush\Exceptions\UserAbortException;
use JetBrains\PhpStorm\Deprecated;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
final class SanitizeCommands extends DrushCommands implements CustomEventAwareInterface
{
    use AutowireTrait;
    use CustomEventAwareTrait;

    public function __construct(protected EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
    }

    #[Deprecated('This constant will soon move to a new class. Use the string value instead.')]
    const SANITIZE = 'sql:sanitize';
    #[Deprecated('This constant will soon move to a new class. Use the string value instead.')]
    const CONFIRMS = 'sql-sanitize-confirms';

    /**
     * Sanitize the database by removing or obfuscating user data.
     *
     *  Commandfiles may add custom operations by implementing a Listener that subscribes to two events:
     *
     *      - `\Drush\Events\SanitizeConfirmsEvent`. Display a summary to the user before confirmation.
     *      - `\Symfony\Component\Console\Event\ConsoleTerminateEvent`. Run queries or call APIs to perform sanitizing
     *
     * [Deprecated] Commandfiles may add custom operations by implementing:
     *
     *     - `#[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]`. Display summary to user before confirmation.
     *     - `#[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: SanitizeCommands::SANITIZE)]`. Run queries or call APIs to perform sanitizing
     *
     * Several working commandfiles may be found at https://github.com/drush-ops/drush/tree/13.x/src/Commands/sql/sanitize
     */
    #[CLI\Command(name: self::SANITIZE, aliases: ['sqlsan','sql-sanitize'])]
    #[CLI\Usage(name: 'drush sql:sanitize --sanitize-password=no', description: 'Sanitize database without modifying any passwords.')]
    #[CLI\Usage(name: 'drush sql:sanitize --allowlist-fields=field_biography,field_phone_number', description: 'Sanitizes database but exempts two user fields from modification.')]
    #[CLI\Topics(topics: [DocsCommands::HOOKS])]
    public function sanitize(): void
    {
        // To present only one prompt, collect all confirmations first.
        // These are the "new" event listeners.
        $event = new SanitizeConfirmsEvent($this->input());
        $this->eventDispatcher->dispatch($event);
        $messages = $event->getMessages();

        // These are the "old" custom events.
        $handlers = $this->getCustomEventHandlers(self::CONFIRMS);
        foreach ($handlers as $handler) {
            $handler($messages, $this->input());
            $stringCallable = (is_string($handler[0]) ? $handler[0] : get_class($handler[0])) . '::' . $handler[1];
            $this->logger()->notice('The {handler} sanitize plugin is using a deprecated API. See {url}', ['handler' => $stringCallable, 'url' => 'https://www.drush.org/14.x/events/']);
        }

        if ($messages) {
            $this->output()->writeln(dt('The following operations will be performed:'));
            $this->io()->listing($messages);
        }
        if (!$this->io()->confirm(dt('Do you want to sanitize the current database?'))) {
            throw new UserAbortException();
        }

        // All sanitize operations defined in post-command hooks, including Drush
        // core sanitize routines. See \Drush\Commands\sql\sanitize\SanitizePluginInterface.
    }
}
