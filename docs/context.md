Drush Contexts
==============

The drush contexts API acts as a storage mechanism for all options, arguments and configuration settings that are loaded into drush.

This API also acts as an IPC mechanism between the different drush commands, and provides protection from accidentally overriding settings that are needed by other parts of the system.

It also avoids the necessity to pass references through the command chain and allows the scripts to keep track of whether any settings have changed since the previous execution.

This API defines several contexts that are used by default.

Argument contexts
-----------------

These contexts are used by Drush to store information on the command. They have their own access functions in the forms of drush\_set\_arguments(), drush\_get\_arguments(), drush\_set\_command(), drush\_get\_command().

-   command : The drush command being executed.
-   arguments : Any additional arguments that were specified.

Setting contexts
----------------

These contexts store options that have been passed to the drush.php script, either through the use of any of the config files, directly from the command line through --option='value' or through a JSON encoded string passed through the STDIN pipe.

These contexts are accessible through the drush\_get\_option() and drush\_set\_option() functions. See drush\_context\_names() for a description of all of the contexts.

Drush commands may also choose to save settings for a specific context to the matching configuration file through the drush\_save\_config() function.

Available Setting contexts
--------------------------

These contexts are evaluated in a certain order, and the highest priority value is returned by default from drush\_get\_option. This allows scripts to check whether an option was different before the current execution.

Specified by the script itself :

-   process : Generated in the current process.
-   cli : Passed as --option=value to the command line.
-   stdin : Passed as a JSON encoded string through stdin.
-   alias : Defined in an alias record, and set in the alias context whenever that alias is used.
-   specific : Defined in a command-specific option record, and set in the command context whenever that command is used.

Specified by config files :

-   custom : Loaded from the config file specified by --config or -c
-   site : Loaded from the drushrc.php file in the Drupal site directory.
-   drupal : Loaded from the drushrc.php file in the Drupal root directory.
-   user : Loaded from the drushrc.php file in the user's home directory.
-   drush : Loaded from the drushrc.php file in the $HOME/.drush directory.
-   system : Loaded from the drushrc.php file in the system's $PREFIX/etc/drush directory.
-   drush : Loaded from the drushrc.php file in the same directory as drush.php.

Specified by the script, but has the lowest priority :

-   default : The script might provide some sensible defaults during init.

