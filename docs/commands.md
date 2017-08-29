Creating Custom Drush Commands
==============================

Creating a new Drush command is easy. Follow these simple steps:

1. Run `drush generate drush-command-file`.
1. Enter the machine name of the module that should "own" the file.
1. Drush will then report that it created a commandfile and a drush.services.yml file. Edit those 2 files as needed.
1. Use the classes for the core Drush commands at /src/Drupal/Commands as inspiration and documentation.   
1. Once your two files are ready, run `drush cr` to get your command recognized by the Drupal container.

Global Drush Commands
==============================

Commandfiles that don't ship inside Drupal modules are called 'global' commandfiles. See the examples/Commands folder for examples. In general, its better to use modules to carry your Drush commands. If you still prefer using a global commandfiles, please note:

1. The file's namespace should be \Drush.
1. The filename must end in Commands.php (e.g. FooCommands.php)
1. The enclosing directory must be named Commands
1. The directory above Commands must be one of: 
    1.  Folders listed in the 'include' option (see `drush topic docs-configuration`).
    1.  The system-wide Drush commands folder, e.g. /usr/share/drush/commands.
    1.  The ".drush" folder in the user's HOME folder.
    1.  ../drush, /drush and /sites/all/drush relative to the current Drupal installation.

Avoiding the loading of certain Commandfiles
=================

- Folders and files containing other versions of Drush in their names will be \*skipped\* (e.g. devel.drush7.inc or drush7/devel.drush.inc). Names containing the current version of Drush (e.g. devel.drush9.inc) will be loaded.
- The --ignored-modules global option stops loading of commandfiles from specified modules.

