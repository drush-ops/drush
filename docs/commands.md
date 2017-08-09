Creating Custom Drush Commands
==============================

Creating a new Drush command is very easy. Follow these simple steps:

1. Run `drush generate drush-command-file`.
1. Enter the machine name of the module that should "own" the file.
1. Drush will then report that it created a commandfile and a drush.services.yml file. Edit those 2 files as needed.
1. Use the classes for the core Drush commands at /lib/Drush/Commands as inspiration and documentation.   
1. Once your two files are ready, run `drush cr` to get your command recognized by the Drupal container.

Drush searches for commandfiles in the following locations:

-  Folders listed in the 'include' option (see `drush topic docs-configuration`).
-  The system-wide Drush commands folder, e.g. /usr/share/drush/commands.
-  The ".drush" folder in the user's HOME folder.
-  ../drush, /drush and /sites/all/drush relative to the current Drupal installation.
-  All enabled modules in the current Drupal installation.

Note: Folders and files containing other versions of Drush in their names will be \*skipped\* (e.g. devel.drush7.inc or drush7/devel.drush.inc). Names containing the current version of Drush (e.g. devel.drush9.inc) will be loaded.

