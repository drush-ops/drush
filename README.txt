// $Id$

NOTE: this module is currently in a pre-alpha state. Come back later unless
you're a developer and don't mind looking at the code to figure things out.

DESCRIPTION
-----------
drush is a command line shell and Unix scripting interface for Drupal.

USAGE
-----
Once installed and setup (see below), you can use drush as follows while in
a Drupal directory:

  Usage: drush [options] <service> <action> ...

  Options:
    -q          Don't output anything at all (be as quiet as possible).
    -v          Display all output from an action (be verbose).
    -y          Assume that the answer to simple yes/no questions is 'yes'.
    -s          Simulate actions, but do not actually perform them.
    -r path     Drupal root directory to use (default: current directory).
    -h host     HTTP host name to use (for multi-site Drupal installations).
    -u uid      Drupal user name (or numeric ID) to execute actions under.

Use the 'help' command to get a list of available services and actions:

  # drush help

(You can also view the command listing at administer >> help >> drush.)

COMMANDS
--------
The following built-in commands are currently available:

  version
    Outputs the drush version number.

  help
    Outputs usage instructions and lists the available services and
    commands.

  usage
    Outputs usage instructions for drush.

  services
    Lists all available drush services with their descriptions.

  actions
    Lists all available drush commands with their descriptions.

  aliases
    Lists all defined drush aliases with their expansions.

  cache clear
    Expires all data, or all entries like a given wildcard, from the Drupal
    page cache.

  cache list
    Lists all entries currently in the Drupal page cache.

  cache get
    Displays the contents of the Drupal cache entry of a given name.

  cache set
    Replaces the contents of the Drupal cache entry of a given name.

  cache enable
    Enables the Drupal page cache.

  cache disable
    Disables the Drupal page cache.

  drupal version
    Displays the currently installed Drupal core version.

  drupal settings
    Outputs a table of all Drupal settings.

  drupal cron
    Fires off the cron handles for all installed Drupal modules.

  drupal notify
    Sends a ping to a specified Drupal directory server.

  generate password
    Generates a random, alphanumeric password of the given length (default:
    10 chars).

  generate module
    Generates a Drupal module skeleton for the given module name.

  generate theme
    Generates a Drupal theme skeleton for the given theme name.

  module list
    Lists all installed Drupal modules with their versions and descriptions.

  module list outdated
    Lists all installed Drupal modules that have updates available.

  module list available
    Lists all available Drupal modules from the Drupal CVS repository.

  module info
    Displays information on a given installed Drupal module.

  module version
    Displays the current version number of a given installed Drupal module.

  module download
    Downloads a given Drupal module into to the modules/ directory using
    CVS.

  module install
    Downloads, installs and enables a given Drupal module using CVS.

  module enable
    Enables a given Drupal module.

  module disable
    Disables a given Drupal module.

  module uninstall
    Disables, uninstalls and deletes a given Drupal module. (Use caution!)

  php version
    Outputs the PHP version.

  php credits
    Outputs the credits listing the PHP developers, modules, etc.

  php info
    Outputs lots of PHP system and configuration information.

  php config
    Outputs a table of all PHP configuration options.

  php extensions
    Outputs the names of all the modules compiled and loaded in the PHP
    interpreter.

  php functions
    Outputs the names of all the functions exported by the given PHP
    extension.

  theme list
    Lists all currently installed Drupal themes.

  theme download
    Downloads a given theme using CVS.

  watchdog tail
    Displays the most recent watchdog log messages (default: 10 messages).

  watchdog delete
    Deletes all log messages of a certain type from the watchdog log
    (default: all).

  watchdog notice
    Logs a system message using the WATCHDOG_NOTICE severity level.

  watchdog warning
    Logs a system message using the WATCHDOG_WARNING severity level.

  watchdog error
    Logs a system message using the WATCHDOG_ERROR severity level.

REQUIREMENTS
------------
This version of drush is designed for Drupal 4.7.x running on a Unix
platform.

* To use drush from the command line, you'll need a CLI-mode capable PHP
  binary. The minimum required PHP version is 4.3.0 (PHP 5.x is OK, too).
* To perform actions such as installing or updating modules, the CVS binary
  is required. drush has been tested with CVS version 1.11.22.
* To perform certain synchronizing actions, the rsync binary is required.
  drush has been tested using rsync version 2.6.8.

INSTALLATION
------------
1. Copy all the module files into a subdirectory called modules/drush/
   under your Drupal installation directory.
2. Go to administer >> modules and enable the drush module.
3. Go to administer >> settings >> drush to review and change the
   configuration options to suit your particular setup.

UNIX SETUP
----------
To use drush in a pleasant manner on a Linux, BSD or Mac OS X system, you'll
probably want to setup a shell alias like the following:

  alias drush='php modules/drush/drush.php'

This would go into your .profile or .bash_aliases file in your Unix home
directory, and allows you to invoke drush from a Drupal installation
directory simply by typing 'drush' instead of the much more laborious
'modules/drush/drush.php'.

Depending on the specifics of your Drupal setup, you might instead want to
symlink drush into the default binary load PATH with something like:

  # sudo ln -s /usr/local/bin/drush /path/to/drupal/modules/drush/drush.php

This option provides the advantage of being able to invoke drush from any
current directory, not just the Drupal installation's root directory.
However, it is likely to be useful primarily to people running a highly
organized multi-site Drupal hosting environment, where there is only a
single Drupal code base in a centralized location.

FAQ
---

  Q: What does `drush' stand for?
  A: The Drupal Shell.

LIMITATIONS
-----------
* Due to reliance on PHP's tokenizer, drush may not work well in situations
  where the PHP code for the Drupal code base is encrypted (refer to API.txt
  for more information). This is unlikely to change.
* At present, Windows users are SOL. Patches that add Windows support in a
  non-detrimental (for Unix users), maintainable and well-documented fashion
  are welcome. (Of course, y'all *could* just upgrade to a real operating
  system...)

CREDITS
-------
Developed and maintained by Arto Bendiken <http://bendiken.net/>
Inspired by a chat with Adrian Rossouw at DrupalCon Brussels 2006.
