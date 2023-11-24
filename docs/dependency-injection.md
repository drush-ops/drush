Dependency Injection
==================

Drush command files obtain references to the resources they need through a technique called _dependency injection_. When using this programing paradigm, a class by convention will never use the `new` operator to instantiate dependencies. Instead, it will store the other objects it needs in  class variables, and provide a way for other code to assign an object to that variable.

Types of Injection
-----------------------

There are two ways that a class can receive its dependencies. One is called “constructor injection”, and the other is called “setter injection”.

*Example of constructor injection:*
```php
    public function __construct(DependencyType $service)
    {
        $this->service = $service;
    }
```

*Example of setter injection:*
```php
    public function setService(DependencyType $service)
    {
        $this->service = $service;
    }
```
The code that is responsible for providing the dependencies a class needs is usually an object called the dependency injection container.

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

Inflection
-------------

!!! tip

    Inflection is deprecated in Drush 12; use `create()` or `createEarly()` instead.
    Some classes are no longer available for inflection in Drush 12, and more (or potentially all)
    may be removed in Drush 13.

Drush will also inject dependencies that it provides using a technique called inflection. Inflection is a kind of dependency injection that works by way of a set of provided inflection interfaces, one for each available service. Each of these interfaces will define one or more setter methods (usually only one); these will automatically be called by Drush when the commandfile object is instantiated. The command only needs to implement this method and save the provided object in a class field. There is usually a corresponding trait that may be included via a `use` statement to fulfill this requirement.

For example:

```php
<?php
namespace Drupal\my_module\Commands;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\ListDataFromKeys;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class MyModuleCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
  use SiteAliasManagerAwareTrait;
  
  /**
   * Prints the current alias name and info.
   */
  #[CLI\Command(name: 'mymodule:myAlias')]
  public function myAlias(): ListDataFromKeys 
  {
    $selfAlias = $this->siteAliasManager()->getSelf();
    $this->logger()->success(‘The current alias is {name}’, [‘name’ => $selfAlias]);
    return new ListDataFromKeys($aliasRecord->export());
  }
}
```

All Drush command files extend DrushCommands. DrushCommands implements ConfigAwareInterface, IOAwareInterface, LoggerAwareInterface, which gives access to `$this->getConfig()`, `$this->logger()` and other ways to do input and output. See the [IO documentation](io.md) for more information.

Any additional services that are desired must be injected by implementing the appropriate inflection interface.

Additional Interfaces:

- SiteAliasManagerAwareInterface: The site alias manager [allows alias records to be obtained](site-alias-manager.md).
- CustomEventAwareInterface: Allows command files to [define and fire custom events](hooks.md) that other command files can hook.

Note that although the autoloader and Drush dependency injection container is available and may be injected into your command file if needed, this should be avoided. Favor using services that can be injected from Drupal or Drush. Some of the objects in the container are not part of the Drush public API, and may not maintain compatibility in minor and patch releases.
