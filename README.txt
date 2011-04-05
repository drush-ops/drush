
DESCRIPTION
===========
Drush is a command line shell and Unix scripting interface for Drupal.
If you are unfamiliar with shell scripting, reviewing the documentation
for your shell (e.g. man bash) or reading an online tutorial (e.g. search
for "bash tutorial") will help you get the most out of Drush.

Drush core ships with lots of useful commands for interacting with code
like modules/themes/profiles. Similarly, it runs update.php, executes sql
queries and DB migrations, and misc utilities like run cron or clear cache.


REQUIREMENTS
============
* To use Drush from the command line, you'll need a
  CLI-mode capable PHP binary version 5.2 or greater.

* Drush commands that work with git require git 1.7 or greater.

* Drush is designed a Unix-like OS (Linux, OS X)

* Some Drush commands run on Windows.
  If you must run Drush on Windows, you have to install Unix Tools from
  GnuWin32 (http://gnuwin32.sourceforge.net/). More info
  about Drush on Windows available at http://drupal.org/node/594744.

* Drush works with Drupal 6, Drupal 7, and Drupal 8.  However, occasionally
  recent changes to the most recent version of Drupal can introduce issues
  with Drush.


INSTALLATION
============

STANDARD CONFIGURATION:
-----------------------
1. Place the uncompressed drush.tar.gz, drush.zip, or cloned git repository
   in a directory that is outside of your web root.

2. Make the 'drush' command executable:

     $ chmod u+x /path/to/drush/drush

3. Configure your system to recognize where Drush resides. There are 2 options:

  a) create a symbolic link to the Drush executable in a directory that is already in your PATH, e.g.:

       $ ln -s /path/to/drush/drush /usr/bin/drush

  b) explicitly add the Drush executable to the PATH variable which is defined
     in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc
     that is located in your home folder, i.e.

       export PATH="$PATH:/path/to/drush:/usr/local/bin"
       (your system will search path options from left to right until it finds a result)

     To apply your changes to your current session, either log out and then log back in again,
     or re-load your bash configuration file, i.e.:

       $ source .bashrc

  NOTE:  If you do not follow step 3, you will need to inconveniently run Drush commands using
  the full path to the executable "/path/to/drush/drush" or by navigating to /path/to/drush
  and running "./drush". The -r or -l options will be required (see USAGE, below).

4. Test that Drush is found by your system:

  $ which drush


AMP STACK CONFIGURATIONS:
-------------------------
Users of Apache distributions such as MAMP, XAMPP, or Acquia's Dev Desktop
will want to ensure that its php can be found by the command line by adding
it to the PATH variable, using the method in 3.b above. Depending on the
version and distribution of your AMP stack, php might reside at:

  /Applications/MAMP/bin/php5/bin       MAMP (<=v.1.84)
  /Applications/MAMP/bin/php5.2/bin     MAMP (>v.1.9+)
  /Applications/MAMP/bin/php5.3/bin     MAMP (>v.1.9+)
  /Applications/acquia-drupal/php/bin   Acquia Dev Desktop (Mac)
  /Applications/xampp/xamppfiles/bin    XAMP (Mac)
  /opt/lampp/bin                        XAMPP (Windows)

Most AMP stacks store mysql binaries in the same directory as php, so the
above paths cover both cases. MAMP is an exception in that it stores mysql elsewhere.
Adding mysql to your PATH is advisable, or Drush sql commands may fail:

  /Applications/MAMP/Library/bin

Additionally, you may find that Drush does not use the same version of php.ini
in the command-line as your AMP stack does for the web server. See CONFIGURING
PHP.INI below for more details on how to proceed.


CUSTOM CONFIGURATIONS:
----------------------
Running a specific php-cli version for Drush
- - - - - - - - - - - - - - - - - - - - - - -
  If you want to run Drush with a specific version of php, rather than the
  php-cli defined by your system, you can add an environment variable to your
  the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc
  that is located in your home folder:

    export DRUSH_PHP='/path/to/php'


Controlling how Drush is called via an bash alias
- - - - - - - - - - - - - - - - - - - - - - - - - -
  If you want to exactly control how Drush is called, you may define an alias
  in your shell configuration file that executes the drush.php file directly and
  passes that path to Drush. Note that we can pass options to php if we want to,
  but must also pass them to Drush with the '--php' option. We must also export
  the columns variable so drush output is properly formatted

    alias drush='/path/to/php -d memory_limit=128M /path/to/drush/drush.php --php="/path/to/php -d memory_limit=128M"'
    export COLUMNS


CONFIGURING PHP.INI
-------------------
Usually, php is configured to use separate php.ini files for the web server
and the command line. Make sure that Drush's php.ini is given as much memory
to work with as the web server is; otherwise, Drupal might run out of memory
when Drush bootstraps it.
To see which php.ini file Drush is using, run:

  $ drush status

To see which php.ini file the webserver is using, use the phpinfo() function
in a .php web page.  See http://drupal.org/node/207036.

If Drush is using the same php.ini file as the web server, you can create
a php.ini file exclusively for Drush by copying your web server's php.ini
file to the folder $HOME/.drush or the folder /etc/drush.  Then you may edit
this file and change the settings described above without affecting the
php enviornment of your web server.

Alternately, if you only want to override a few values, copy example.drush.ini
from the "examples" folder into $HOME/.drush or the folder /etc/drush and edit
to suit.  See comments in example.drush.ini for more details.

Drush requires a fairly unrestricted php environment to run in.  In particular,
you should insure that safe_mode, open_basedir, disable_functions and
disable_classes are empty.


INSTALLING DRUSH ON WINDOWS:
----------------------------
Windows support is lacking! Consider using on Linux/Unix/OSX
using Virtualbox or other virtual machine.
Drush 3 has proven more reliable than 4 for many users.

 1. Drush depends on the following external packages:
    Gzip, LibArchive, Tar, Wget, Less
    which can be downloaded from
      http://gnuwin32.sourceforge.net/packages.html
    if you use cygwin, install the ncurses package to for tput support
      http://invisible-island.net/ncurses/

 2. bsdtar.exe is more reliable than tar.exe, so copy bsdtar.exe to tar.exe
      C:\Program Files\GnuWin32\bin\bsdtar.exe
      C:\Program Files\GnuWin32\bin\tar.exe

 3. Set your PATH environment variable to include drush, php, and binaries installed above, i.e.
      ;C:\Program Files (x86)\php;C:\path\to\drush;C:\Program Files (x86)\GnuWin32\bin
    (You will need to reopen your shell/command prompt window so the path variables refresh.)

 4. To disable the warning about Windows support, set $options['check_os'] = FALSE
    in drushrc.php. See examples/example.drushrc.php.

Whenever the documentation or the help text refers to
'drush [option] <command>' or something similar, 'drush' may need to be replaced by 'drush.bat'.

Complete Drush Windows installation documentation can be found at http://drupal.org/node/594744


USAGE
=====
Once you have completed the installation steps, Drush can be run in your shell
by typing "drush" from within any Drupal root directory.

  $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

  $ drush help

For even more documentation, use the 'topic' command:

  $ drush topic

For a full list of Drush commands and documentation by version, visit http://drush.ws

Many commands support a --pipe option which returns machine readable output.
For example, return a list of enabled modules:

  $ drush pm-list --type=module --status=enabled --pipe

For multisite installations, use the -l option to target a particular site.
If you are outside the Drupal web root, you might need to use the -r, -l or
other command line options just for Drush to work. If you do not specify a URI
with -l and Drush falls back to the default site configuration, Drupal's
$GLOBAL['base_url'] will be set to http://default.
This may cause some functionality to not work as expected.

  $ drush -l http://example.com pm-update

Related Options:
  -r <path>, --root=<path>      Drupal root directory to use
                                (defaults to current directory or anywhere in a Drupal directory tree)
  -l <uri> , --uri=<uri>        URI of the Drupal site to use
  -v, --verbose                 Display verbose output.
  --php                         The absolute path to your php binary.

The 'core-cli' command provides a customized bash shell, and the --pipe option
lists the .bashrc code it uses, which you can import into your usual shell.

  $ drush core-cli

Very intensive scripts can exhaust your available PHP memory. One remedy is to
just restart automatically using bash. For example:

  while true; do drush search-index; sleep 5; done


DRUSH CONFIGURATION FILES
=========================
Inside /path/to/drush/examples you will find some example files to help you
get started with your Drush configuration file (example.drushrc.php),
site alias definitions (example.aliases.drushrc.php) and Drush commands
(sandwich.drush.inc). You will also see an example 'policy' file which
can be customized to block certain commands or arguments as required by
your organization's needs.

DRUSHRC.PHP
-----------
If you get tired of typing options all the time you can contain them in a
drushrc.php file. Multiple Drush configuration files can provide the
flexibility of providing specific options in different site  directories of a
multi-site installation. See example.drushrc.php for examples and installation
details.


SITE ALIASES
------------
Drush lets you run commands on a remote server, or even on a set of remote servers.
Once defined, aliases can be references with the @ nomenclature, i.e.

  # Syncronize staging files to production
  $ drush rsync @staging:%files/ @live:%files

  # Syncronize database from production to dev, excluding the cache table
  $ drush sql-sync --structure-tables-key=custom --no-cache @live @dev

See http://drupal.org/node/670460 and example.aliases.drushrc.php for more information.


COMMANDS
--------
Drush can be extended to run your own commands. Writing a Drush command is no harder
than writing simple Drupal modules, since they both follow the same structure.

See sandwich.drush.inc for light details on the internals of a Drush command file.
Otherwise, the core commands in Drush are good models for your own commands.

You can put your Drush command file in a number of places:

  a) In a folder specified with the --include option (see above).

  b) Along with one of your existing modules. If your command is related to an
     existing module, this is the preferred approach.

  c) In a .drush folder in your HOME folder. Note, that you have to create the
     .drush folder yourself.

  d) In the system-wide Drush commands folder, e.g. /usr/share/drush/commands

In any case, it is important that you end the filename with ".drush.inc", so
that Drush can find it.


FAQ
===
  Q: What does "drush" stand for?
  A: The Drupal Shell.

  Q: How do I pronounce Drush?
  A: Some people pronounce the dru with a long u like Drupal. Fidelity points go to
  them, but they are in the minority. Most pronounce Drush so that it rhymes with
  hush, rush, flush, etc. This is the preferred pronunciation.


CREDITS
=======
* Originally developed by Arto Bendiken <http://bendiken.net/> for Drupal 4.7.
* Redesigned by Franz Heinzmann (frando) <http://unbiskant.org/> in May 2007 for Drupal 5.
* Maintained by Moshe Weitzman <http://drupal.org/moshe> with much help from
  Owen Barton, Adrian Rossouw, greg.1.anderson, jonhattan.
