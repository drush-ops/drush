DESCRIPTION
-----------

Drush is a command line shell and Unix scripting interface for Drupal.  If you are unfamiliar with shell scripting, reviewing the documentation for your shell (e.g. man bash) or reading an online tutorial (e.g. search for "bash tutorial") will help you get the most out of Drush.

Drush core ships with lots of useful commands for interacting with code like modules/themes/profiles. Similarly, it runs update.php, executes sql queries and DB migrations, and misc utilities like run cron or clear cache.

DRUSH VERSIONS
--------------

Each version of Drush supports multiple Drupal versions.  Drush 6 is recommended version.

Drush Version | Branch  | PHP | Compatible Drupal versions | Code Status
------------- | ------  | --- | -------------------------- | -----------
Drush 7       | [master](https://travis-ci.org/drush-ops/drush)  | 5.3.3+ | D6, D7, D8                 | <img src="https://travis-ci.org/drush-ops/drush.png?branch=master">
Drush 6       | [6.x](https://travis-ci.org/drush-ops/drush) | 5.3.3+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.png?branch=6.x">
Drush 5       | [5.x](https://travis-ci.org/drush-ops/drush) | 5.2.0+ | D6, D7                     | <img src="https://travis-ci.org/drush-ops/drush.png?branch=5.x">
Drush 4       | 4.x | 5.2.0+ | D5, D6, D7                 | Unsupported
Drush 3       | 3.x | 5.2.0+ | D5, D6                     | Unsupported

Drush comes with a full test suite powered by [PHPUnit](https://github.com/sebastianbergmann/phpunit). Each commit gets tested by the awesome [Travis.ci continuous integration service](https://travis-ci.org/drush-ops/drush).

USAGE
-----------

Drush can be run in your shell by typing "drush" from within any Drupal root directory.

    $ drush [options] <command> [argument1] [argument2]

Use the 'help' command to get a list of available options and commands:

    $ drush help

For even more documentation, use the 'topic' command:

    $ drush topic

Installation instructions can be found below.  For a full list of Drush commands 
and documentation by version, visit http://www.drush.org.


SUPPORT
-----------

Please take a moment to review the rest of the information in this file before
pursuing one of the support options below.

* Post support requests to [Drupal Answers](http://drupal.stackexchange.com/questions/tagged/drush).
* Bug reports and feature requests should be reported in the [GitHub Drush Issue Queue](https://github.com/drush-ops/drush/issues).
* Use pull requests (PRs) to contribute to Drush. See [/CONTRIBUTING.md](CONTRIBUTING.md).
* It is still possible to search the old issue queue on Drupal.org for [fixed bugs](https://drupal.org/project/issues/search/drush?status%5B%5D=7&categories%5B%5D=bug), [unmigrated issues](https://drupal.org/project/issues/search/drush?status%5B%5D=5&issue_tags=needs+migration), [unmigrated bugs](https://drupal.org/project/issues/search/drush?status%5B%5D=5&categories%5B%5D=bug&issue_tags=needs+migration), and so on.

MISC
-----------
* [www.drush.org](http://www.drush.org)
* Subscribe to https://github.com/drush-ops/drush/releases.atom to receive notification on new releases.
* [A list of modules that include Drush integration](http://drupal.org/project/modules?filters=tid%3A4654)
* For more information, please see the [Resources](http://drush.org/resources) and the [Drush FAQ](http://drupal.org/drush-faq). Run the `drush topic` command for even more help.
* If you are using Debian or Ubuntu, you can alternatively use the Debian packages uploaded in your distribution. You may need to use the backports to get the latest version, if you are running a LTS or "stable" release.
* For advice on using Drush with your ISP, see the <a href="http://drush.org/resources#hosting">hosting section of the Resources page</a> on <a href="http://drush.org">drush.org</a>.

REQUIREMENTS
-----------

* To use Drush, you'll need a command line PHP version 5.3.3+.
* Drush commands that work with git require git 1.7 or greater.
* Drush works best on a Unix-like OS (Linux, OS X)
* Most Drush commands run on Windows.  See INSTALLING DRUSH ON WINDOWS, below.

INSTALL - PEAR
-----------
If you have trouble with PEAR installation, consider trying MANUAL INSTALLATION. It is not too hard.

```bash
pear channel-discover pear.drush.org
pear install drush/drush
```

_Tip: Use sudo to overcome permission problems.  If the channel-discover fails, try running the following sequence of commands:_

```bash
pear upgrade --force Console_Getopt
pear upgrade --force pear
pear upgrade-all
```

To update, run `pear upgrade drush/drush`

To get alternate drush versions, replace that last line with one of the below that matches your fancy.

```bash
pear install drush/drush-5.0.0
pear install drush/drush-6.0.0RC4
```

See the POST-INSTALL section for configuration tips.

INSTALL - MANUAL
-----------
1. Place the uncompressed drush.tar.gz, drush.zip, or cloned git repository in a directory that is outside of your web root.
1. Make the 'drush' command executable:
    
    `$ chmod u+x /path/to/drush/drush`

1. Configure your system to recognize where Drush resides. There are 3 options:
    1. Create a symbolic link to the Drush executable in a directory that is already in your PATH, e.g.:

         `$ ln -s /path/to/drush/drush /usr/bin/drush`

    1. Explicitly add the Drush executable to the PATH variable which is defined in the the shell configuration file called .profile, .bash_profile, .bash_aliases, or .bashrc that is located in your home folder, i.e.:

           `export PATH="$PATH:/path/to/drush:/usr/local/bin"`

     Your system will search path options from left to right until it finds a result.

    1. Add an alias for drush (this method can also be handy if you want to use 2 versions of Drush, for example Drush 5 or 6 (stable) for Drupal 7 development, and Drush 7 (master) for Drupal 8 development).
     To add an alias to your Drush 7 executable, add this to you shell configuration file (see list in previous option):
         `$ alias drush-master=/path/to/drush/drush`

    For options 2 and 3 above, in order to apply your changes to your current session, either log out and then log back in again, or re-load your bash configuration file, i.e.:

      `$ source .bashrc`

    NOTE: If you do not follow this step, you will need to inconveniently run Drush commands using the full path to the executable "/path/to/drush/drush" or by navigating to /path/to/drush and running "./drush". The -r or -l options will be required (see USAGE, below).

1. Test that Drush is found by your system:

     `$ which drush`
     
See the POST-INSTALL section for configuration tips.

POST-INSTALL
-----------------------
1. See [example.bashrc](examples/example.bashrc) for instructions on how to add some
   useful shell aliases that provides even tighter integration between
   drush and bash. You may source this file directly into your shell by adding to
   your .bashrc (or equivalent): source /path/to/drush/examples/example.bashrc

1. If you didn't source it the step above, see top of
   [drush.complete.sh](drush.complete.sh) file for instructions adding bash completion for drush
   command to your shell.  Once configured, completion works for site aliases,
   command names, shell aliases, global options, and command-specific options.

1. Optional. If [drush.complete.sh](drush.complete.sh) is being sourced (ideally in
   bash_completion.d), you can use the supplied __drush_ps1() sh function to
   add your current drush site (set with `drush use @sitename`) to your PS1
   prompt like so:
      ```bash
      if [ "\$(type -t __git_ps1)" ] && [ "\$(type -t __drush_ps1)" ]; then
        PS1='\u@\h \w$(__git_ps1 " (%s)")$(__drush_ps1 "[%s]")\$ '
      fi
      ```
   Putting this in a .bashrc/.bash_profile/.profile would produce this prompt:

     `msonnabaum@hostname ~/repos/drush (master)[@sitename]$`
     
1. Help the Drush development team by sending anonymized usage statistics.  To automatically send usage data, please add the following to a .drushrc.php file:

       ```php
       $options['drush_usage_log'] = TRUE;
       $options['drush_usage_send'] = TRUE;
       ```

     Stats are usually logged locally and sent whenever log file exceeds 50Kb.
     Alternatively, one may disable automatic sending and instead use 
     `usage-show` and `usage-send` commands to more carefully send data.
     

ADDITIONAL CONFIGURATIONS FOR MAMP:
-----------------------------------

Users of MAMP will need to manually specify in their PATH which version of php
and MySQL to use in the command line interface. This is independent of the php
version selected in the MAMP application settings.  Under OS X, edit (or create
if it does not already exist) a file called .bash_profile in your home folder.

To use php 5.3.x, add this line to .bash_profile:

    export PATH="/Applications/MAMP/Library/bin:/Applications/MAMP/bin/php5.3/bin:$PATH"

If you want to use php 5.4.x, add this line instead:

    export PATH="/Applications/MAMP/Library/bin:/Applications/MAMP/bin/php5.4/bin:$PATH"

If you have MAMP v.1.84 or lower, this configuration will work for both version
of PHP:

    export PATH="/Applications/MAMP/Library/bin:/Applications/MAMP/bin/php5/bin:$PATH"

If you have done this and are still getting a "no such file or directory" error
from PDO::__construct, try this:

```bash
  sudo mkdir /var/mysql
  sudo ln -s /Applications/MAMP/tmp/mysql/mysql.sock /var/mysql/mysql.sock
```

Additionally, you may need to adjust your php.ini settings before you can use
drush successfully. See CONFIGURING PHP.INI below for more details on how to
proceed.

ADDITIONAL CONFIGURATIONS FOR OTHER AMP STACKS:
-----------------------------------------------

Users of other Apache distributions such as XAMPP, or Acquia's Dev Desktop will
want to ensure that its php can be found by the command line by adding it to
the PATH variable, using the method in 3.b above. Depending on the version and
distribution of your AMP stack, PHP might reside at:

Path                                       | Application
-----                                      | ----
/Applications/acquia-drupal/php/bin        | Acquia Dev Desktop (Mac)
/Applications/xampp/xamppfiles/bin         | XAMP (Mac)
/opt/lampp/bin                             | XAMPP (Windows)

Additionally, you may need to adjust your php.ini settings before you can use
drush successfully. See CONFIGURING PHP.INI below for more details on how to
proceed.

RUNNING A SPECIFIC PHP FOR DRUSH
--------------------------

  If you want to run Drush with a specific version of php, rather than the
  php defined by your shell, you can add an environment variable to your
  the shell configuration file called .profile, .bash_profile, .bash_aliases,
  or .bashrc that is located in your home folder:

    export DRUSH_PHP='/path/to/php'

CONFIGURING PHP.INI
-------------------

Usually, php is configured to use separate php.ini files for the web server and
the command line. Make sure that Drush's php.ini is given as much memory to
work with as the web server is; otherwise, Drupal might run out of memory when
Drush bootstraps it.

To see which php.ini file Drush is using, run:

    $ drush status

To see which php.ini file the webserver is using, use the phpinfo() function in
a .php web page.  See http://drupal.org/node/207036.

If Drush is using the same php.ini file as the web server, you can create a
php.ini file exclusively for Drush by copying your web server's php.ini file to
the folder $HOME/.drush or the folder /etc/drush.  Then you may edit this file
and change the settings described above without affecting the php enviornment
of your web server.

Alternately, if you only want to override a few values, copy [example.drush.ini](examples/example.drush.ini)
from the /examples folder into $HOME/.drush or the folder /etc/drush and edit
to suit.  See comments in example.drush.ini for more details.

You may also use environment variables to control the php settings that Drush
will use.  There are three options:

```bash
export PHP_INI='/path/to/php.ini'
export DRUSH_INI='/path/to/drush.ini'
export PHP_OPTIONS='-d memory_limit="128M"'
```

In the case of PHP_INI and DRUSH_INI, these environment variables specify the
full path to a php.ini or drush.ini file, should you wish to use one that is
not in one of the standard locations described above.  The PHP_OPTIONS
environment variable can be used to specify individual options that should
be passed to php on the command line when Drush is executed.

Drush requires a fairly unrestricted php environment to run in.  In particular,
you should insure that safe_mode, open_basedir, disable_functions and
disable_classes are empty.  If you are using php 5.3.x, you may also need to
add the following definitions to your php.ini file:

```ini
magic_quotes_gpc = Off
magic_quotes_runtime = Off
magic_quotes_sybase = Off
```

INSTALLING DRUSH ON WINDOWS:
----------------------------

Windows support has improved, but is still lagging. For full functionality,
consider using on Linux/Unix/OSX using Virtualbox or other virtual machine.

There is a Windows msi installer for drush available at http://www.drush.org/drush_windows_installer.

Please see that page for more information on running Drush on Windows.

Whenever the documentation or the help text refers to 'drush [option]
<command>' or something similar, 'drush' may need to be replaced by
'drush.bat'.

Additional Drush Windows installation documentation can be found at
http://drupal.org/node/594744.

Most Drush commands will run in a Windows CMD shell or PowerShell, but the
Git Bash shell provided by the 'Git for Windows' installation is the preferred
shell in which to run Drush commands. For more information on "Git for Windows'
visit http://msysgit.github.com/.

When creating aliases for Windows remote machines, pay particular attention to
information presented in the example.aliases.drushrc.php file, especially when
setting values for 'remote-host' and 'os', as these are very important when
running Drush rsync and Drush sql-sync commands.


OPTIONS
-----------

For multisite installations, use the -l option to target a particular site.  If
you are outside the Drupal web root, you might need to use the -r, -l or other
command line options just for Drush to work. If you do not specify a URI with
-l and Drush falls back to the default site configuration, Drupal's
$GLOBAL['base_url'] will be set to http://default.  This may cause some
functionality to not work as expected.

    $ drush -l http://example.com pm-update

Related Options:
  ```
  -r <path>, --root=<path>      Drupal root directory to use
                                (defaults to current directory or anywhere in a
                                Drupal directory tree)
  -l <uri> , --uri=<uri>        URI of the Drupal site to use
  -v, --verbose                 Display verbose output.
  ```

Very intensive scripts can exhaust your available PHP memory. One remedy is to
just restart automatically using bash. For example:

    while true; do drush search-index; sleep 5; done


DRUSH CONFIGURATION FILES
-----------

Inside the [examples](examples) directory you will find some example files to help you get
started with your Drush configuration file (example.drushrc.php), site alias
definitions (example.aliases.drushrc.php) and Drush commands
(sandwich.drush.inc). You will also see an example 'policy' file which can be
customized to block certain commands or arguments as required by your
organization's needs.

DRUSHRC.PHP
-----------

If you get tired of typing options all the time you can contain them in a
drushrc.php file. Multiple Drush configuration files can provide the
flexibility of providing specific options in different site directories of a
multi-site installation. See [example.drushrc.php](examples/example.drushrc.php) for examples and installation
details.

SITE ALIASES
------------

Drush lets you run commands on a remote server, or even on a set of remote
servers.  Once defined, aliases can be references with the @ nomenclature, i.e.

```bash
# Synchronize staging files to production
$ drush rsync @staging:%files/ @live:%files
# Syncronize database from production to dev, excluding the cache table
$ drush sql-sync --structure-tables-key=custom --no-cache @live @dev
```

See http://drupal.org/node/670460 and [example.aliases.drushrc.php](examples/example.aliases.drushrc.php) for more
information.

COMMANDS
--------

Drush can be extended to run your own commands. Writing a Drush command is no harder than writing simple Drupal modules, since they both follow the same structure.

See [sandwich.drush.inc](examples/sandwich.drush.inc) for a quick tutorial on Drush command files.  Otherwise, the core commands in Drush are good models for your own commands.

You can put your Drush command file in a number of places:

  1. In a folder specified with the --include option (see `drush topic
     docs-configuration`).
  1. Along with one of your enabled modules. If your command is related to an
     existing module, this is the preferred approach.
  1. In a .drush folder in your HOME folder. Note, that you have to create the
     .drush folder yourself.
  1. In the system-wide Drush commands folder, e.g. /usr/share/drush/commands.
  1. In Drupal's /drush or sites/all/drush folders. Note, that you have to create the
     drush folder yourself.

In any case, it is important that you end the filename with ".drush.inc", so that Drush can find it.


FAQ
------

```
  Q: What does "drush" stand for?
  A: The Drupal Shell.

  Q: How do I pronounce Drush?
  A: Some people pronounce the dru with a long u like Drupal. Fidelity points
     go to them, but they are in the minority. Most pronounce Drush so that it
     rhymes with hush, rush, flush, etc. This is the preferred pronunciation.

  Q: Does Drush have unit tests?
  A: Drush has an excellent suite of unit tests. See the README.md file in the /tests subdirectory for
     more information.
```

CREDITS
-----------

* Originally developed by [Arto Bendiken](http://bendiken.net) for Drupal 4.7.
* Redesigned by [Franz Heinzmann](http://unbiskant.org) in May 2007 for Drupal 5.
* Maintained by [Moshe Weitzman](http://drupal.org/moshe) with much help from
  Owen Barton, greg.1.anderson, jonhattan, Mark Sonnabaum, and Jonathan Hedstrom.

![Drush Logo](drush_logo-black.png)
