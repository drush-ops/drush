# Events and Event Listeners

!!! tip

      As of Drush 13.7.0, Drush recommends using listeners to modify the behavior of other commandfiles. Prior versions used [hooks](hooks.md) which are now deprecated.

Drush makes use of [standard Symfony Console events](https://symfony.com/doc/current/components/console/events.html). In addition, it also fires a custom _ConsoleDefinitionsEvent_ event (see below). Further, commands can inject an event dispatcher service via autowire (use `\Psr\EventDispatcher\EventDispatcherInterface` as the type hint) and then dispatch their own events (e.g. [sql:sanitize](commands/sql_sanitize.md), [cache:clear](commands/cache_clear.md)).

- `Drush\Event\ConsoleDefinitionsEvent`. Used to modify command definitions. That is, add/remove options, usages, etc. Example: [OptionsetSqlListener](https://github.com/drush-ops/drush/blob/14.x/src/Listeners/OptionsetSqlListener.php)  
- `Symfony\Component\Console\Event\ConsoleCommandEvent`. Used to act before a command fires. That is, populate user input, check for validity, etc. Example: [ValidateQueueNameListener](https://github.com/drush-ops/drush/blob/14.x/src/Listeners/ValidateQueueNameListener.php)
- `Symfony\Component\Console\Event\ConsoleTerminateEvent`. Used to run code after a command completes. Example: [DrupliconListener](https://github.com/drush-ops/drush/blob/14.x/src/Listeners/DrupliconListener.php)

## Implementing a listener

In the module that wants to alter command info, add a class that:

1. The class namespace, relative to base namespace, should be `Drupal\<module-name>\Drush\Listeners` and the class file should be located under the `src/Drush/Listeners` directory.
1. The filename must have a name like FooListener.php. The prefix `Foo` can be whatever string you want. The file must end in `Listener.php`.
1. The class should implement the `#[\Symfony\Component\EventDispatcher\Attribute\AsEventListener]` PHP Attribute.
1. Implement your logic via a `__invoke(ConsoleDefinitionsEvent $event)` method. Use a different type hint to listen on a different Event.
1. Inject the logger and any other needed dependencies in the class constructor.

Another example is the [WootDefinitionListener](https://github.com/drush-ops/drush/blob/14.x/sut/modules/unish/woot/src/Drush/Listeners/WootDefinitionListener.php) provided by the testing 'woot' module.
