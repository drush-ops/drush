Creating Custom Drush Commands
==============================

Creating a new Drush command is very easy. Follow these simple steps:

1.  Copy the example commandfile at lib/Drush/Commands/examples/Commands/SandwichCommands.php to mymodule/src/Drush/Commands/MyModuleCommands.php
1.  Edit the namespace and classnames in your file to match the file's location.
1.  Use the classes for the core Drush commands at /lib/Drush/Commands as inspiration and documentation.   
1.  Rename and edit the makeSandwich() method. Carefully add/edit/remove annotations above the method and put your logic inside the method.

Drush searches for commandfiles in the following locations:

-   Folders listed in the 'include' option (see `drush topic docs-configuration`).
-   The system-wide Drush commands folder, e.g. /usr/share/drush/commands
-   The ".drush" folder in the user's HOME folder.
-   /drush and /sites/all/drush in the current Drupal installation
-   All enabled modules in the current Drupal installation
-   Folders and files containing other versions of Drush in their names will be \*skipped\* (e.g. devel.drush4.inc or drush4/devel.drush.inc). Names containing the current version of Drush (e.g. devel.drush5.inc) will be loaded.

