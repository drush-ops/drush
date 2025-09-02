# Creating Custom Commands

!!! tip

      1. Drush 13+ expects commandfiles to use the [AutowireTrait](https://github.com/drush-ops/drush/blob/13.x/src/Commands/AutowireTrait.php) to inject Drupal and Drush dependencies. Prior versions used a [drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files) which is now deprecated and will be removed in Drush 14.
      1. Drush 12+ expects all commandfiles in the `<module-name>/src/Drush/<Commands|Generators|Listeners>` directory. The `Drush` subdirectory is a new requirement.

Creating a new Drush command is easy. Follow the steps below.

1. Run `drush generate drush:command-file`.
2. Drush will prompt for the machine name of the module that should _own_ the file. The module selected must already exist and be enabled. Use `drush generate module` to create a new module.
3. Drush will then report that it created a commandfile. Edit as needed.
4. Use the classes for the core Drush commands at [/src/Commands](https://github.com/drush-ops/drush/tree/13.x/src/Commands) as inspiration and documentation.
5. You may [inject dependencies](dependency-injection.md) into a command instance.
6. Write PHPUnit tests based on [Drush Test Traits](https://github.com/drush-ops/drush/blob/13.x/docs/contribute/unish.md#drush-test-traits).

## Symfony Console Commands

Drush 14+ deprecates old-style Annotated Commands in favor of pure [Symfony Console commands](https://symfony.com/doc/current/console.html). This implies:

- Each command lives in its own class file
- The command class extends `Symfony\Component\Console\Command\Command` directly. The base class `DrushCommands` is deprecated.
- The command class should use Console's #[AsCommand] Attribute to declare its name, aliases, and hidden status. The `#[Command]` Attribute is deprecated.
- Options and Arguments moved from Attributes to a configure() method on the command class
- The main logic of the command moved to an execute() method on the command class.
- User interaction now happens in an interact() method on the command class.
- Drush and Drupal services may still be autowired. This is how you access the logger. Build own $io as needed.
- Commands that wish to offer multiple _output formats_ (yes please!) should (Example: 
    - See [TwigUnusedCommand(https://www.drush.org/latest/commands/twig_unused/)] or [SqlDumpCommand](https://www.drush.org/latest/commands/sql_dump/)) as examples.
    - Implement the [Formatter Attribute](https://github.com/drush-ops/drush/blob/13.x/src/Attributes/Formatter.php).
    - `execute()` is largely boilerplate. By convention, do your work in a `doExecute()` method instead.
- [See Optionset and Validate Attributes provided by Drush core](https://github.com/drush-ops/drush/blob/13.x/src/Attributes). Custom code can supply additional Attributes+Listeners, which any command may choose to use.

## Altering Command Info

Drush command info can be altered from other modules. This is done by creating and registering a command definition listener. Listeners are dispatched once after non-bootstrap commands are instantiated and once again after bootstrap commands are instantiated.

In the module that wants to alter command info, add a class that:

1. The class namespace, relative to base namespace, should be `Drupal\<module-name>\Drush\Listeners` and the class file should be located under the `src/Drush/Listeners` directory.
1. The filename must have a name like FooListener.php. The prefix `Foo` can be whatever string you want. The file must end in `Listener.php`.
1. The class should implement the `#[AsListener]` PHP Attribute.
1. Implement the alteration logic via a `__invoke(ConsoleDefinitionsEvent $event)` method.
1. Along with the alter code, it's strongly recommended to log a debug message explaining what exactly was altered. This makes things easier on others who may need to debug the interaction of the alter code with other modules. Also, it's a good practice to inject the logger in the class constructor.

For an example, see [WootDefinitionListener](https://github.com/drush-ops/drush/blob/13.x/sut/modules/unish/woot/src/Drush/Liseners/WootDefinitionListener.php) provided by the testing 'woot' module.

## Auto-discovered commands (PSR4)

Such commands are auto-discovered by their class PSR4 namespace and class/file name suffix. Drush will auto-discover commands if:

* The commands class is PSR4 auto-loadable.
* The commands class namespace, relative to base namespace, is `Drush\Commands`. For instance, if a Drush command provider third party library maps this PSR4 autoload entry:
  ```json
  "autoload": {
    "psr-4": {
      "My\\Custom\\Library\\": "src"
    }
  }
  ```
  then the Drush global commands class namespace should be `My\Custom\Library\Drush\Commands` and the class file should be located under the `src/Drush/Commands` directory.
* The class and file name ends with `*Commands`, e.g. `FooCommands`.

Auto-discovered commandfiles should declare their Drush version compatibility via a `conflict` directive. For example, a Composer-managed site-wide command that works with both Drush 11 and Drush 12 might contain something similar to the following in its composer.json file:
```json
    "conflict": {
        "drush/drush": "<11.0",
    }
```
Using `require` in place of `conflict` is not recommended.

!!! warning "Symlinked packages"

    While it is good practice to make your custom commands into a Composer package, please beware that symlinked packages (by using the composer repository type [Path](https://getcomposer.org/doc/05-repositories.md#path)) will **not** be discovered by Drush. When in development, it is recommended to [specify your package's](https://github.com/drush-ops/drush/blob/13.x/examples/example.drush.yml#L52-L67) path in your `drush.yml` to have quick access to your commands.

## Site-wide Commands
Commandfiles that are installed in a Drupal site and are not bundled inside a Drupal module are called _site-wide_ commandfiles. Site-wide commands may either be added directly to the Drupal site's repository (e.g. for site-specific policy files), or via `composer require`. See the [examples/Commands](https://github.com/drush-ops/drush/tree/13.x/examples/Commands) folder for examples. In general, it's preferable to use modules to carry your Drush commands.

Here are some examples of valid commandfile names and namespaces:

1. Simple
     - Filename: $PROJECT_ROOT/drush/Commands/ExampleCommands.php
     - Namespace: Drush\Commands
1. Nested in a subdirectory committed to the site's repository
     - Filename: $PROJECT_ROOT/drush/Commands/example/ExampleCommands.php
     - Namespace: Drush\Commands\example
1. Nested in a subdirectory installed via a Composer package
    - Filename: $PROJECT_ROOT/drush/Commands/contrib/dev_modules/ExampleCommands.php
    - Namespace: Drush\Commands\dev_modules

Note: Make sure you do _not_ include `src` in the path to your command. Your command may not be discovered and have additional problems.

If a commandfile is added via a Composer package, then it may declare any dependencies that it may need in its composer.json file. Site-wide commandfiles that are committed directly to a site's repository only have access to the dependencies already available in the site. 

A site-wide commandfile should have tests that run with each (major) version of Drush that is supported. You may model your test suite after the [example drush extension](https://github.com/drush-ops/example-drush-extension) project.

## Global commands discovered by configuration

!!! warning "Deprecation"

    Configuration discovery has been deprecated and will be removed in a future version of Drush. It is recommended that you avoid global Drush commands, and favor site-wide or PSR4 discovered commandfiles instead. If you really need commands that are not part of any Drupal site, consider making a stand-alone script or custom .phar instead. See [ahoy](https://github.com/ahoy-cli/ahoy), [Robo](https://github.com/consolidation/robo) and [g1a/starter](https://github.com/g1a/starter) as potential starting points.

Global commandfiles discoverable by configuration are not supported by default; in order to enable them, you must configure your `drush.yml` configuration file to add an `include` search location.

For example:

```yaml
drush:
  paths:
    include:
      - '${env.home}/.drush/commands'
```      
With this configuration in place, global commands may be placed as described in the Site-Wide Drush Commands section above. Global commandfiles may not declare any dependencies of their own; they may only use those dependencies already available via the autoloader.

!!! tip

    1. The filename must be have a name like Commands/ExampleCommands.php
       1. The prefix `Example` can be whatever string you want.
       1. The file must end in `Commands.php`
        1. The directory above `Commands` must be one of:
            1.  A Folder listed in the 'include' option. Include may be provided via [config](#global-drush-commands) or via CLI.
            1.  ../drush, /drush or /sites/all/drush. These paths are relative to Drupal root.

Xdebug
------------

Drush disables Xdebug by default. This improves performance substantially, because developers are often debugging something other than Drush and they still need to clear caches, import config, etc. There are two equivalent ways to override Drush's disabling of Xdebug:

- Pass the `--xdebug` global option.
- Set an environment variable: `DRUSH_ALLOW_XDEBUG=1 drush [command]`
