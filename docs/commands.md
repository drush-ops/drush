Creating Custom Drush Commands
==============================

Creating a new Drush command or porting a legacy command is easy. Follow the steps below.

1. Run `drush generate drush-command-file`.
1. Drush will prompt for the machine name of the module that should "own" the file.
    1. (optional) Drush will also prompt for the path to a legacy command file to port. See [tips on porting command to Drush 9](https://weitzman.github.io/blog/port-to-drush9)
    1. The module selected must already exist and be enabled. Use `drush generate module-standard` to create a new module.
1. Drush will then report that it created a commandfile, a drush.services.yml file and a composer.json file. Edit those files as needed.
1. Use the classes for the core Drush commands at /src/Drupal/Commands as inspiration and documentation.
1. See the [dependency injection docs](dependency-injection.md) for interfaces you can implement to gain access to Drush config, Drupal site aliases, etc.
1. Once your two files are ready, run `drush cr` to get your command recognized by the Drupal container.

Providing Multiple Services File
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
If for some reason you need to load different services for different versions of Drush, simply define multiple services files in the `services` section. For example:
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

Global Drush Commands
==============================

Commandfiles that don't ship inside Drupal modules are called 'global' commandfiles. See the examples/Commands folder for examples. In general, its better to use modules to carry your Drush commands. If you still prefer using a global commandfiles, please note:

1. The file's namespace should be `\Drush\Commands\[dir-name]`.
1. The filename must end in Commands.php (e.g. FooCommands.php)
1. The enclosing directory must be named Commands
1. The directory above Commands must be one of: 
    1.  Folders listed in the 'include' option.
    1.  ../drush, /drush and /sites/all/drush relative to the current Drupal installation.

Avoiding the loading of certain Commandfiles (Note: not functional right now).
=================
- The --ignored-modules global option stops loading of commandfiles from specified modules.

