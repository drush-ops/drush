# Events and Event Subscribers

!!! tip

      As of Drush 13.7.0, Drush recommends using event subscribers to modify the behavior of other commandfiles. Prior versions used hooks, which have been removed.

Drush makes use of [standard Symfony Console events](https://symfony.com/doc/current/components/console/events.html). Commands may inject an event dispatcher service via autowire (use `\Psr\EventDispatcher\EventDispatcherInterface` as the type hint) and then dispatch their own events (e.g. [cache:clear](commands/cache_clear.md)).

- `Symfony\Component\Console\Event\ConsoleCommandEvent`. Used to act before a command fires. That is, populate user input, check for validity, etc.
- `Symfony\Component\Console\Event\ConsoleTerminateEvent`. Used to run code after a command completes. Example: [DrupliconListener](https://github.com/drush-ops/drush/blob/15.x/src/Listeners/DrupliconListener.php)
