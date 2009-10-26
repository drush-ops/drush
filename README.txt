// $Id$

DESCRIPTION
-----------
drush is a command line shell and Unix scripting interface for Drupal.

Drush core ships with lots of useful commands for interacting with code 
like modules/themes/profiles. Similarly, it runs update.php, executes sql 
queries and DB migrations, and misc utilities like run cron or clear cache.

INSTALLATION
------------
For Linux/Unix/Mac:
  1. Untar the tarball into a folder outside of your web site (/path/to/drush)
  2. Make the 'drush' command executable:
       $ chmod u+x /path/to/drush/drush
  3. (Optional, but recommended:) To ease the use of drush,
     - create a link to drush in a directory that is in your $PATH, e.g.:
       $ ln -s /path/to/drush/drush /usr/bin/drush
     OR
     - create an alias to drush:
       $ alias drush='/path/to/drush/drush'
       (this goes into .profile or .bash_aliases in your home folder)

     NOTE FOR ADVANCED USERS
     - If you want to run drush with a specific version of php, rather than the
       one found by the drush command, you can instead create an alias that
       executes the drush.php file directly:
       $ alias drush='/path/to/php/php5 /path/to/drush/drush.php'
       If you do this, to allow Drush to detect the number of available columns,
       you need to add the line 'export COLUMNS' to the .profile file in your
       home folder.

  4. Start using drush by running "drush" from your Drupal root directory.

     (or, if you did not follow step 3, by running "/path/to/drush/drush"
      or navigating to /path/to/drush and running "./drush" )

    If you have troubles, try using the -l and -r parameters when invoking drush. See below.

For Windows:
  - Follow step 1. Use drush by navigating to /path/to/drush
    and running 'drush.bat'.
  - Whenever the documentation or the help text refers to
   'drush [option] <command>' or something similar, 'drush' has to be replaced
    by 'drush.bat'.
  - If drush.bat is not working for you, either add the directory in which your
    php.exe resides to your PATH or edit drush.bat to point to your php.exe.

USAGE
-----
Once installed and setup (see above), you can use drush as follows while in
any Drupal directory:

  $ drush [options] <command>

Use the 'help' command to get a list of available options and commands:

  $ drush help

For multisite installations, you might need to use the -l or other command line
options just to get drush to work:

  $ drush -l http://example.com help

Related Options:
  -r <path>, --root=<path>      Drupal root directory to use
                                (default: current directory or anywhere in a Drupal directory tree)
  -l <uri> , --uri=<uri>        URI of the drupal site to use
                                (only needed in multisite environments)
  -v, --verbose                 Display all available output

Some other options:
  -i <path>, --include=<path>   Path to folder(s) containing additional drush command files.
                                Follows the POSIX convention of separating paths with a ':'

If you get tired of typing options all the time, you can add them to your drush.php alias or
create a drushrc.php file. These provide additional options for your drush call. They provide
great flexibility for a multi-site installation, for example. See example.drushrc.php.

COMMANDS
--------
Drush ships with a number of commands, but you can easily write
your own. In fact, writing a drush command is no harder that writing simple
Drupal extensions, since drush command files closely follows the structure of
ordinary Drupal modules.

See example.drush.inc for details on the internals of a drush command
file.

You can put your drush command file in a number of places:

  - In a .drush folder in your HOME folder. Note, that you have
    to make the .drush folder yourself.
  - Along with one of your existing modules. If your command is
    related to an existing module, this is the preferred option.
  - In a folder specified with the include option (see above).
  - In /path/to/drush/commands (not a Smart Thing, but it would work).

In any case, it is important that you append it with ".drush.inc", so
that drush can find it.

REQUIREMENTS
------------
* To use drush from the command line, you'll need a CLI-mode capable PHP
  binary. The minimum required PHP version is 5.2.
* drush should also run on Windows, however, drush modules makes use of
  unix command line tools, so to use it effectively, you have to install
  some of them, e.g. from GnuWin32 (http://gnuwin32.sourceforge.net/).

FAQ
---
  Q: What does "drush" stand for?
  A: The Drupal Shell.
  
  Q: How do ?
  A: The Drupal Shell.

CREDITS
-------
Originally developed by Arto Bendiken <http://bendiken.net/> for Drupal 4.7.
Redesigned by Franz Heinzmann (frando) <http://unbiskant.org/> in May 2007 for Drupal 5.
Maintained by Moshe Weitzman <http://drupal.org/moshe> with much help from 
Grugnog2, Adrian Rossouw, and Vingborg.
