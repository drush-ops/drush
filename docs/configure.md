Configure
-----------------------

* See [example.bashrc](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.bashrc) for instructions on how to add some
   useful shell aliases that provides even tighter integration between
   drush and bash. You may source this file directly into your shell by adding to
   your .bashrc (or equivalent): source /path/to/drush/examples/example.bashrc

* If you didn't source it the step above, see top of
   [drush.complete.sh](https://raw.githubusercontent.com/drush-ops/drush/master/drush.complete.sh) file for instructions on adding completion for drush
   commands to your shell.  Once configured, completion works for site aliases,
   command names, shell aliases, global options, and command-specific options.

* Optional. If [drush.complete.sh](https://raw.githubusercontent.com/drush-ops/drush/master/drush.complete.sh) is being sourced (ideally in
   bash_completion.d), you can use the supplied \__drush_ps1() sh function to
   add your current drush site (set with `drush use @sitename`) to your PS1
   prompt like so:
      
      ```   
      if [ "\$(type -t __git_ps1)" ] && [ "\$(type -t __drush_ps1)" ]; then
        PS1='\u@\h \w$(__git_ps1 " (%s)")$(__drush_ps1 "[%s]")\$ '
      fi
      ```
      
   Putting this in a .bashrc/.bash_profile/.profile would produce this prompt:

     `msonnabaum@hostname ~/repos/drush (master)[@sitename]$`

* Help the Drush development team by sending anonymized usage statistics.  To automatically send usage data, please add the following to a .drushrc.php file:
     ```
     $options['drush_usage_log'] = TRUE;
     $options['drush_usage_send'] = TRUE;
     ```

     Stats are usually logged locally and sent whenever log file exceeds 50Kb.
     Alternatively, one may disable automatic sending and instead use
     `usage-show` and `usage-send` commands to more carefully send data.


Additional Configurations for Mamp:
-----------------------------------

Users of MAMP will need to manually specify in their PATH which version of php
and MySQL to use in the command line interface. This is independent of the php
version selected in the MAMP application settings.  Under OS X, edit (or create
if it does not already exist) a file called .bash_profile in your home folder.

If you want to use php 5.4.x, add this line:

    export PATH="/Applications/MAMP/Library/bin:/Applications/MAMP/bin/php5.4/bin:$PATH"
    
If you use MAMP 3 (php 5.5.14 by default) and want to use php 5.5.x , add this line instead:

    export PATH="/Applications/MAMP/Library/bin:/Applications/MAMP/bin/php/php5.5.14/bin:$PATH"

If you have MAMP v.1.84 or lower, this configuration will work for both versions
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

Additional Configurations for Other amp Stacks:
-----------------------------------------------

Users of other Apache distributions such as XAMPP, or Acquia's Dev Desktop v1 will
want to ensure that its php can be found by the command line by adding it to
the PATH variable, using the method in 3.b above. Depending on the version and
distribution of your AMP stack, PHP might reside at:

Path                                       | Application
-----                                      | ----
/Applications/acquia-drupal/php/bin        | Acquia Dev Desktop v1 (Mac). v2 has own Drush.
/Applications/xampp/xamppfiles/bin         | XAMP (Mac)
/opt/lampp/bin                             | XAMPP (Windows)

Additionally, you may need to adjust your php.ini settings before you can use
drush successfully. See CONFIGURING PHP.INI below for more details on how to
proceed.

Running a Specific php for Drush
--------------------------

  If you want to run Drush with a specific version of php, rather than the
  php defined by your shell, you can add an environment variable to your
  the shell configuration file called .profile, .bash_profile, .bash_aliases,
  or .bashrc that is located in your home folder:

    export DRUSH_PHP='/path/to/php'

Configuring php.ini
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
disable_classes are empty.

Drushrc.php
-----------

If you get tired of typing options all the time you can contain them in a
drushrc.php file. Multiple Drush configuration files can provide the
flexibility of providing specific options in different site directories of a
multi-site installation. See [example.drushrc.php](https://raw.githubusercontent.com/drush-ops/drush/master/examples/example.drushrc.php) for examples and installation
details.

Configuring Drush for Php 5.5
-----------------------------

If you are running on Linux, you may find that you need
the php5-json package.  On Ubuntu, you can install it via:

`apt-get install php5-json`
