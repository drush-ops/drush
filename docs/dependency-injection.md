Dependency Injection
==================

Drush command files obtain references to the resources they need through a technique called _dependency injection_. When using this programing paradigm, a class by convention will never use the `new` operator to instantiate dependencies. Instead, it will store the other objects it needs in  class variables, and provide a way for other code to assign an object to that variable.

!!! tip

    Drush 11 and prior required [dependency injection via a drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files). This approach is deprecated in Drush 12+.

Autowire
------------------
:octicons-tag-24: 12.5+

Command files may inject Drush and Drupal services by adding the [AutowireTrait](https://github.com/drush-ops/drush/blob/12.x/src/Commands/AutowireTrait.php) to the class (example: [PmCommands](https://github.com/drush-ops/drush/blob/12.x/src/Commands/pm/MaintCommands.php)). This enables your [Constructor parameter type hints determine the the injected service](https://www.drupal.org/node/3396179). When a type hint is insufficient, an [#[Autowire] Attribute](https://www.drupal.org/node/3396179) on the constructor property (with _service:_ named argument) directs AutoWireTrait to the right service (example: [LoginCommands](https://github.com/drush-ops/drush/blob/12.x/src/Commands/core/LoginCommands.php)).

If your command is not found by Drush, add the `-vvv` option for debug info about any service instantiation errors. If Autowire is still insufficient, a commandfile may implement its own `create()` method (see below).

create() method
------------------
:octicons-tag-24: 11.6+

Command files not using Autowire may inject services by adding a create() method to the commandfile. The passed in Container is a [League container](https://container.thephpleague.com/) with a delegate to the Drupal container. Note that the type hint should be to `Psr\Container\ContanierInterface` not `Symfony\Component\DependencyInjection\ContainerInterface`. A create() method and constructor will look something like this:
```php
class WootStaticFactoryCommands extends DrushCommands
{
    protected $configFactory;

    protected function __construct($configFactory)
    {
        $this->configFactory = $configFactory;
    }

    public static function create(Psr\Container\ContainerInterface $container): self
    {
        return new static($container->get('config.factory'));
    }
```
See the [Drupal Documentation](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal-8#s-injecting-dependencies-into-controllers-forms-and-blocks) for details on how to inject Drupal services into your command file. This approach mimics Drupal's blocks, forms, and controllers.

createEarly() method
------------------
:octicons-tag-24: 12.0+

The `createEarly()` method was deprecated in Drush 12.5. Instead put a `#[CLI\Bootstrap(DrupalBootLevels::NONE)]` Attribute on the command class and inject dependencies via the usual `__construct` with [AutowireTrait](https://github.com/drush-ops/drush/blob/13.x/src/Commands/AutowireTrait.php). 

Note also that Drush commands packaged with Drupal modules are not discovered
until after Drupal bootstraps, and therefore cannot use `createEarly()`. This
mechanism is only usable by PSR-4 discovered commands packaged with Composer
projects that are *not* Drupal modules.

Inflection
-----------------
A command class may implement the following interfaces. When doing so, implement the corresponding trait to satisfy the interface.

- [CustomEventAwareInterface](https://github.com/consolidation/annotated-command/blob/4.x/src/Events/CustomEventAwareInterface.php): Allows command files to [define and fire custom events](hooks.md) that other command files can hook. Example: [CacheCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/core/CacheCommands.php)
- [StdinAwareInterface](https://github.com/consolidation/annotated-command/blob/4.x/src/Input/StdinAwareInterface.php): Read from standard input. This class contains facilities to redirect stdin to instead read from a file, e.g. in response to an option or argument value. Example: [CacheCommands](https://github.com/drush-ops/drush/blob/13.x/src/Commands/core/CacheCommands.php)
