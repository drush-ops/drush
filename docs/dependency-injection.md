Dependency Injection
==================

Drush 9 command files obtain references to the resources they need through a technique called _dependency injection_. When using this programing paradigm, a class by convention will never use the `new` operator to instantiate dependencies. Instead, it will store the other objects it needs in  class variables, and provide a way for other code to assign an object to that variable.

Types of Injection
-----------------------

There are two ways that a class can receive its dependencies. One is called “constructor injection”, and the other is called “setter injection”.

*Example of constructor injection:*
```
    public function __construct(DependencyType $service)
    {
        $this->service = $service;
    }
```

*Example of setter injection:*
```
    public function setService(DependencyType $service)
    {
        $this->service = $service;
    }
```
A class should use one or the other of these methods. The code that is responsible for providing the dependencies a class need is usually an object called the dependency injection container.

Services Files
------------------

Drush command files can request that Drupal inject services by using a drush.services.yml file. See the document [commands](commands.md) for instructions on how to use the Drupal Code Generator to create a simple command file starter with a drush.services.yml file. An initial services file will look something like this:
```
services:
  my_module.commands:
    class: \Drupal\my_module\Commands\MyModuleiCommands
    tags:
      - { name: drush.command }
```
See the [Drupal Documentation](https://www.drupal.org/docs/8/api/services-and-dependency-injection/services-and-dependency-injection-in-drupal-8) for details on how to inject Drupal services into your command file. The process is exactly the same as using a Drupal services.yml file to inject services into your module classes.

Inflection
-------------

Drush will also inject dependencies that it provides using a technique called inflection. Inflection is a kind of dependency injection that works by way of a set of provided inflection interfaces, one for each available service. Each of these interfaces will define one or more setter methods (usually only one); these will automatically be called by Drush when the commandfile object is instantiated. The command only needs to implement this method and save the provided object in a class field. There is usually a corresponding trait that may be included via a `use` statement to fulfill this requirement.

For example:

```
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
   * Prints the currenbt alias name and info.
   *
   * @command mymodule:myAlias
   * @return \Consolidation\OutputFormatters\StructuredData\ListDataFromKeys
   */
  public function myAlias() 
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

- AutoloaderAwareInterface: Provides access to the class loader.
- SiteAliasManagerAwareInterface: The site alias manager [allows alias records to be obtained](site-alias-manager.md).
- CustomEventAwareInterface: Allows command files to [define and fire custom events](hooks.md) that other command files can hook.
- ContainerAwareInterface: Provides Drush's dependency injection container.

Note that although the autoloader and Drush dependency injection container is available and may be injected into your command file if needed, this should be avoided. Favor using services that can be injected from Drupal or Drush. Some of the objects in the container are not part of the Drush public API, and may not maintain compatibility in minor and patch releases.
