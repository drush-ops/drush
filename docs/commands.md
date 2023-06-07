# Creating Custom Commands

!!! tip

    Drush 11 and prior required [dependency injection via a drush.services.yml file](https://www.drush.org/11.x/dependency-injection/#services-files). This approach is deprecated in Drush 12 and will be removed in Drush 13. See [create() method](dependency-injection.md#create-method).

Creating a new Drush command is easy. Follow the steps below.

1. Run `drush generate drush-command-file`.
2. Drush will prompt for the machine name of the module that should "own" the file. The module selected must already exist and be enabled. Use `drush generate module-standard` to create a new module.
3. Drush will then report that it created a commandfile. Edit as needed.
4. Use the classes for the core Drush commands at [/src/Commands](https://github.com/drush-ops/drush/tree/12.x/src/Commands) as inspiration and documentation.
5. See the [dependency injection docs](dependency-injection.md) for interfaces you can implement to gain access to Drush config, Drupal site aliases, etc. Also note the [create() method](dependency-injection.md#create-method) for injecting Drupal or Drush dependencies.
6. Write PHPUnit tests based on [Drush Test Traits](https://github.com/drush-ops/drush/blob/12.x/docs/contribute/unish.md#drush-test-traits).

## Attributes or Annotations
The following are both valid ways to declare a command:

=== "PHP8 Attributes"
    
    ```php
    use Drush\Attributes as CLI;

    /**
     * Retrieve and display xkcd cartoons (attribute variant).
     */
    #[CLI\Command(name: 'xkcd:fetch-attributes', aliases: ['xkcd-attributes'])]
    #[CLI\Argument(name: 'search', description: 'Optional argument to retrieve the cartoons matching an index, keyword, or "random".')]
    #[CLI\Option(name: 'image-viewer', description: 'Command to use to view images (e.g. xv, firefox).', suggestedValues: ['open', 'xv', 'firefox'])]
    #[CLI\Option(name: 'google-custom-search-api-key', description: 'Google Custom Search API Key')]
    #[CLI\Usage(name: 'drush xkcd', description: 'Retrieve and display the latest cartoon')]
    #[CLI\Usage(name: 'drush xkcd sandwich', description: 'Retrieve and display cartoons about sandwiches.')]
    public function fetch($search = null, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => 'AIza']) {
        $this->doFetch($search, $options);
    }
    ```

=== "Annotations"
    
    ```php
    /**
     * @command xkcd:fetch
     * @param $search Optional argument to retrieve the cartoons matching an index number, keyword, or "random".
     * @option image-viewer Command to use to view images (e.g. xv, firefox).
     * @option google-custom-search-api-key Google Custom Search API Key.
     * @usage drush xkcd
     *   Retrieve and display the latest cartoon.
     * @usage drush xkcd sandwich
     *   Retrieve and display cartoons about sandwiches.
     * @aliases xkcd
    */
    public function fetch($search = null, $options = ['image-viewer' => 'open', 'google-custom-search-api-key' => 'AIza']) {
        $this->doFetch($search, $options);
    }
    ```

- A commandfile that will only be used on PHP8+ should [use PHP Attributes](https://github.com/drush-ops/drush/pull/4821) instead of Annotations.
- [See Attributes provided by Drush core](https://www.drush.org/api/Drush/Attributes.html). Custom code can add additional attributes.

## Altering Command Info
Drush command info (annotations/attributes) can be altered from other modules. This is done by creating and registering 'command info alterers'. Alterers are class services that are able to intercept and manipulate an existing command annotation.

In order to alter an existing command info, follow the steps below:

1. In the module that wants to alter a command info, add a service class that implements the `\Consolidation\AnnotatedCommand\CommandInfoAltererInterface`.
1. In the module `drush.services.yml` declare a service pointing to this class and tag the service with the `drush.command_info_alterer` tag.
1. In that class, implement the alteration logic in the `alterCommandInfo()` method.
1. Along with the alter code, it's strongly recommended to log a debug message explaining what exactly was altered. This makes things easier on others who may need to debug the interaction of the alter code with other modules. Also it's a good practice to inject the the logger in the class constructor.

For an example, see [WootCommandInfoAlterer](https://github.com/drush-ops/drush/blob/12.x/sut/modules/unish/woot/src/WootCommandInfoAlterer.php) provided by the testing 'woot' module.

## Symfony Console Commands
Drush lists and runs Symfony Console commands, in addition to more typical annotated commands. See [this test](https://github.com/drush-ops/drush/blob/eed106ae4510d5a2df89f8e7fd54b41ffb0aa5fa/tests/integration/AnnotatedCommandCase.php#L178-L180) and this [commandfile](https://github.com/drush-ops/drush/blob/12.x/sut/modules/unish/woot/src/Commands/GreetCommand.php).

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
* The class and file name ends with `*DrushCommands`, e.g. `FooDrushCommands`.

Auto-discovered commandfiles should declare their Drush version compatibility via a `conflict` directive. For example, a Composer-managed site-wide command that works with both Drush 11 and Drush 12 might contain something similar to the following in its composer.json file:
```json
    "conflict": {
        "drush/drush": "<11.0",
    }
```
Using `require` in place of `conflict` is not recommended.

!!! warning "Symlinked packages"

    While it is good practice to make your custom commands into a Composer package, please beware that symlinked packages (by using the composer repository type [Path](https://getcomposer.org/doc/05-repositories.md#path)) will **not** be discovered by Drush. When in development, it is recommended to [specify your package's](https://github.com/drush-ops/drush/blob/12.x/examples/example.drush.yml#L52-L67) path in your `drush.yml` to have quick access to your commands.

## Site-wide Commands
Commandfiles that are installed in a Drupal site and are not bundled inside a Drupal module are called _site-wide_ commandfiles. Site-wide commands may either be added directly to the Drupal site's repository (e.g. for site-specific policy files), or via `composer require`. See the [examples/Commands](https://github.com/drush-ops/drush/tree/12.x/examples/Commands) folder for examples. In general, it's preferable to use modules to carry your Drush commands.

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