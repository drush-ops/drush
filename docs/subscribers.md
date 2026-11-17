# Events and Event Subscribers

!!! tip

    Drush hooks have been removed. Use event subscribers to change the behavior of other commands.

`dr` dispatches the [standard Symfony Console events](https://symfony.com/doc/current/components/console/events.html) through Drupal's `event_dispatcher` service. Any module can subscribe to them, the same way it subscribes to Drupal's own events.

- `Symfony\Component\Console\Event\ConsoleCommandEvent` (`ConsoleEvents::COMMAND`): runs before a command. Validate or adjust input, or call `disableCommand()` to stop it.
- `Symfony\Component\Console\Event\ConsoleErrorEvent` (`ConsoleEvents::ERROR`): runs when a command throws.
- `Symfony\Component\Console\Event\ConsoleTerminateEvent` (`ConsoleEvents::TERMINATE`): runs after a command completes.

## Writing a subscriber

Register the class as a service tagged `event_subscriber` in your module's `*.services.yml`:

```yaml
services:
  example.console_subscriber:
    class: Drupal\example\EventSubscriber\ConsoleSubscriber
    tags:
      - { name: event_subscriber }
```

```php
namespace Drupal\example\EventSubscriber;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConsoleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => 'onCommand'];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()?->getName() === 'sql:drop' && getenv('APP_ENV') === 'prod') {
            $event->getOutput()->writeln('sql:drop is disabled in production.');
            $event->disableCommand();
        }
    }
}
```

## Events dispatched by Drush commands

Commands may dispatch their own events by injecting `Psr\EventDispatcher\EventDispatcherInterface`. For example, [cache:clear](commands/cache_clear.md) dispatches `Drush\Event\CacheClearEvent`; call its `addType()` to offer another cache type.

[sql:sanitize](commands/sql_sanitize.md) does all of its work in subscribers. It dispatches `Drush\Event\SanitizeConfirmsEvent` to collect a summary, asks for confirmation once, and exits. A sanitizer subscribes to:

- `Drush\Event\SanitizeConfirmsEvent`: call `addMessage()` to describe what will be done.
- `ConsoleEvents::TERMINATE`: do the work, when the command is `sql:sanitize` and `getExitCode()` is 0.

The [subscribers that ship with Drush](https://github.com/drush-ops/drush/tree/15.x/src/EventSubscriber/sanitize) are working examples. `SanitizeSessionsSubscriber` is the shortest.

## Adding options to a command

A `ConsoleEvents::COMMAND` subscriber may add options to a command's definition. Symfony binds and validates input after this event, so the new options are accepted on the command line. Add them whichever command is running, so that `help`, `list` and shell completion show them too. This is how `sql:sanitize` gets `--sanitize-email` and its other options; see `SanitizeUserTableSubscriber` and `SanitizeOptionsTrait`.

```php
public function onCommand(ConsoleCommandEvent $event): void
{
    $application = $event->getCommand()?->getApplication();
    if (!$application?->has('sql:sanitize')) {
        return;
    }
    $command = $application->get('sql:sanitize');
    if (!$command->getNativeDefinition()->hasOption('example')) {
        $command->addOption('example', null, InputOption::VALUE_REQUIRED, 'An example option.');
    }
}
```
