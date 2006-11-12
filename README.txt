// $Id$

NOTE: this module is currently in a pre-alpha state. Come back later unless
you're a developer and don't mind looking at the code to figure things out.

DESCRIPTION
-----------
drush is a command line shell and Unix scripting interface for Drupal.

Use the 'help' command to get a list of built-in services and actions:

  # drush help

(You can also view the command listing at administer >> help >> drush.)

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
To use drush in a pleasant manner, you'll probably want to setup a shell
alias like the following:

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

LIMITATIONS
-----------
* At present, Windows users are SOL. Patches that add Windows support in a
  non-detrimental (for Unix users), maintainable and well-documented fashion
  are welcome.
* Due to reliance on PHP's tokenizer, drush may not work well in situations
  where the PHP code for the Drupal code base is encrypted (refer to API.txt
  for more information). This is unlikely to change.

TODO/IDEAS
----------
* user.drush, node.drush: finish up remaining core services.
* setup.drush: setup a new Drupal instance, load database scripts, etc.
* stats.drush: calculate and report code base statistics (KLOCs, etc) for
  the Drupal core or an installed module.
* phpdoc.drush: rebuild all PHPDoc documentation, and provide summary of
  TODO and FIXME comments in code base.
* simpletest.drush: service for easier unit testing.
* Add a setting for running admin-specified drush commands on cron.
* Ability to register drush commands as XML-RPC callbacks.
  (http://api.drupal.org/api/4.7/function/hook_xmlrpc)
* Unix user / Drupal site-specific .drush/ directory for settings and state.
* Enter interactive mode when no command given on command line.
* Command tab completion and service on-demand auto-loading.

CREDITS
-------
Developed and maintained by Arto Bendiken <http://bendiken.net/>
Inspired by a chat with Adrian Rossouw at DrupalCon Brussels 2006.
