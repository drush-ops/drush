Dependency Injection
==================

Drush command files obtain references to the resources they need through a technique called _dependency injection_. When using this programing paradigm, a class by convention will never use the `new` operator to instantiate dependencies. Instead, it will store the other objects it needs in  class variables, and provide a way for other code to assign an object to that variable.

create() method
------------------
:octicons-tag-24: 11.6+

!!! tip

    Drush 11 and prior required [dependency injection via a drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files). This approach is deprecated in Drush 12 and will be removed in Drush 13.

Drush command files can inject services by adding a create() method to the commandfile. See [creating commands](commands.md) for instructions on how to use the Drupal Code Generator to create a simple command file starter. A create() method and a constructor will look something like this:
```php
class WootStaticFactoryCommands extends DrushCommands
{
    protected $configFactory;

    protected function __construct($configFactory)
    {
        $this->configFactory = $configFactory;
    }

    public static function create(ContainerInterface $container, DrushContainer $drush): self
    {
        return new static($container->get('config.factory'));
    }
```
See the [Drupal Documentation](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/services-and-dependency-injection-in-drupal-8#s-injecting-dependencies-into-controllers-forms-and-blocks) for details on how to inject Drupal services into your command file. Drush's approach mimics Drupal's blocks, forms, and controllers.

Note that if you do not need to pull any services from the Drush container, then you may
omit the second parameter to the `create()` method.

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