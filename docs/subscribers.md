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
