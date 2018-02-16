Creating Custom Drush Commands
==============================

Creating a new Drush command or porting a legacy command is easy. Follow the steps below.

1. Run `drush generate drush-command-file`.
1. Drush will prompt for the machine name of the module that should "own" the file.
    1. (optional) Drush will also prompt for the path to a legacy command file to port. See [tips on porting command to Drush 9](https://weitzman.github.io/blog/port-to-drush9)
    1. The module selected must already exist and be enabled. Use `drush generate module-standard` to create a new module.
1. Drush will then report that it created a commandfile, a drush.services.yml file and a composer.json file. Edit those files as needed.
1. Use the classes for the core Drush commands at [/src/Drupal/Commands](https://github.com/drush-ops/drush/tree/master/src/Drupal/Commands) as inspiration and documentation.
1. See the [dependency injection docs](dependency-injection.md) for interfaces you can implement to gain access to Drush config, Drupal site aliases, etc.
1. Once your two files are ready, run `drush cr` to get your command recognized by the Drupal container.

Specifying the Services File
================================

A module's composer.json file stipulates the filename where the Drush services (e.g. the Drush command files) are defined. The default services file is `drush.services.yml`, which is defined in the extra section of the composer.json file as follows:
```
  "extra": {
    "drush": {
      "services": {
        "drush.services.yml": "^9"
      }
    }
  }
```
If for some reason you need to load different services for different versions of Drush, simply define multiple services files in the `services` section. The first one found will be used. For example:
```
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

Global Drush Commands
==============================

Commandfiles that don't ship inside Drupal modules are called 'global' commandfiles. See the [examples/Commands](/examples/Commands) folder for examples. In general, its better to use modules to carry your Drush commands. If you still prefer using a global commandfiles, please note:

1. The file's fully qualified namespace should be `\Drush\Commands`.
1. The filename must be have a name like Commands/FooCommands.php
    1. The prefix `Foo` can be whatever string you want. The file must end in `Commands.php`
    1. The enclosing directory must be named `Commands`
1. The directory above Commands must be one of: 
    1.  A Folder listed in the 'include' option. include may be provided via config or via CLI.
    1.  ../drush, /drush or /sites/all/drush. These paths are relative to Drupal root.
