// $Id$

DESCRIPTION
-----------
drush is a command line shell and Unix scripting interface for Drupal, a
veritable Swiss Army knife designed to make life easier for those of us who
spend most of our working hours hacking away at the command prompt.

Green text against a black background is optional. Perhaps you would like
some alpha-channel translucency with that? ;)

drush.module itself doesn't provide any actual tools or commands but the API
for those. There are several modules that provide drush utilities included in
this download. See Package Manager, SQL Tools, and Toolbox.

INSTALLATION
------------
For Linux/Unix/Mac:
  1. Untar the tarball into your module directory (sites/all/modules)
  2. Enable drush.module and any submodules you want to use. You must enable the CVS or wget modules
     if you want to install modules (you do).
  3. (optional, but recommended:) To ease the use of drush,
     - create a link to drush.php in a directory that is in your $PATH, e.g.:
       $ ln /path/to/drush.php /usr/bin/drush
     OR
     - create an alias to drush.php:
       $ alias drush='php modules/drush/drush.php'
       (this goes into .profile or .bash_aliases in your home folder)

  4. Start using drush by running "drush" from your Drupal root directory.

     (or, if you did not follow step 3, by running "./sites/all/modules/drush.php"
      or navigating to sites/all/modules/drush and running "./drush.php" )
      
    If you have troubles, try using the -l and -r flags when invoking drush.php. See below.

For Windows (experimental!):
  - Follow steps 1 and 2. Use drush by navigating to sites/all/modules/drush
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

  $ drush [options] <command> <command> ...

Use the 'help' command to get a list of available options and commands:

  $ drush help

Please note that drush requires a working drupal setup in order to function
correctly. So for multisite installations, you might need to use the -l or other command line 
options just to get the help command to work:

  $ drush -l http://association.drupal.org/drupal help

Related Options:
  -r <path>, --root=<path>      Drupal root directory to use (default: current directory)            
  -l <uri> , --uri=<uri>        URI of the drupal site to use (only needed in multisite environments)
  -v, --verbose                 Display all available output
  
If you get tired of typing options all the time, you can add them to your drush.php alias or create a drushrc.php file. These provide additional options for your drush call. They provide great flexibility for a multi-site installation, for example. See example.drushrc.php.                                         

REQUIREMENTS
------------
This version of drush is designed for Drupal 6.x running on a Unix/Linux
platform.

* To use drush from the command line, you'll need a CLI-mode capable PHP
  binary. The minimum required PHP version is 4.3.0 (PHP 5.x is OK, too).
* drush should also run on Windows, however, drush modules might make use of
  unix command line tools, so to use it effectively, you have to install
  some of them, e.g. from GnuWin32 (http://gnuwin32.sourceforge.net/).
  The READMEs of the individual modules should state which binaries are required.

FAQ
---
  Q: What does "drush" stand for?
  A: The Drupal Shell.

LIMITATIONS
-----------
* Due to reliance on PHP's tokenizer, drush may not work well in situations
  where the PHP code for the Drupal code base is encrypted (refer to API.txt
  for more information). This is unlikely to change.

CREDITS
-------
Originally developed by Arto Bendiken <http://bendiken.net/> for Drupal 4.7.
Redesigned by Franz Heinzmann (frando) <http://unbiskant.org/> in May 2007 for Drupal 5.
Further refined by Moshe Weitzman <http://drupal.org/moshe>
