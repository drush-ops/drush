<?php

/**
 * Example of valid statements for an alias file.  Use this
 * file as a guide to creating your own aliases.
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
 * to local and remote Drupal installations.  These aliases can beTo create aliases to remote Drupal installations, add entries
 * used in place of a site specification on the command line, and
 * may also be used in arguments to certain commands such as
 * "drush rsync" and "drush sql-sync".
 *
 * Each entry in the aliases array is accessed by its
 * site alias (e.g. '@stage' or '@dev').  Only the 'uri' and 'root'
 * items are required, and most alias records use only a few of
 * the optional keys, if any.  A simple alias record can be generated
 * using the "drush --full site-alias" command.
 *
 * The following settings are stored in the site alias record:
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
 *     '%dump': Path to the file that "drush sql-sync" should use to store sql-dump file.
 *     '%files': Path to 'files' directory.  This will be looked up if not specified.
 *     '%root': A reference to the Drupal root defined in the 'root' item
 *       in the site alias record.
 *
 * Alias files can be expressed in either of two syntactic variants.
 * Alias files that contain multiple aliases can specify each alias
 * as a separate named item of the $aliases array, like so:
 *
 *   $aliases['dev'] = array(
 *     'uri' => 'dev.mydrupalsite.com',
 *     'root' => '/path/to/drupal',
 *   );
 *
 * Alias files that are named after the single alias they contain
 * may use the syntax above, or they may set values in $options, just
 * like a drushrc.php configuration file:
 *
 *   $options['uri'] = 'dev.mydrupalsite.com',
 *   $options['root'] = '/path/to/drupal';
 *
 * In the later case, the alias file must be named using the
 * ALIASNAME.alias.drushrc.php variant, and the name of the alias
 * is taken from the first part of the alias filename.
 *
 * Remove the leading hash signs to enable.
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
#      '%dump' => '/path/to/live/sql_dump.sql',
#      '%files' => 'sites/mydrupalsite.com/files',
#      '%custom' => '/my/custom/path',
#     ),
#  );
#$aliases['dev'] = array(
#    'uri' => 'dev.mydrupalsite.com',
#    'root' => '/path/to/drupal/root',
#  );
