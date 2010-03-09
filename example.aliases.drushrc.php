<?php

/**
 * Site aliases:
 *
 * To create aliases to remote Drupal installations, add entries
 * to the site aliases array here.  These settings can be
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
 * - 'db-url': The Drupal 6 database connection string from settings.php.
 *     For remote databases accessed via an ssh tunnel, set the port
 *     number to the tunneled port as it is accessed on the local machine.
 *     If 'db-url' is not provided, then drush will automatically look it
 *     up, either from settings.php on the local machine, or via backend invoke
 *     if the target alias specifies a remote server.
 * - 'databases': Like 'db-url', but contains the full Drupal 7 databases
 *     record.  Drush will look up the 'databases' record if it is not specified.
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
