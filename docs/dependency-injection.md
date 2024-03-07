Dependency Injection
==================

Drush command files obtain references to the resources they need through a technique called _dependency injection_. When using this programing paradigm, a class by convention will never use the `new` operator to instantiate dependencies. Instead, it will store the other objects it needs in  class variables, and provide a way for other code to assign an object to that variable.

!!! tip

    Drush 11 and prior required [dependency injection via a drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files). This approach is deprecated in Drush 12+ and removed in Drush 13.

Autowire
------------------
:octicons-tag-24: 13
Command files may inject services by adding the Drupal [AutowireTrait](https://github.com/drush-ops/drush/blob/13.x/src/Commands/AutowireTrait.php) to their command class. This way, constructor parameter type hints determine the the injected service. When a type hint is insufficient, an [#[Autowire] Attribute](https://www.drupal.org/node/3396179) on the constructor property (with _service:_ named argument) can direct AutoWireTrait to the right service. If your commandfile is not found by Drush, add the `-vvv` option for debug info about any service instantiation errors. If Autowire is still insufficient, a commandfile may omit Autowire and implement its own `create()` method (see below).

create() method
------------------
:octicons-tag-24: 11.6+

Command files may inject services by adding a create() method to the commandfile. See [creating commands](commands.md) for instructions on how to use the Drupal Code Generator to create a simple command file starter. A create() method and a constructor will look something like this:
```php
class WootStaticFactoryCommands extends DrushCommands
{
    protected $configFactory;

    protected function __construct($configFactory)
    {
        $this->configFactory = $configFactory;
    }

    public static function create(ContainerInterface $container): self
    {
        return new static($container->get('config.factory'));
    }
```
See the [Drupal Documentation](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal-8#s-injecting-dependencies-into-controllers-forms-and-blocks) for details on how to inject Drupal services into your command file. Drush's approach mimics Drupal's blocks, forms, and controllers.

createEarly() method
------------------
:octicons-tag-24: 12.0+
Drush commands that need to be instantiated prior to bootstrap may do so by
utilizing the `createEarly()` static factory. This method looks and functions
exacty like the `create()` static factory, except it is only passed the Drush
container. The Drupal container is not available to command handlers that use
`createEarly()`.

Note also that Drush commands packaged with Drupal modules are not discovered
until after Drupal bootstraps, and therefore cannot use `createEarly()`. This
mechanism is only usable by PSR-4 discovered commands packaged with Composer
projects that are *not* Drupal modules.

Inflection
-----------------
A command class may implement the following interfaces. When doing so, implement the corresponding trait to satisfy the interface.

- [CustomEventAwareInterface](https://github.com/consolidation/annotated-command/blob/4.x/src/Events/CustomEventAwareInterface.php): Allows command files to [define and fire custom events](hooks.md) that other command files can hook. Example: [CacheCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/core/CacheCommands.php)
- [StdinAwareInterface](https://github.com/consolidation/annotated-command/blob/4.x/src/Input/StdinAwareInterface.php): Read from standard input. This class contains facilities to redirect stdin to instead read from a file, e.g. in response to an option or argument value. Example: [CacheCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/core/CacheCommands.php)