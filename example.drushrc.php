<?php
// $Id$

/*
 * Examples of valid statements for a drushrc.php file. Use this file to cut down on
 * typing of options and avoid mistakes.
 *
 * Rename this file to drushrc.php and optionally copy it to one of
 * five convenient places, listed below in order of precedence:
 *
 * 1. Drupal site folder (e.g sites/{default|example.com}/drushrc.php).
 * 2. Drupal installation root.
 * 3. In any location, as specified by the --config (-c) option.
 * 4. User Home folder (i.e. ~/.drushrc.php).
 * 5. System wide configuration folder (e.g. /etc/drush/drushrc.php).
 * 6. Drush installation folder.
 *
 * If a configuration file is found in any of the above locations, it
 * will be loaded and merged with other configuration files in the
 * search list.
 *
 * IMPORTANT NOTE on configuration file loading:
 *
 * At its core, drush works by "bootstrapping" the Drupal environment
 * in very much the same way that is done during a normal page request
 * from the web server, so most drush commands run in the context
 * of a fully-initialized website.
 *
 * Configuration files are loaded in the reverse order they are
 * shown above.  Configuration files #6 through #3 are loaded immediately;
 * the configuration file stored in the Drupal root is loaded
 * when Drupal is initialized, and the configuration file stored
 * in the site folder is loaded when the site is initialized.
 *
 * This load order means that in a multi-site environment, the
 * configuration file stored in the site folder will only be
 * available for commands that operate on that one particular
 * site.  Additionally, there are some drush commands such as
 * pm-download do not bootstrap a drupal environment at all,
 * and therefore only have access to configuration files #6 - #3.
 * The drush commands 'rsync' and 'sql-sync' are special cases.
 * These commands will load the configuration file for the site 
 * specified by the source parameter; however, they do not
 * load the configuration file for the site specified by the
 * destination parameter, nor do they load configuration files
 * for remote sites.
 */

// DEPRECATED:  Allow command names to contain spaces.
// This feature will be removed shortly; drush-3 will
// require commands to be named with dashes instead of
// spaces (e.g. "cache-clear" instead of "cache clear").
// During the transition period, uncomment the line below
// to allow commands with spaces to be used.
# $options['allow-spaces-in-commands'] = 1;

// Specify a particular multisite.
# $options['l'] = 'http://example.com/subir';

// Specify your Drupal core base directory (useful if you use symlinks).
# $options['r'] = '/home/USER/workspace/drupal-6';

// Load a drushrc.php configuration file from the current working directory.
# $options['c'] = '.';

// Specify CVS for checkouts
# $options['package-handler'] = 'cvs';

// Specify CVS credentials for checkouts (requires --package-handler=cvs)
# $options['cvscredentials'] = 'name:password';

// Specify additional directories to search for *.drush.inc files
// Use POSIX path separator (':')
# $options['i'] = 'sites/default:profiles/myprofile';

// Enable verbose mode.
# $options['v'] = 1;

// Default logging level for php notices.  Defaults to "notice"; set to "warning"
// if doing drush development.  Also make sure that error_reporting is set to E_ALL
// in your php configuration file.  See 'drush status' for the path to your php.ini file.
# $options['php-notices'] = 'warning';

// Specify options to pass to ssh in backend invoke. (Default is to prohibit password authentication; uncomment to change)
# $options['ssh-options'] = '-o PasswordAuthentication=no';

/*
 * Customize this associative array with your own tables. This is the list of
 * tables whose *data* is skipped by the 'sql-dump' and 'sql-sync' commands when
 * a structure-tables-key is provided. You may add new tables to the existing
 * array or add a new element.
 */
$options['structure-tables'] = array(
 'common' => array('accesslog', 'cache', 'cache_filter', 'cache_menu', 'cache_page', 'history', 'search_dataset', 'search_index', 'search_total', 'sessions', 'watchdog'),
);

/*
 * Customize this associative array with your own tables. This is the list of
 * tables that are entirely omitted by the 'sql-dump' and 'sql-sync' commands
 * when a skip-tables-key is provided. This is useful if your database contains
 * non Drupal tables used by some other application or during a migration for
 * example. You may add new tables to the existing array or add a new element.
 */
$options['skip-tables'] = array(
 'common' => array('migration_data1', 'migration_data2'),
);

// Use cvs checkouts when downloading and updating modules.
// An example of a command specific argument being set in drushrc.php
// $options['package-handler'] = 'cvs';

/*
 * Command-specific options
 *
 * To define options that are only applicable to certain commands,
 * make an entry in the 'command-specific' structures as shown below.
 * The name of the command may be either the command's full name
 * or any of the command's aliases.
 *
 * Options defined here will be overridden by options of the same
 * name on the command line.  Unary flags such as "--verbose" are overridden
 * via special "--no-xxx" options (e.g. "--no-verbose").
 *
 * Limitation: If 'verbose' is set in a command-specific option,
 * it must be cleared by '--no-verbose', not '--no-v', and visa-versa.
 */
# $options['command-specific']['rsync'] = array('verbose' => TRUE);
# $options['command-specific']['dl'] = array('cvscredentials' => 'user:pass');

// Specify additional directories to search for scripts
// Use POSIX path separator (':')
# $options['script']['script-path'] = 'sites/all/scripts:profiles/myprofile/scripts';

/**
 * Variable overrides:
 *
 * To override specific entries in the 'variable' table for this site,
 * set them here. Any configuration setting from the 'variable'
 * table can be given a new value. We use the $override global here
 * to make sure that changes from settings.php can not wipe out these
 * settings.
 *
 * Remove the leading hash signs to enable.
 */
# $override = array(
#   'site_name' => 'My Drupal site',
#   'theme_default' => 'minnelli',
#   'anonymous' => 'Visitor',
# );

/**
 * Site aliases:
 *
 * To create aliases to remote Drupal installations, add entries
 * to the site aliases option array here.  These settings can be
 * used in place of a site specification on the command line, and
 * may also be used in arguments to certain commands such as
 * "drush rsync" and "drush sql-sync".
 *
 * Each entry in the 'site-aliases' array is accessed by its
 * site alias (e.g. 'stage' or 'dev').  Only the 'uri' and 'root'
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
#$options['site-aliases']['stage'] = array(
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
#$options['site-aliases']['dev'] = array(
#    'uri' => 'dev.mydrupalsite.com',
#    'root' => '/path/to/drupal/root',
#  );
