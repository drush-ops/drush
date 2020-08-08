# Creating Custom Drush Commands

Creating a new Drush command or porting a legacy command is easy. Follow the steps below.

1. Run `drush generate drush-command-file`.
1. Drush will prompt for the machine name of the module that should "own" the file.
    1. (optional) Drush will also prompt for the path to a legacy command file to port. See [tips on porting command to Drush 9](https://weitzman.github.io/blog/port-to-drush9)
    1. The module selected must already exist and be enabled. Use `drush generate module-standard` to create a new module.
1. Drush will then report that it created a commandfile, a drush.services.yml file and a composer.json file. Edit those files as needed.
1. Use the classes for the core Drush commands at [/src/Drupal/Commands](https://github.com/drush-ops/drush/tree/10.x/src/Drupal/Commands) as inspiration and documentation.
1. See the [dependency injection docs](dependency-injection.md) for interfaces you can implement to gain access to Drush config, Drupal site aliases, etc.
1. Write PHPUnit tests based on [Drush Test Traits](https://github.com/drush-ops/drush/tree/10.x/tests#drush-test-traits).
1. Once your two files are ready, run `drush cr` to get your command recognized by the Drupal container.

## Specifying the Services File

A module's composer.json file stipulates the filename where the Drush services (e.g. the Drush command files) are defined. The default services file is `drush.services.yml`, which is defined in the extra section of the composer.json file as follows:
```json
  "extra": {
    "drush": {
      "services": {
        "drush.services.yml": "^9"
      }
    }
  }
```
If for some reason you need to load different services for different versions of Drush, simply define multiple services files in the `services` section. The first one found will be used. For example:
```json
  "extra": {
    "drush": {
      "services": {
        "drush-9-99.services.yml": "^9.99",
        "drush.services.yml": "^9"
      }
    }
  }
```
In this example, the file `drush-9-99.services.yml` loads commandfile classes that require features only available in Drush 9.99 and later, and drush.services.yml loads an older commandfile implementation for earlier versions of Drush.

It is also possible to use [version ranges](https://getcomposer.org/doc/articles/versions.md#version-range) to exactly specify which version of Drush the services file should be used with (e.g. `"drush.services.yml": ">=9 <9.99"`).

In Drush 9, the default services file, `drush.services.yml`, will be used in instances where there is no `services` section in the Drush extras of the project's composer.json file. In Drush 10, however, the services section must exist, and must name the services file to be used. If a future Drush extension is written such that it only works with Drush 10 and later, then its entry would read `"drush.services.yml": "^10"`, and Drush 9 would not load the extension's commands. It is all the same recommended that Drush 9 extensions explicitly declare their services file with an appropriate version constraint.

## Altering Drush Command Info
Drush command info (annotations) can be altered from other modules. This is done by creating and registering 'command info alterers'. Alterers are class services that are able to intercept and manipulate an existing command annotation.

In order to alter an existing command info, follow the steps below:

1. In the module that wants to alter a command info, add a service class that implements the `\Consolidation\AnnotatedCommand\CommandInfoAltererInterface`.
1. In the module `drush.services.yml` declare a service pointing to this class and tag the service with the `drush.command_info_alterer` tag.
1. In that class, implement the alteration logic in the `alterCommandInfo()` method.
1. Along with the alter code, it's strongly recommended to log a debug message explaining what exactly was altered. This makes things easier on others who may need to debug the interaction of the alter code with other modules. Also it's a good practice to inject the the logger in the class constructor.

For an example, see the alterer class provided by the testing 'woot' module: `tests/functional/resources/modules/d8/woot/src/WootCommandInfoAlterer.php`.

## Site-Wide Drush Commands
Commandfiles that are installed in a Drupal site and are not bundled inside a Drupal module are called 'site-wide' commandfiles. Site-wide commands may either be added directly to the Drupal site's repository (e.g. for site-specific policy files), or via `composer require`. See the [examples/Commands](https://github.com/drush-ops/drush/tree/10.x/examples/Commands) folder for examples. In general, it's better to use modules to carry your Drush commands, as module-based commands may [participate in Drupal's dependency injection via the drush.services.yml](#specifying-the-services-file).

If you still prefer using site-wide commandfiles, here are some examples of valid commandfile names and namespaces:

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

Installing commands as part of a Composer project requires that the project's type be `drupal-drush`, and that the `installer-paths` in the Drupal site's composer.json file contains `"drush/Commands/contrib/{$name}": ["type:drupal-drush"]`. It is also possible to commit projects with a similar layout using a directory named `custom` in place of `contrib`; if this is done, then the directory `custom` will not be considered to be part of the commandfile's namespace.

If a site-wide commandfile is added via a Composer package, then it may declare any dependencies that it may need in its composer.json file. Site-wide commandfiles that are committed directly to a site's repository only have access to the dependencies already available in the site. Site-wide commandfiles should declare their Drush version compatibility via a `conflict` directive. For example, a Composer-managed site-wide command that works with both Drush 8 and Drush 9 might contain something similar to the following in its composer.json file:
```json
    "conflict": {
        "drush/drush": "<8.2 || >=9.0 <9.6 || >=10.0",
    }
```
Using `require` in place of `conflict` is not recommended.

A site-wide commandfile should have tests that run with each (major) version of Drush that is supported. You may model your test suite after the [example drush extension](https://github.com/drush-ops/example-drush-extension) project, which works on Drush ^8.2 and ^9.6.

## Global Drush Commands
Commandfiles that are not part of any Drupal site are called 'global' commandfiles. Global commandfiles are not supported by default; in order to enable them, you must configure your `drush.yml` configuration file to add an `include` search location.

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

It is recommended that you avoid global Drush commands, and favor site-wide commandfiles instead. If you really need a command or commands that are not part of any Drupal site, consider making a stand-alone script or custom .phar instead. See [ahoy](https://github.com/ahoy-cli/ahoy), [Robo](https://github.com/consolidation/robo) and [g1a/starter](https://github.com/g1a/starter) as potential starting points.

!!! warning "Symlinked packages"
    While it is good practice to make your custom commands into a Composer package, please beware that symlinked packages (by using the composer repository type [Path](https://getcomposer.org/doc/05-repositories.md#path)) will **not** be discovered by Drush. When in development, it is recommended to [specify your package's](https://github.com/drush-ops/drush/blob/10.x/examples/example.drush.yml#L52-L67) path in your `drush.yml` to have quick access to your commands.
