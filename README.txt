
DESCRIPTION
-----------
Drush is a command line shell and Unix scripting interface for Drupal.
If you are unfamiliar with shell scripting, reviewing the documentation
for your shell (e.g. man bash) or reading an online tutorial (e.g. search
for "bash tutorial") will help you get the most out of Drush.

Drush core ships with lots of useful commands for interacting with code
like modules/themes/profiles. Similarly, it runs update.php, executes sql
queries and DB migrations, and misc utilities like run cron or clear cache.

REQUIREMENTS
------------
* To use drush from the command line, you'll need a CLI-mode capable PHP
  binary. The minimum PHP version is 5.2.
* Drush 4 does not support Windows; see "For Windows", below.
* Drush works with Drupal 5, Drupal 6 and Drupal 7.  However, occasionally
  recent changes to the most recent version of Drupal can introduce issues
  with drush.  On Drupal 5, drush requires update_status v5.x-2.5 or later
  in order to use pm-updatecode.  If you have an earlier version of update_status,
  upgrade it via "drush dl update_status" before using pm-updatecode.


INSTALLATION
------------
For Linux/Unix/Mac:
  1. Untar the tarball into a folder outside of your web site (/path/to/drush)
     (e.g. if drush is in your home directory, ~/drush can be used for /path/to/drush)
  2. Make the 'drush' command executable:
       $ chmod u+x /path/to/drush/drush
  3. (Optional, but recommended:) To ease the use of drush,
     - create a link to drush in a directory that is in your PATH, e.g.:
       $ ln -s /path/to/drush/drush /usr/local/bin/drush
     OR
     - add the folder that contains drush to your PATH
       PATH=$PATH:/path/to/drush

       This goes into .profile, .bash_aliases or .bashrc in your home folder.
       NOTE:  You must log out and then log back in again or re-load your bash
       configuration file to apply your changes to your current session:
       $ source .bashrc

     NOTE FOR ADVANCED USERS
     - If you want to run drush with a specific version of php, rather than the
       one found by the drush command, you can define an environment variable
       DRUSH_PHP that points to the php to execute:
       export DRUSH_PHP=/usr/bin/php5
     OR
     - If you want to exactly control how drush is called, you may define an alias
       that executes the drush.php file directly and passes that path to drush:
       $ alias drush='/path/to/php/php5 -d memory_limit=128M /path/to/drush/drush.php --php="/path/to/php/php5 -d memory_limit=128M"'
       Note that it is necessary to pass the '--php' option to drush to define
       how drush should call php if it needs to do so.
       If you define an alias, to allow Drush to detect the number of available columns,
       you need to add the line 'export COLUMNS' to the .profile file in your
       home folder.

     NOTE ON PHP.INI FILES
     - Usually, php is configured to use separate php.ini files for the web server
       and the command line.  To see which php.ini file drush is using, run:
       $ drush status
     - Compare the php.ini that drush is using with the php.ini that the webserver is
       using.  Make sure that drush's php.ini is given as much memory to work with as
       the web server is; otherwise, Drupal might run out of memory when drush
       bootstraps it.
     - Drush requires a fairly unrestricted php environment to run in.  In particular,
       you should insure that safe_mode, open_basedir, disable_functions and
       disable_classes are empty.
     - If drush is using the same php.ini file as the web server, you can create
       a php.ini file exclusively for drush by copying your web server's php.ini
       file to the folder $HOME/.drush or the folder /etc/drush.  Then you may edit
       this file and change the settings described above without affecting the
       php enviornment of your web server.  Alternately, if you only want to
       override a few values, copy example.drush.ini from the "examples" folder
       into $HOME/.drush or the folder /etc/drush and edit to suit.  See comments
       in example.drush.ini for more details.
       
  4. Start using drush by running "drush" from your Drupal root directory.

     (or, if you did not follow step 3, by running "/path/to/drush/drush"
      or navigating to /path/to/drush and running "./drush" )

    If you have troubles, try using the -l and -r options when invoking drush. See below.

For Windows:
  - Drush 4 does not support Windows.  Consider using Drush 3 or Drush 5 instead.
  - Consider using on Linux/Unix/OSX using Virtualbox or other VM. Windows support is lacking.
  - The Drush 5 Windows installer can be found at http://drush.ws/drush_windows_installer.
  - Instructions for installing Drush 3 on Windows can be found at 
    http://drupal.org/node/594744.

USAGE
-----
Once installed and setup, you can use drush as follows while in
any Drupal directory:

  $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

  $ drush help

For even more documentation, use the 'topic' command:

  $ drush topic

For multisite installations, you might need to use the -l or other command line
options just to get drush to work:

  $ drush -l http://example.com help

Related Options:
  -r <path>, --root=<path>      Drupal root directory to use
                                (default: current directory or anywhere in a Drupal directory tree)
  -l <uri> , --uri=<uri>        URI of the drupal site to use
                                (only needed in multisite environments)
  -v, --verbose                 Display verbose output.
  --php                         The absolute path to your php binary.

NOTE: If you do not specify a URI with -l and drush falls back to the default
site configuration, Drupal's $GLOBAL['base_url'] will be set to http://default.
This may cause some functionality to not work as expected.

The drush core-cli command provide a customized bash shell or lets you enhance
your usual shell with its --pipe option.

Many commands support a --pipe option which returns machine readable output. See
`drush pm-list --status=enabled --pipe` as an example.

Very intensive scripts can exhaust your available PHP memory. One remedy is to 
just restart automatically using bash. For example:

    while true; do drush search-index; sleep 5; done

EXAMPLES
--------
Inside the "examples" folder you will find some example files to help you
get started with your drush configuration file (example.drushrc.php),
site alias definitions (example.aliases.drushrc.php) and drush commands
(sandwich.drush.inc). You will also see an example 'policy' file which 
can be customized to block certain commands or arguments as your organization
needs.

DRUSHRC.PHP
--------
If you get tired of typing options all the time, you can add them to your drush.php alias or
create a drushrc.php file. These provide additional options for your drush call. They provide
great flexibility for a multi-site installation, for example. See example.drushrc.php.

SITE ALIASES
--------
Drush lets you run commands on a remote server, or even on a set of remote servers.
See http://drupal.org/node/670460 and example.aliases.drushrc.php for more information.

COMMANDS
--------
Drush ships with a number of commands, but you can easily write
your own. In fact, writing a drush command is no harder than writing simple
Drupal modules, since drush command files closely follow the structure of
ordinary Drupal modules.

See sandwich.drush.inc for light details on the internals of a drush command file.
Otherwise, the core commands in drush are good models for your own commands.

You can put your drush command file in a number of places:

  - In a folder specified with the --include option (see above).
  - Along with one of your existing modules. If your command is related to an
    existing module, this is the preferred approach.
  - In a .drush folder in your HOME folder. Note, that you have to create the
    .drush folder yourself.
  - In the system-wide drush commands folder, e.g. /usr/share/drush/commands

In any case, it is important that you end the filename with ".drush.inc", so
that drush can find it.

FAQ
---
  Q: What does "drush" stand for?
  A: The Drupal Shell.

  Q: How do I pronounce drush?
  A: Some people pronounce the dru with a long u like drupal. Fidelity points go to
  them, but they are in the minority. Most pronounce drush so that it rhymes with
  hush, rush, flush, etc. This is the preferred pronunciation.

CREDITS
-------
Originally developed by Arto Bendiken <http://bendiken.net/> for Drupal 4.7.
Redesigned by Franz Heinzmann (frando) <http://unbiskant.org/> in May 2007 for Drupal 5.
Maintained by Moshe Weitzman <http://drupal.org/moshe> with much help from
Owen Barton, Adrian Rossouw, greg.1.anderson, jonhattan.
