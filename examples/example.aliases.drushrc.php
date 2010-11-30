<?php

/**
 * Example of valid statements for an alias file.  Use this
 * file as a guide to creating your own aliases.
 *
 * Aliases are commonly used to define short names for
 * local or remote Drupal installations; however, an alias
 * is really nothing more than a collection of option sets.
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
 *   2. If 'alias-path' is not set, then in one of the default
 *      locations:
 *        a. /etc/drush
 *        b. In the drush installation folder
 *        c. Inside the 'aliases' folder in the drush installation folder
 *        d. $HOME/.drush
 *   3. Inside the sites folder of any bootstrapped Drupal site,
 *      or any local Drupal site indicated by an alias used as
 *      a parameter to a command
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
 * If you would like to see all of the Drupal sites at a specified
 * root directory, use the built-in alias "@sites":
 *
 *   $ drush -r /path/to/drupal site-alias @sites
 *
 * See 'drush help site-alias' for more options for displaying site
 * aliases.
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
 * - 'ssh-options': If the target requires special options, such as a non-
 *     standard port, alternative identity file, or alternative
 *     authentication method, ssh- options can contain a string of extra
 *     options  that are used with the ssh command, eg "-p 100"
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
 *     '%drush': The path to the folder where drush is stored.  Optional;
 *       defaults to the folder containing the running script.  Always be sure
 *       to set '%drush' if the path to drush is different on the remote server.
 *     '%drush-script': The path to the 'drush' script (used by backend invoke);
 *       default is 'drush' on remote machines, or the full path to drush.php on
 *       the local machine.  Note that you only need to define one of '%drush'
 *       or '%drush-script', as drush can infer one from the other.
 *     '%dump-dir': Path to directory that "drush sql-sync" should use to store
 *       sql-dump files. Helpful filenames are auto-generated.
 *     '%dump': Path to the file that "drush sql-sync" should use to store sql-dump file.
 *     '%files': Path to 'files' directory.  This will be looked up if not specified.
 *     '%root': A reference to the Drupal root defined in the 'root' item
 *       in the site alias record.
 *
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
#  );
#$aliases['dev'] = array(
#    'uri' => 'dev.mydrupalsite.com',
#    'root' => '/path/to/drupal/root',
#  );
#$aliases['server'] = array(
#    'remote-host' => 'mystagingserver.myisp.com',
#    'remote-user' => 'publisher',
#  );
#$aliases['live'] = array(
#    'parent' => '@server,@dev',
#    'uri' => 'mydrupalsite.com',
#  );
