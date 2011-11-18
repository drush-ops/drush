<?php

/**
 * Example of valid statements for an alias file.  Use this
 * file as a guide to creating your own aliases.
 *
 * Aliases are commonly used to define short names for
 * local or remote Drupal installations; however, an alias
 * is really nothing more than a collection of options.
 * A canonical alias named "dev" that points to a local
 * Drupal site named "dev.mydrupalsite.com" looks like this:
 *
 *   $aliases['dev'] = array(
 *     'root' => '/path/to/drupal',
 *     'uri' => 'dev.mydrupalsite.com',
 *   );
 *
 * With this alias definition, then the following commands
 * are equivalent:
 *
 *   $ drush @dev status
 *   $ drush --root=/path/to/drupal --uri=dev.mydrupalsite.com status
 *
 * Any option that can be placed on the drush commandline
 * can also appear in an alias definition.
 *
 * There are several ways to create alias files.
 *
 *   + Put each alias in a separate file called ALIASNAME.alias.drushrc.php
 *   + Put multiple aliases in a single file called aliases.drushrc.php
 *   + Put groups of aliases into files called GROUPNAME.aliases.drushrc.php
 *
 * Drush will search for aliases in any of these files using
 * the alias search path.  The following locations are examined
 * for alias files:
 *
 *   1. In any path set in $options['alias-path'] in drushrc.php,
 *      or (equivalently) any path passed in via --alias-path=...
 *      on the command line.
 *   2. In one of the default locations:
 *        a. /etc/drush
 *        b. In the drush installation folder
 *        c. Inside the 'aliases' folder in the drush installation folder
 *        d. $HOME/.drush
 *   3. Inside the sites folder of any bootstrapped Drupal site,
 *      or any local Drupal site indicated by an alias used as
 *      a parameter to a command
 *
 * Folders and files containing other versions of drush in their names will
 * be *skipped* (e.g. mysite.aliases.drush4rc.php or drush4/mysite.aliases.drushrc.php).
 * Names containing the current version of drush (e.g. mysite.aliases.drush5rc.php)
 * will be loaded.
 *
 * Files stored in these locations can be used to create aliases
 * to local and remote Drupal installations.  These aliases can be
 * used in place of a site specification on the command line, and
 * may also be used in arguments to certain commands such as
 * "drush rsync" and "drush sql-sync".
 *
 * Alias files that are named after the single alias they contain
 * may use the syntax for the canoncial alias shown at the top of
 * this file, or they may set values in $options, just
 * like a drushrc.php configuration file:
 *
 *   $options['uri'] = 'dev.mydrupalsite.com',
 *   $options['root'] = '/path/to/drupal';
 *
 * When alias files use this form, then the name of the alias
 * is taken from the first part of the alias filename.
 *
 * Alias groups (aliases stored together in files called
 * GROUPNAME.aliases.drushrc.php, as mentioned above) also
 * create an implicit namespace that is named after the group
 * name.
 *
 * For example:
 *
 *   # File: mysite.aliases.drushrc.php
 *   $aliases['dev'] = array(
 *     'root' => '/path/to/drupal',
 *     'uri' => 'dev.mydrupalsite.com',
 *   );
 *   $aliases['live'] = array(
 *     'root' => '/other/path/to/drupal',
 *     'uri' => 'mydrupalsite.com',
 *   );
 *
 * Then the following special aliases are defined:
 *
 *   @mysite            An alias named after the groupname
 *                      may be used to reference all of the
 *                      aliases in the group (e.g. drush @mydrupalsite status)
 *
 *   @mysite.dev        A copy of @dev
 *
 *   @mysite.live       A copy of @live
 *
 * Thus, aliases defined in an alias group file may be referred to
 * either by their simple (short) name, or by thier full namespace-qualified
 * name.
 *
 * To see an example alias definition for the current bootstrapped
 * site, use the "site-alias" command with the built-in alias "@self":
 *
 *   $ drush site-alias @self
 *
 * TIP:  If you would like to have drush include a 'databases' record
 * in the output, include the options --with-db and --show-passwords:
 *
 *   $ drush site-alias @self --with-db --show-passwords
 *
 * If you would like to see all of the Drupal sites at a specified
 * root directory, use the built-in alias "@sites":
 *
 *   $ drush -r /path/to/drupal site-alias @sites
 *
 * It is also possible to define explicit lists of sites using a special
 * alias list definition.  Alias lists contain a list of alias names in
 * the group, and no other information.  For example:
 *
 * $aiases['mydevsites'] = array(
 *   'site-list' => array('@mysite.dev', '@otherside.dev');
 * );
 *
 * The built-in alias "@none" represents the state of no Drupal site;
 * to ignore the site at the cwd and just see default drush status:
 *
 *   $ drush @none status
 *
 * See `drush help site-alias` for more options for displaying site
 * aliases.  See `drush topic docs-bastion` for instructions on configuring
 * remote access to a Drupal site behind a firewall via a bastion server.
 *
 * Although most aliases will contain only a few options, a number
 * of settings that are commonly used appear below:
 *
 * - 'uri': This should always be the same as the site's folder name
 *     in the 'sites' folder.
 * - 'root': The Drupal root; must not be specified as a relative path.
 * - 'remote-port': If the database is remote and 'db-url' contains
 *     a tunneled port number, put the actual database port number
 *     used on the remote machine in the 'remote-port' setting.
 * - 'remote-host': The fully-qualified domain name of the remote system
 *     hosting the Drupal instance.  The remote-host option must be
 *     omitted for local sites, as this option controls whether or not
 *     rsync parameters are for local or remote machines.
 * - 'remote-user': The username to log in as when using ssh or rsync.
 * - 'os': The operating system of the remote server.  Valid values
 *     are 'Windows' and 'Linux'.  Default value is PHP_OS if 'remote-host'
 *     is not set, and 'Linux' (or $options['remote-os']) if it is.
 * - 'ssh-options': If the target requires special options, such as a non-
 *     standard port, alternative identity file, or alternative
 *     authentication method, ssh- options can contain a string of extra
 *     options that are used with the ssh command, eg "-p 100"
 * - 'parent': The name of a parent alias (e.g. '@server') to use as a basis
 *     for this alias.  Any value of the parent will appear in the child
 *     unless overridden by an item with the same name in the child.
 *     Multiple inheritance is possible; name multiple parents in the
 *     'parent' item separated by commas (e.g. '@server,@devsite').
 * - 'db-url': The Drupal 6 database connection string from settings.php.
 *     For remote databases accessed via an ssh tunnel, set the port
 *     number to the tunneled port as it is accessed on the local machine.
 *     If 'db-url' is not provided, then drush will automatically look it
 *     up, either from settings.php on the local machine, or via backend invoke
 *     if the target alias specifies a remote server.
 * - 'databases': Like 'db-url', but contains the full Drupal 7 databases
 *     record.  Drush will look up the 'databases' record if it is not specified.
 * - 'path-aliases': An array of aliases for common rsync targets.
 *   Relative aliases are always taken from the Drupal root.
 *     '%drush-script': The path to the 'drush' script, or to 'drush.php' or
 *       'drush.bat', as desired.  This is used by backend invoke when drush
 *       runs a drush command.  The default is 'drush' on remote machines, or
 *       the full path to drush.php on the local machine.
 *     '%drush': A read-only property: points to the folder that the drush script
 *       is stored in.
 *     '%dump-dir': Path to directory that "drush sql-sync" should use to store
 *       sql-dump files. Helpful filenames are auto-generated.
 *     '%dump': Path to the file that "drush sql-sync" should use to store sql-dump file.
 *     '%files': Path to 'files' directory.  This will be looked up if not specified.
 *     '%root': A reference to the Drupal root defined in the 'root' item
 *       in the site alias record.
 * - 'php': path to custom php interpreter, defaults to /usr/bin/php
 * - 'php-options': commandline options for php interpreter, you may
 *   want to set this to '-d error_reporting="E_ALL^E_DEPRECATED"'
 * - 'variables' : An array of name/value pairs which override Drupal variables.
 *    These values take precedence even over settings.php variable overrides.
 * - 'command-specific': These options will only be set if the alias
 *   is used with the specified command.  In the example below, the option
 *   `--no-cache` will be selected whenever the @stage alias
 *   is used in any of the following ways:
 *      drush @stage sql-sync @self @live
 *      drush sql-sync @stage @live
 *      drush sql-sync @live @stage
 *   In case of conflicting options, command-specific options in targets
 *   (source and destination) take precedence over command-specific options
 *   in the bootstrapped site, and command-specific options in a destination
 *   alias will take precedence over those in a source alias.
 * - 'source-command-specific' and 'target-command-specific': Behaves exactly
 *   like the 'command-specific' option, but is applied only if the alias
 *   is used as the source or target, respectively, of an rsync or sql-sync
 *   command.  In the example below, `--skip-tables-list=comments` whenever
 *   the alias @live is the target of an sql-sync command, but comments will
 *   be included if @live is the source for the sql-sync command.
 * - '#peer': Settings that begin with a '#' are not used directly by Drush, and
 *   in fact are removed before making a backend invoke call (for example). These
 *   kinds of values are useful in conjunction with shell aliases.  See
 *   `drush topic docs-shell-aliases` for more information on this.
 * Some examples appear below.  Remove the leading hash signs to enable.
 */
#$aliases['stage'] = array(
#    'uri' => 'stage.mydrupalsite.com',
#    'root' => '/path/to/remote/drupal/root',
#    'db-url' => 'pgsql://username:password@dbhost.com:port/databasename',
#    'remote-host' => 'mystagingserver.myisp.com',
#    'remote-user' => 'publisher',
#    'path-aliases' => array(
#      '%drush' => '/path/to/drush',
#      '%drush-script' => '/path/to/drush/drush',
#      '%dump-dir' => '/path/to/dumps/',
#      '%files' => 'sites/mydrupalsite.com/files',
#      '%custom' => '/my/custom/path',
#     ),
#    'databases' => 
#      array (
#        'default' => 
#        array (
#          'default' => 
#          array (
#            'driver' => 'mysql',
#            'username' => 'sqlusername',
#            'password' => 'sqlpassword',
#            'port' => '',
#            'host' => 'localhost',
#            'database' => 'sqldbname',
#          ),
#       ),
#     ),
#     'variables => array(
#        site_name => 'My Drupal site',
#      ),
#     'command-specific' => array (
#       'sql-sync' => array (
#         'no-cache' => TRUE,
#       ),
#     ),
#     # This shell alias will run `mycommand` when executed via `drush @stage site-specific-alias`
#     'shell-aliases' => array (
#       'site-specific-alias' => '!mycommand',
#     ),
#  );
#$aliases['dev'] = array(
#    'uri' => 'dev.mydrupalsite.com',
#    'root' => '/path/to/drupal/root',
#    'variables' => array('mail_system' => array('default-system' => 'DevelMailLog')),
#  );
#$aliases['server'] = array(
#    'remote-host' => 'mystagingserver.myisp.com',
#    'remote-user' => 'publisher',
#  );
#$aliases['live'] = array(
#    'parent' => '@server,@dev',
#    'uri' => 'mydrupalsite.com',
#     'target-command-specific' => array (
#       'sql-sync' => array (
#         'skip-tables-list' => 'comments',
#       ),
#     ),
#  );
